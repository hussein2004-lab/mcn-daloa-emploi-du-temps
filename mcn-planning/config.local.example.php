<?php
/* ============================================================================
   MCN DALOA — EMPLOI DU TEMPS
   config.local.example.php  —  MODELE de configuration pour la mise en ligne
   ----------------------------------------------------------------------------
   1. Copiez ce fichier sous le nom  config.local.php  (dans ce meme dossier)
      ou  ../mcn-config.php  (un dossier au-dessus du site : encore mieux,
      s'il est accessible chez votre hebergeur).
   2. Remplacez les valeurs ci-dessous par celles de votre hebergeur.
   3. Ne publiez JAMAIS config.local.php (il est deja exclu par .gitignore).

   APP_PASSWORD_HASH = l'empreinte du mot de passe d'entree du planning
   (jamais le mot de passe lui-meme). Pour la fabriquer :
     Windows (XAMPP) :  C:\xampp\php\php.exe -r "echo password_hash('VotreMotDePasse', PASSWORD_DEFAULT);"
     Mac / Linux     :  php -r "echo password_hash('VotreMotDePasse', PASSWORD_DEFAULT);"
   Choisissez un mot de passe d'au moins 10 caracteres.
   ============================================================================ */
return [
    'DB_HOST' => 'sqlXXX.exemple-hebergeur.com',   // "MySQL Hostname" (souvent 'localhost')
    'DB_NAME' => 'nom_de_la_base',                 // nom complet, avec le prefixe de l'hebergeur
    'DB_USER' => 'utilisateur_mysql',
    'DB_PASS' => 'mot-de-passe-mysql',
    'DB_PORT' => 3306,
    'APP_PASSWORD_HASH' => '$2y$10$remplacez-par-votre-empreinte',
];
