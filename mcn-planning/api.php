<?php
/* ============================================================================
   MCN DALOA — EMPLOI DU TEMPS
   api.php  —  Le "pont" entre la page web et la base de donnees
   ----------------------------------------------------------------------------
   La page (index.html) appelle ce fichier pour :
     - LIRE   les notes :           GET  api.php?action=list
     - ENREGISTRER des changements : POST api.php?action=sync
            corps JSON { "upsert": [note, ...], "delete": [id, ...] }
            -> seules les notes modifiees / supprimees sont touchees : deux
               personnes peuvent travailler en meme temps sans s'effacer.
     - TOUT REMPLACER (bouton Reinitialiser) : POST api.php?action=save
            corps JSON { "notes": [...] }
   Toutes les reponses sont au format JSON.
   ============================================================================ */

require __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

/* Petite fonction pour repondre en JSON puis arreter le script. */
function respond(array $data, int $http = 200): void
{
    http_response_code($http);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/* Les 4 seuls types de note autorises. */
const TYPES_VALIDES = ['commande', 'cheque', 'rendezvous', 'autre'];
const ID_MAX = 4294967295;   // limite d'un INT UNSIGNED

/* Verifie et nettoie une note recue. Renvoie null si elle est inutilisable. */
function clean_note($n): ?array
{
    if (!is_array($n)) {
        return null;
    }
    $id = filter_var($n['id'] ?? null, FILTER_VALIDATE_INT);
    if ($id === false || $id <= 0 || $id > ID_MAX) {
        return null;
    }
    $titre = trim((string) ($n['titre'] ?? ''));
    if ($titre === '') {
        return null;                                   // note sans titre = ignoree
    }
    $date = (string) ($n['date'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return null;                                   // date invalide = ignoree
    }
    $type = (string) ($n['type'] ?? 'autre');
    if (!in_array($type, TYPES_VALIDES, true)) {
        $type = 'autre';
    }
    $contenu = trim((string) ($n['contenu'] ?? ''));
    $hd = (string) ($n['heureDebut'] ?? '');
    $hf = (string) ($n['heureFin'] ?? '');
    $montant = $n['montant'] ?? '';
    if ($montant === '' || $montant === null || !is_numeric($montant) || (float) $montant < 0) {
        $montant = null;
    } else {
        $montant = (float) $montant;
    }
    return [
        ':id'          => $id,
        ':type'        => $type,
        ':titre'       => mb_substr($titre, 0, 255),
        ':contenu'     => $contenu === '' ? null : $contenu,
        ':date'        => $date,
        ':heure_debut' => preg_match('/^\d{2}:\d{2}$/', $hd) ? $hd : null,
        ':heure_fin'   => preg_match('/^\d{2}:\d{2}$/', $hf) ? $hf : null,
        ':montant'     => $montant,
        ':rappel'      => !empty($n['rappel']) ? 1 : 0,
        ':done'        => !empty($n['done']) ? 1 : 0,
    ];
}

/* Lit le corps JSON d'une requete POST (refuse tout le reste). */
function json_body(): array
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
        || stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== 0) {
        respond(['ok' => false, 'error' => 'Requete invalide.'], 400);
    }
    $input = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($input)) {
        respond(['ok' => false, 'error' => 'Format invalide.'], 400);
    }
    return $input;
}

const SQL_INSERT = 'INSERT INTO notes
        (id, type, titre, contenu, `date`, heure_debut, heure_fin, montant, rappel, done)
     VALUES
        (:id, :type, :titre, :contenu, :date, :heure_debut, :heure_fin, :montant, :rappel, :done)';

/* Acces protege par mot de passe. La simple lecture automatique (list)
   ne compte pas comme une activite : un onglet oublie se verrouille. */
if (!is_logged_in(($_GET['action'] ?? '') !== 'list')) {
    respond(['ok' => false, 'error' => 'Mot de passe requis', 'auth' => true], 401);
}
/* La session n'est plus utile : on la libere pour ne pas bloquer les autres requetes. */
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

try {
    $pdo    = db();
    $action = $_GET['action'] ?? '';

    /* ---- LIRE toutes les notes ------------------------------------------ */
    if ($action === 'list') {
        $rows = $pdo->query(
            'SELECT id, type, titre, contenu, `date`, heure_debut, heure_fin,
                    montant, rappel, done
             FROM notes
             ORDER BY `date` ASC, heure_debut ASC, id ASC'
        )->fetchAll();

        $notes = [];
        foreach ($rows as $r) {
            $notes[] = [
                'id'         => (int) $r['id'],
                'type'       => $r['type'],
                'titre'      => $r['titre'],
                'contenu'    => $r['contenu'] ?? '',
                'date'       => $r['date'],
                'heureDebut' => $r['heure_debut'] ?? '',
                'heureFin'   => $r['heure_fin'] ?? '',
                'montant'    => $r['montant'] === null ? '' : (float) $r['montant'],
                'rappel'     => (bool) $r['rappel'],
                'done'       => (bool) $r['done'],
            ];
        }
        respond(['ok' => true, 'notes' => $notes]);
    }

    /* ---- ENREGISTRER les changements (note par note) --------------------- */
    if ($action === 'sync') {
        $input  = json_body();
        $upsert = is_array($input['upsert'] ?? null) ? $input['upsert'] : [];
        $delete = is_array($input['delete'] ?? null) ? $input['delete'] : [];

        $pdo->beginTransaction();
        $ins = $pdo->prepare(SQL_INSERT . '
             ON DUPLICATE KEY UPDATE
                type = VALUES(type), titre = VALUES(titre), contenu = VALUES(contenu),
                `date` = VALUES(`date`), heure_debut = VALUES(heure_debut),
                heure_fin = VALUES(heure_fin), montant = VALUES(montant),
                rappel = VALUES(rappel), done = VALUES(done)');
        $del = $pdo->prepare('DELETE FROM notes WHERE id = ?');

        $saved = [];
        $deleted = [];
        foreach ($upsert as $n) {
            $row = clean_note($n);
            if ($row !== null) {
                $ins->execute($row);
                $saved[] = $row[':id'];
            }
        }
        foreach ($delete as $id) {
            $id = filter_var($id, FILTER_VALIDATE_INT);
            if ($id !== false && $id > 0 && $id <= ID_MAX) {
                $del->execute([$id]);
                $deleted[] = $id;
            }
        }
        $pdo->commit();
        respond(['ok' => true, 'saved' => $saved, 'deleted' => $deleted]);
    }

    /* ---- TOUT REMPLACER (utilise par "Reinitialiser") -------------------- */
    if ($action === 'save') {
        $input = json_body();
        if (!isset($input['notes']) || !is_array($input['notes'])) {
            respond(['ok' => false, 'error' => 'Format invalide : "notes" manquant.'], 400);
        }
        $pdo->beginTransaction();
        $pdo->exec('DELETE FROM notes');
        $ins = $pdo->prepare(SQL_INSERT);
        $seen = [];
        foreach ($input['notes'] as $n) {
            $row = clean_note($n);
            if ($row !== null && !isset($seen[$row[':id']])) {
                $ins->execute($row);
                $seen[$row[':id']] = true;
            }
        }
        $pdo->commit();
        respond(['ok' => true, 'count' => count($seen)]);
    }

    /* ---- Petit test de sante --------------------------------------------- */
    if ($action === 'ping') {
        respond(['ok' => true, 'message' => 'API operationnelle']);
    }

    respond(['ok' => false, 'error' => 'Action inconnue.'], 400);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[mcn-planning] ' . $e->getMessage());
    /* En ligne (mot de passe configure) on ne montre pas le detail technique. */
    $detail = (APP_PASSWORD_HASH === '' && acces_local()) ? ' : ' . $e->getMessage() : '.';
    respond(['ok' => false, 'error' => 'Erreur serveur' . $detail], 500);
}
