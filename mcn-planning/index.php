<?php
/* ============================================================================
   MCN DALOA — EMPLOI DU TEMPS
   index.php  —  Porte d'entree : demande le mot de passe, puis affiche
                 le planning (index.html).
   Sans mot de passe configure (XAMPP), le planning s'ouvre directement.
   ============================================================================ */

require __DIR__ . '/config.php';

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

/* Verrouiller (redemander le mot de passe) : index.php?deconnexion */
if (isset($_GET['deconnexion'])) {
    start_session();
    $_SESSION = [];
    session_destroy();
    header('Location: ./', true, 303);
    exit;
}

$erreur = '';

/* Anti-devinette : apres 5 mauvais mots de passe en 15 minutes, plus aucun
   essai n'est accepte pendant 15 minutes (pour tout le monde). Le compteur est
   dans .ht-essais.php : c'est du PHP qui s'arrete immediatement, donc meme
   ouvert depuis internet il n'affiche rien. */
const ESSAIS_MAX     = 5;
const ESSAIS_FENETRE = 15 * 60;
const ESSAIS_FICHIER = __DIR__ . '/.ht-essais.php';
const ESSAIS_ENTETE  = "<?php exit; ?>
";

function essais_recents(): array
{
    $liste = is_file(ESSAIS_FICHIER) ? json_decode(substr((string) @file_get_contents(ESSAIS_FICHIER), strlen(ESSAIS_ENTETE)), true) : [];
    $limite = time() - ESSAIS_FENETRE;
    return array_values(array_filter(is_array($liste) ? $liste : [], fn($t) => is_int($t) && $t > $limite));
}

if (APP_PASSWORD_HASH !== '' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    start_session();
    $essais = essais_recents();
    if (count($essais) >= ESSAIS_MAX) {
        $minutes = (int) ceil((min($essais) + ESSAIS_FENETRE - time()) / 60);
        $erreur = "Trop d'essais incorrects. Réessayez dans " . max(1, $minutes) . ' min.';
    } else {
        $mdp = (string) ($_POST['mot_de_passe'] ?? '');
        if ($mdp !== '' && password_verify($mdp, APP_PASSWORD_HASH)) {
            @unlink(ESSAIS_FICHIER);
            session_regenerate_id(true);
            $_SESSION['mcn_ok'] = true;
            $_SESSION['mcn_vu'] = time();
            header('Location: ./', true, 303);
            exit;
        }
        $essais[] = time();
        @file_put_contents(ESSAIS_FICHIER, ESSAIS_ENTETE . json_encode($essais), LOCK_EX);
        sleep(2);   // freine les essais en serie
        $reste = ESSAIS_MAX - count($essais);
        $erreur = $reste > 0
            ? 'Mot de passe incorrect.'
            : "Mot de passe incorrect. Trop d'essais : réessayez dans 15 min.";
    }
}

if (is_logged_in()) {
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-cache');
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    readfile(__DIR__ . '/index.html');
    exit;
}

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
$non_configure = APP_PASSWORD_HASH === '';   // en ligne sans mot de passe : on refuse
?><!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>MCN Daloa — Connexion</title>
  <link rel="icon" href="logo.png">
  <style>
    * { box-sizing: border-box; }
    body {
      margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 20px;
      font-family: 'Outfit', -apple-system, 'Segoe UI', sans-serif; color: #0f172a;
      background: radial-gradient(at 0% 0%, rgba(59,130,246,.12) 0, transparent 50%),
                  radial-gradient(at 100% 100%, rgba(252,96,9,.08) 0, transparent 50%), #fafbff;
    }
    .logo { display: block; width: 72px; height: 72px; margin: 0 auto 14px; border-radius: 50%; box-shadow: 0 6px 18px rgba(252,96,9,.18); }
    h1, p.sous { text-align: center; }
    .carte {
      width: 100%; max-width: 380px; background: #fff; border-radius: 16px; padding: 32px 28px;
      box-shadow: 0 20px 50px rgba(59,130,246,.12), 0 8px 16px rgba(15,23,42,.06);
      border: 1px solid #dbeafe;
    }
    h1 { margin: 0 0 4px; font-size: 1.5rem; font-family: 'Playfair Display', Georgia, serif; color: #1e40af; }
    p.sous { margin: 0 0 24px; color: #475569; font-size: .95rem; }
    label { display: block; font-weight: 600; font-size: .9rem; margin-bottom: 6px; }
    input[type=password] {
      width: 100%; padding: 12px 14px; font-size: 16px; border: 1px solid #bfdbfe; border-radius: 10px;
      outline: none; transition: box-shadow .15s, border-color .15s;
    }
    input[type=password]:focus { border-color: #3b82f6; box-shadow: 0 0 0 4px rgba(59,130,246,.15); }
    button {
      margin-top: 16px; width: 100%; padding: 12px; font-size: 1rem; font-weight: 600; color: #fff; border: 0;
      border-radius: 10px; cursor: pointer; background: linear-gradient(135deg, #3b82f6 0%, #2563eb 50%, #1d4ed8 100%);
    }
    button:hover { filter: brightness(1.05); }
    .erreur { margin: 0 0 16px; padding: 10px 12px; border-radius: 10px; background: #fef2f2; color: #b91c1c; font-size: .9rem; border: 1px solid #fecaca; }
  </style>
</head>
<body>
  <form class="carte" method="post" action="./">
    <img class="logo" src="logo.png" alt="">
    <h1>MCN Daloa</h1>
    <?php if ($non_configure): ?>
    <p class="sous">Emploi du temps</p>
    <p class="erreur" role="alert">Site non configuré : aucun mot de passe n'est défini.
      Ajoutez <code>APP_PASSWORD_HASH</code> dans <code>config.local.php</code> (voir le README).</p>
  </form>
</body>
</html>
<?php exit; endif; ?>
    <p class="sous">Emploi du temps — entrez le mot de passe</p>
    <?php if ($erreur !== ''): ?>
      <p class="erreur" role="alert"><?= htmlspecialchars($erreur, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <label for="mdp">Mot de passe</label>
    <input id="mdp" type="password" name="mot_de_passe" autocomplete="current-password" required autofocus>
    <button type="submit">Entrer</button>
  </form>
</body>
</html>
