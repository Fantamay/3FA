<?php
// Copier ce fichier en config/credentials.php et remplir vos valeurs.
// Ne jamais commiter config/credentials.php (voir .gitignore)

define('MAIL_HOST',     getenv('MAIL_HOST')     ?: 'smtp.gmail.com');
define('MAIL_USERNAME', getenv('MAIL_USERNAME') ?: 'votre.email@gmail.com');
define('MAIL_PASSWORD', getenv('MAIL_PASSWORD') ?: 'votre_mot_de_passe_application');
define('MAIL_FROM',     getenv('MAIL_USERNAME') ?: 'votre.email@gmail.com');
define('MAIL_PORT',     (int)(getenv('MAIL_PORT') ?: 587));
