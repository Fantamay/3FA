# 3FA — Application de gestion sécurisée de documents médicaux

Application web PHP illustrant la **triple authentification (3FA)** appliquée à un contexte de documents médicaux.

## Table des matières

- [Prérequis](#prérequis)
- [Installation locale (XAMPP)](#installation-locale-xampp)
- [Installation via Docker](#installation-via-docker)
- [Configuration](#configuration)
- [Lancer les tests](#lancer-les-tests)
- [Fonctionnalités](#fonctionnalités)
- [Architecture de sécurité](#architecture-de-sécurité)
- [SIEM & Monitoring](#siem--monitoring)

---

## Prérequis

### Sans Docker
- PHP >= 8.1
- MySQL >= 5.7
- Composer
- XAMPP (Windows) ou Apache + PHP sur Linux/Mac

### Avec Docker
- Docker >= 24
- Docker Compose >= 2.0

---

## Installation locale (XAMPP)

```bash
# 1. Cloner le dépôt
git clone https://github.com/Fantamay/3FA.git
cd 3FA

# 2. Installer les dépendances PHP
composer install

# 3. Créer le fichier de credentials (non versionné)
cp config/credentials.example.php config/credentials.php
# Éditer config/credentials.php avec vos identifiants SMTP Gmail

# 4. Créer la base de données dans phpMyAdmin
#    Nom : triple_auth
#    Puis importer :
mysql -u root triple_auth < config/create_tables.sql

# 5. Démarrer XAMPP (Apache + MySQL) et accéder à :
#    http://localhost/Triple/register.php
```

---

## Installation via Docker

```bash
# 1. Cloner le dépôt
git clone https://github.com/Fantamay/3FA.git
cd 3FA

# 2. Copier et configurer les credentials
cp config/credentials.example.php config/credentials.php
# Éditer config/credentials.php

# 3. Démarrer toute la stack en une commande
docker compose up -d

# 4. Accéder à l'application
#    http://localhost:8080/register.php
```

L'application sera disponible sur `http://localhost:8080`.
phpMyAdmin sera disponible sur `http://localhost:8081`.
La base de données est initialisée automatiquement au premier démarrage.

Pour arrêter :
```bash
docker compose down
```

---

## Configuration

### Fichier `config/credentials.php` (à créer, non versionné)

```php
<?php
define('MAIL_HOST',     'smtp.gmail.com');
define('MAIL_USERNAME', 'votre.email@gmail.com');
define('MAIL_PASSWORD', 'votre_mot_de_passe_application');
define('MAIL_FROM',     'votre.email@gmail.com');
define('MAIL_PORT',     587);
```

> Générer un **mot de passe d'application** Gmail : Compte Google → Sécurité → Mots de passe des applications.

### Variables d'environnement Docker (optionnel)

Créer un fichier `.env` à la racine :

```env
MYSQL_ROOT_PASSWORD=secret
MYSQL_DATABASE=triple_auth
MAIL_HOST=smtp.gmail.com
MAIL_USERNAME=votre.email@gmail.com
MAIL_PASSWORD=votre_mot_de_passe_application
MAIL_PORT=587
```

---

## Lancer les tests

```bash
# Installer PHPUnit (inclus dans composer)
composer install

# Lancer la suite de tests
./vendor/bin/phpunit --testdox

# Rapport de couverture HTML (nécessite Xdebug)
./vendor/bin/phpunit --coverage-html coverage/

# Rapport de couverture texte
./vendor/bin/phpunit --coverage-text
```

> Couverture cible : **>= 70 %** — Suite actuelle : 49 tests, 164 assertions.

---

## Fonctionnalités

### Triple authentification (3FA)

| Facteur | Mécanisme |
|---------|-----------|
| **Connaissance** | Mot de passe bcrypt |
| **Possession** | Code OTP 6 chiffres par email (TTL 10 min) |
| **Inhérence** | Code PIN chiffré ou simulation biométrique (WebAuthn) |

### Gestion de documents médicaux

- Upload de fichiers (PDF, JPG, PNG) avec nommage personnalisé
- Catégorisation : Ordonnances, Radios, Analyses, Vaccins, Comptes-rendus
- Liens de partage temporaires (token sécurisé)
- Téléchargement et suppression

### Sécurité

- Blocage du compte après 3 échecs de connexion
- Détection de nouvelle IP → OTP forcé
- Protection CSRF, XSS, injection SQL (PDO préparé)
- Upload restreint : types MIME validés, répertoire hors webroot

---

## Architecture de sécurité

```
register.php  ──►  login.php  ──►  otp.php  ──►  verify_pin.php  ──►  dashboard.php
                     │                                                        │
                     ▼                                                        ▼
              logs (IP, date)                                     audit_logs (actions)
```

### Tables principales

| Table | Rôle |
|-------|------|
| `users` | Comptes, hash mdp, PIN chiffré, blocage |
| `otp_codes` | Codes OTP avec expiration |
| `logs` | Historique connexions (IP, statut) |
| `audit_logs` | Journal d'audit (actions utilisateur) |
| `files` | Métadonnées des documents |
| `shared_links` | Liens de partage temporaires |
| `password_resets` | Tokens de réinitialisation |

---

## SIEM & Monitoring

Les événements de sécurité sont enregistrés dans la table `audit_logs` et configurés dans `config/siem/`.

### Événements tracés

| Événement | Niveau |
|-----------|--------|
| Connexion réussie | INFO |
| Échec de connexion | WARNING |
| Compte bloqué | CRITICAL |
| Nouvelle IP détectée | WARNING |
| Upload de fichier | INFO |
| Accès non autorisé | CRITICAL |

### Exporter les logs

```bash
# Via Docker
docker compose exec db mysql -u root -psecret triple_auth \
  -e "SELECT * FROM audit_logs ORDER BY date DESC LIMIT 500;" > siem_export.csv

# Via MySQL local
mysql -u root triple_auth -e \
  "SELECT * FROM audit_logs ORDER BY date DESC;" > siem_export.csv
```

Voir `config/siem/alert_rules.json` pour les règles d'alerte et `config/siem/dashboard.sql` pour les requêtes des dashboards.

---

## Structure du projet

```
3FA/
├── config/
│   ├── auth_check.php        # Vérification de session
│   ├── create_tables.sql     # Schéma BDD
│   ├── credentials.example.php
│   ├── db.php                # Connexion PDO
│   ├── helpers.php           # Fonctions utilitaires
│   ├── mailer.php            # Envoi OTP par email
│   ├── webauthn.php          # Simulation biométrique
│   └── siem/                 # Config SIEM & alertes
├── assets/                   # CSS, JS, images
├── uploads/                  # Documents médicaux (hors git)
├── tests/                    # Suite PHPUnit
├── docker-compose.yml
├── Dockerfile
└── *.php                     # Pages de l'application
```
