<?php
/* ============================================================================
   MCN DALOA — EMPLOI DU TEMPS
   config.php  —  Connexion a la base de donnees MySQL + mot de passe d'acces
   ----------------------------------------------------------------------------
   NE MODIFIEZ PAS ce fichier pour l'hebergement en ligne.
   Les vrais identifiants sont lus, dans cet ordre, depuis :
     1. ../mcn-config.php      (un dossier AU-DESSUS du site : jamais visible
                                sur internet — recommande chez LWS)
     2. ./config.local.php     (meme dossier, protege par .htaccess)
   Si aucun des deux n'existe, on garde les valeurs XAMPP ci-dessous
   (utilisateur "root", sans mot de passe, pas de mot de passe d'acces).
   Modele : config.local.example.php
   ============================================================================ */

$__cfg = [
    'DB_HOST'       => '127.0.0.1',
    'DB_NAME'       => 'mcn_daloa',
    'DB_USER'       => 'root',
    'DB_PASS'       => '',
    'DB_PORT'       => 3306,
    /* Empreinte (password_hash) du mot de passe d'acces au planning.
       Vide = pas de mot de passe (usage local sous XAMPP uniquement). */
    'APP_PASSWORD_HASH' => '',
];

foreach ([dirname(__DIR__) . '/mcn-config.php', __DIR__ . '/config.local.php'] as $__f) {
    if (is_file($__f)) {
        $__local = require $__f;
        if (is_array($__local)) {
            $__cfg = array_merge($__cfg, $__local);
        }
        break;
    }
}

define('DB_HOST', $__cfg['DB_HOST']);
define('DB_NAME', $__cfg['DB_NAME']);
define('DB_USER', $__cfg['DB_USER']);
define('DB_PASS', $__cfg['DB_PASS']);
define('DB_PORT', (int) $__cfg['DB_PORT']);
define('APP_PASSWORD_HASH', (string) $__cfg['APP_PASSWORD_HASH']);
unset($__cfg, $__f, $__local);

/* Fuseau horaire (Cote d'Ivoire / Daloa) — pour des dates coherentes. */
date_default_timezone_set('Africa/Abidjan');

/* ---- Connexion (PDO) ------------------------------------------------------ */
function db(): PDO
{
    static $pdo = null;            // on ne se connecte qu'une seule fois par requete
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        DB_HOST, DB_PORT, DB_NAME
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // erreurs = exceptions
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // resultats sous forme de tableau
        PDO::ATTR_EMULATE_PREPARES   => false,                   // vraies requetes preparees
    ];

    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

    /* Cree la table au premier lancement (rien a importer a la main). */
    $pdo->exec("CREATE TABLE IF NOT EXISTS `notes` (
        `id`          INT UNSIGNED NOT NULL,
        `type`        VARCHAR(20)  NOT NULL DEFAULT 'commande',
        `titre`       VARCHAR(255) NOT NULL,
        `contenu`     TEXT         NULL,
        `date`        DATE         NOT NULL,
        `heure_debut` VARCHAR(5)   NULL,
        `heure_fin`   VARCHAR(5)   NULL,
        `montant`     DECIMAL(14,2) NULL,
        `rappel`      TINYINT(1)   NOT NULL DEFAULT 0,
        `done`        TINYINT(1)   NOT NULL DEFAULT 0,
        `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_date` (`date`),
        KEY `idx_type` (`type`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    return $pdo;
}

/* ---- Session / mot de passe d'acces ---------------------------------------
   Un seul mot de passe commun, pas de comptes. Il est redemande a chaque
   nouvelle visite : le "laissez-passer" disparait quand le navigateur se
   ferme, ou apres 2 heures sans aucune activite. */
const SESSION_INACTIVITE = 2 * 60 * 60;   // 2 heures, en secondes

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name('mcn_session');
    session_set_cookie_params([
        'lifetime' => 0,          // cookie de session : efface a la fermeture du navigateur
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    ini_set('session.gc_maxlifetime', (string) SESSION_INACTIVITE);
    session_start();
}

/* true si la visite vient de l'ordinateur lui-meme (XAMPP, http://localhost).
   Sans mot de passe configure, le planning n'est ouvert QUE dans ce cas :
   un site mis en ligne par erreur sans mot de passe reste ferme. */
function acces_local(): bool
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return in_array($ip, ['127.0.0.1', '::1'], true)
        && empty($_SERVER['HTTP_X_FORWARDED_FOR']);
}

/* true si l'acces est autorise (pas de mot de passe configure, ou mot de passe
   deja donne pendant cette visite). $activite = false pour les simples
   rafraichissements automatiques, qui ne prolongent pas la visite. */
function is_logged_in(bool $activite = true): bool
{
    if (APP_PASSWORD_HASH === '') {
        return acces_local();   // sans mot de passe : seulement sur l'ordinateur lui-meme
    }
    start_session();
    if (empty($_SESSION['mcn_ok'])) {
        return false;
    }
    $now = time();
    if ($now - (int) ($_SESSION['mcn_vu'] ?? 0) > SESSION_INACTIVITE) {
        $_SESSION = [];
        return false;
    }
    if ($activite) {
        $_SESSION['mcn_vu'] = $now;
    }
    return true;
}
