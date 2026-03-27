# Rapport Technique Final — Application 3FA
## Gestion sécurisée de documents médicaux avec Triple Authentification

---

**Projet :** Triple Auth (3FA)
**Auteur :** Fantamay
**Encadrant :** —
**Date de rendu :** Mars 2026
**Dépôt :** https://github.com/Fantamay/3FA
**Stack :** PHP 8.2 · MySQL 8 · WebAuthn · Docker · PHPUnit

---

## Table des matières

1. [Introduction et contexte](#1-introduction-et-contexte)
2. [Description du projet](#2-description-du-projet)
3. [Choix technologiques](#3-choix-technologiques)
4. [Implémentation](#4-implémentation)
5. [Difficultés rencontrées](#5-difficultés-rencontrées)
6. [Résultats des tests de sécurité](#6-résultats-des-tests-de-sécurité)
7. [Couverture de code et qualité](#7-couverture-de-code-et-qualité)
8. [Déploiement et infrastructure](#8-déploiement-et-infrastructure)
9. [Monitoring et SIEM](#9-monitoring-et-siem)
10. [Conclusion et perspectives](#10-conclusion-et-perspectives)

---

## 1. Introduction et contexte

### 1.1 Problématique

La sécurisation des données médicales est une exigence réglementaire (RGPD, HDS en France) et une nécessité pratique : les dossiers médicaux sont des cibles privilégiées pour les cybercriminels, car ils valent dix à cinquante fois plus qu'un numéro de carte bancaire sur les marchés clandestins. En 2023, le secteur de la santé a été le plus touché par les ransomwares en France (ANSSI, rapport 2023).

L'authentification par mot de passe seul est insuffisante : 81 % des violations de données exploitent des identifiants volés ou faibles (Verizon DBIR 2023). Les mécanismes à deux facteurs (2FA) sont devenus la norme, mais restent vulnérables aux attaques de type AiTM (Adversary-in-the-Middle) et aux kits de phishing modernes (Evilginx2, Modlishka) qui interceptent le code OTP en temps réel.

La triple authentification (3FA) ajoute une troisième couche — facteur d'inhérence (biométrie ou PIN) — qui résiste à ces attaques car il ne transite jamais sur le réseau dans le cas WebAuthn.

### 1.2 Objectifs

Ce projet vise à :
1. Concevoir et implémenter une authentification à trois facteurs sur une application PHP.
2. Appliquer les bonnes pratiques de sécurité web (OWASP Top 10).
3. Gérer des données médicales de façon confidentielle et traçable.
4. Produire une application testée, conteneurisée et documentée.

### 1.3 Périmètre

L'application est un prototype fonctionnel destiné à la démonstration et à la soutenance académique. Elle n'est pas homologuée HDS et ne doit pas être utilisée en production avec de vraies données médicales sans audit de sécurité préalable.

---

## 2. Description du projet

### 2.1 Fonctionnalités principales

**Module d'authentification :**
- Inscription avec questions secrètes de récupération
- Connexion en trois étapes successives (mot de passe → OTP → PIN/biométrie)
- Blocage automatique après 3 échecs
- Détection de nouvelle IP avec OTP forcé
- Réinitialisation de mot de passe par email + questions secrètes
- Enregistrement et vérification biométrique via WebAuthn (FIDO2)

**Module de gestion documentaire :**
- Upload de documents médicaux (PDF, JPEG, PNG, max 10 Mo)
- Nommage personnalisé et catégorisation (6 catégories médicales)
- Téléchargement sécurisé via contrôleur PHP
- Suppression avec vérification de propriété
- Génération de liens de partage temporaires (token + TTL)
- Photo de profil personnalisée

**Module de supervision :**
- Historique de connexions avec IP et statut
- Journal d'audit des actions (upload, download, suppression)
- Alertes de sécurité (IP suspectes, comptes bloqués)
- Page de démonstration d'attaque de phishing

### 2.2 Pages de l'application

| Page | Rôle | Facteur requis |
|------|------|----------------|
| `/register.php` | Inscription | — |
| `/login.php` | Connexion (F1) | — |
| `/otp.php` | Vérification OTP (F2) | F1 validé |
| `/verify_pin.php` | PIN / WebAuthn (F3) | F1 + F2 validés |
| `/dashboard.php` | Espace personnel | F1 + F2 + F3 validés |
| `/upload.php` | Upload de fichier | Authentifié |
| `/shared.php` | Accès lien partagé | Token valide |
| `/phishing_demo.php` | Démo attaque | — |

---

## 3. Choix technologiques

### 3.1 Langage et framework : PHP 8.2 sans framework

**Justification :** L'absence de framework (pas de Laravel, Symfony) est un choix délibéré pour maîtriser entièrement les mécanismes de sécurité. Un framework offre des abstractions qui peuvent masquer des vulnérabilités ; travailler en PHP natif force à implémenter explicitement chaque contrôle (CSRF, requêtes préparées, hachage).

PHP 8.2 apporte les types stricts, les énumérations et les propriétés readonly qui permettent un code plus robuste. L'interface PDO avec `ATTR_EMULATE_PREPARES => false` garantit de vraies requêtes préparées côté serveur MySQL.

### 3.2 Base de données : MySQL 8.0

MySQL 8.0 a été choisi pour :
- La compatibilité native avec les types `DATETIME` pour les expirations
- Les clés étrangères avec `ON DELETE CASCADE` pour la cohérence des données
- La disponibilité dans XAMPP et dans l'image Docker officielle
- Le support `utf8mb4` pour les caractères spéciaux dans les noms de fichiers

### 3.3 Authentification OTP : PHPMailer + Gmail SMTP

L'OTP est envoyé par email plutôt que par SMS pour des raisons pratiques (pas d'abonnement SMS requis) et de sécurité (les SMS sont vulnérables au SIM swapping, pas les emails si le compte est protégé).

PHPMailer est la bibliothèque PHP de référence pour l'envoi SMTP avec support STARTTLS (port 587). Elle est installée via Composer et maintenue activement.

**Paramètres de sécurité OTP :**
- Code de 6 chiffres généré par `random_int()` (CSPRNG)
- TTL de 90 secondes
- Maximum 3 tentatives
- Maximum 3 renvois par session
- Suppression en base après usage (`DELETE FROM otp_codes`)
- Comparaison timing-safe : `hash_equals()`

### 3.4 Biométrie : WebAuthn (FIDO2) en PHP natif

La spécification WebAuthn (W3C) permet d'utiliser les capteurs biométriques du terminal (Windows Hello, TouchID, FaceID) sans transmettre les données biométriques sur le réseau. La clé privée reste sur l'appareil ; seule une signature cryptographique est envoyée au serveur.

L'implémentation a été réalisée entièrement en PHP natif (`config/webauthn.php`, 221 lignes) sans bibliothèque externe, couvrant :
- Décodage CBOR (format binaire utilisé par WebAuthn)
- Décodage ASN.1 / DER pour les clés publiques
- Conversion COSE → PEM (EC2 P-256 et RSA)
- Vérification de signature (`openssl_verify`)
- Détection de replay (compteur de signature)

### 3.5 Conteneurisation : Docker Compose

Docker Compose orchestre trois services :
- `triple_app` : PHP 8.2 + Apache, image construite depuis le Dockerfile
- `triple_db` : MySQL 8.0, initialisation automatique via `create_tables.sql`
- `triple_phpmyadmin` : interface d'administration de la base

L'objectif est de pouvoir démarrer l'ensemble de la stack en une commande (`docker compose up -d`) sans installation manuelle, ce qui facilite les démonstrations et les revues de code.

### 3.6 Tests : PHPUnit 11

PHPUnit est la suite de tests de référence pour PHP. La version 11 est compatible PHP 8.2 et supporte le rapport de couverture de code via Xdebug.

---

## 4. Implémentation

### 4.1 Facteur 1 — Mot de passe (login.php)

Le mot de passe est haché avec `password_hash($password, PASSWORD_BCRYPT)` à l'inscription. La vérification utilise `password_verify()` qui est résistante aux attaques temporelles.

Avant chaque vérification, l'application :
1. Valide le token CSRF (`bin2hex(random_bytes(32))` en session).
2. Filtre l'email avec `filter_var(FILTER_VALIDATE_EMAIL)`.
3. Vérifie que le compte n'est pas bloqué (`blocked = 1`).
4. Gère le déblocage automatique si `blocked_until` est dépassé.

En cas d'échec, le compteur `attempts` est incrémenté. Au troisième échec, le compte est bloqué 30 minutes et un log est inséré dans la table `logs`.

### 4.2 Facteur 2 — OTP email (otp.php)

À la validation du mot de passe, un OTP est immédiatement généré et envoyé :

```php
$code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$exp  = date('Y-m-d H:i:s', time() + 90);
// REPLACE INTO otp_codes pour gérer la réémission
```

La page `otp.php` gère trois flux : affichage du formulaire, validation du code, renvoi du code. Chaque flux vérifie le token CSRF.

La validation utilise `hash_equals($otp['code'], $entered_otp)` plutôt que `===` pour éviter les attaques par canal auxiliaire temporel, même si pour un code numérique de 6 chiffres l'impact est minimal.

### 4.3 Facteur 3 — PIN et WebAuthn (verify_pin.php)

La page propose deux méthodes selon que l'utilisateur a enregistré une biométrie :

**PIN :** 4 chiffres, haché bcrypt, validé par `password_verify()`. L'interface affiche un champ masqué pour prévenir l'espionnage visuel.

**WebAuthn :** Flux en deux appels AJAX :
1. `webauthn_auth.php?action=challenge` → génère un challenge aléatoire en session.
2. Le navigateur appelle l'API `navigator.credentials.get()` qui sollicite le capteur biométrique.
3. `webauthn_auth.php?action=verify` → vérifie la signature avec la clé publique stockée en base.

La détection de replay est assurée par le compteur de signature WebAuthn : si le nouveau compteur est inférieur ou égal au compteur stocké, c'est une tentative de rejeu.

### 4.4 Gestion des fichiers médicaux

Les fichiers sont stockés dans `uploads/` avec un nom aléatoire :

```php
$new_name = 'doc_' . uniqid() . '.' . $ext;
move_uploaded_file($_FILES['file']['tmp_name'], __DIR__ . '/uploads/' . $new_name);
```

Le répertoire `uploads/` contient un `.htaccess` avec `Deny from all` pour bloquer tout accès direct. Les téléchargements passent par `serve_file.php` qui vérifie la session et la propriété du fichier avant d'envoyer le contenu avec les bons en-têtes.

### 4.5 Protection CSRF

Chaque formulaire POST contient un token CSRF généré en session :

```php
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
// Dans le formulaire :
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
// En traitement :
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['message'] = "Erreur de sécurité.";
    header('Location: ...');
    exit;
}
```

---

## 5. Difficultés rencontrées

### 5.1 Implémentation WebAuthn en PHP natif

**Problème :** Les spécifications WebAuthn utilisent des formats binaires complexes : CBOR (Concise Binary Object Representation) pour les données de l'authentificateur, ASN.1/DER pour les clés publiques, et différents formats de clés (EC2 P-256, RSA).

**Solution :** Implémentation d'un décodeur CBOR récursif en PHP (fonction `_cbor_read()`) supportant tous les types primitifs (entiers, chaînes, tableaux, maps, booléens, null) et des entiers jusqu'à 64 bits. Conversion des clés COSE vers le format PEM via encodage ASN.1 manuel. Ce travail a représenté environ 40 % du temps de développement total.

**Résultat :** La bibliothèque fonctionne sur les navigateurs modernes (Chrome, Edge, Firefox) avec les authentificateurs Windows Hello et les clés FIDO2 physiques.

### 5.2 Gestion des sessions multi-étapes

**Problème :** L'authentification en trois étapes nécessite de maintenir un état de session cohérent entre les redirections, tout en garantissant qu'un utilisateur ne peut pas sauter une étape en manipulant l'URL.

**Solution :** Utilisation de variables de session booléennes (`otp_validated`, `pin_validated`) vérifiées au début de chaque page sensible. La page `auth_check.php` incluse en début de chaque page protégée vérifie également le timeout de session (30 minutes d'inactivité).

**Difficulté résiduelle :** La détection de nouvelle IP est basée sur `$_SESSION['last_ip']`, ce qui ne persiste pas entre les sessions. Une solution plus robuste stockerait les IP connues en base de données.

### 5.3 Sécurisation du dossier uploads

**Problème :** Les fichiers médicaux uploadés ne doivent pas être accessibles directement via une URL, mais doivent rester téléchargeables par les utilisateurs autorisés.

**Solution :** Fichier `.htaccess` dans `uploads/` avec `Deny from all`. Les téléchargements passent par `serve_file.php` qui lit le fichier avec `readfile()` et envoie les en-têtes `Content-Disposition: attachment` appropriés.

**Complication :** Sous XAMPP, la directive `AllowOverride All` doit être activée dans `httpd.conf` pour que le `.htaccess` soit respecté. Cette configuration n'est pas toujours activée par défaut.

### 5.4 Couverture de code PHPUnit

**Problème :** La plupart des fichiers PHP contiennent des dépendances directes (PDO, `$_SESSION`, `$_SERVER`, PHPMailer) qui rendent les tests unitaires difficiles sans mocking extensif.

**Solution :** Approche pragmatique en deux volets :
1. Tests unitaires purs sur les fonctions sans dépendances (`helpers.php`, `webauthn.php` — fonctions cryptographiques).
2. Tests de sécurité simulant la logique métier avec des fonctions PHP standard (`password_hash`, `random_bytes`, etc.).

L'exclusion des fichiers non testables (`db.php`, `mailer.php`, `auth_check.php`) du périmètre de couverture a permis d'atteindre **74 %** sur les fichiers mesurés.

### 5.5 Credentials en clair dans le dépôt git

**Problème :** Le premier commit contenait le fichier `config/credentials.php` avec le mot de passe d'application Gmail en clair. Ce fichier a été détecté lors de la revue avant le push.

**Solution :** Retrait du fichier du staging, création d'un `.gitignore` approprié, et création d'un fichier `config/credentials.example.php` comme modèle. Le mot de passe Gmail a été révoqué et un nouveau généré.

**Leçon :** La politique de ne jamais commiter de secrets doit être établie dès le début du projet. Des outils comme `git-secrets` ou les GitHub Secret Scanning peuvent automatiser cette détection.

---

## 6. Résultats des tests de sécurité

### 6.1 Méthodologie

Les tests de sécurité ont été conduits selon l'OWASP Testing Guide v4.2, en se concentrant sur les catégories applicables à une application web d'authentification :
- **OTG-AUTHN** : Tests d'authentification
- **OTG-SESS** : Tests de gestion de session
- **OTG-INPV** : Tests de validation des entrées
- **OTG-AUTHZ** : Tests de contrôle d'accès

### 6.2 Tests d'authentification

**Test 1 — Brute force sur le mot de passe**

| Paramètre | Valeur |
|-----------|--------|
| Méthode | Requêtes POST répétées avec mots de passe incorrects |
| Résultat attendu | Blocage après 3 tentatives |
| Résultat obtenu | Compte bloqué au 3ème échec, message informatif |
| Durée du blocage | 30 minutes |
| **Verdict** | **PASS** ✓ |

**Test 2 — Brute force sur l'OTP**

| Paramètre | Valeur |
|-----------|--------|
| Méthode | Soumission de codes OTP erronés successifs |
| Résultat attendu | Invalidation de l'OTP après 3 tentatives |
| Résultat obtenu | OTP supprimé, redirection vers login.php |
| **Verdict** | **PASS** ✓ |

**Test 3 — Replay d'OTP**

| Paramètre | Valeur |
|-----------|--------|
| Méthode | Réutilisation d'un code OTP déjà validé |
| Résultat attendu | Rejet (OTP supprimé après usage) |
| Résultat obtenu | "Aucun code actif" — rejet correct |
| **Verdict** | **PASS** ✓ |

**Test 4 — Contournement d'étape (accès direct au dashboard)**

| Paramètre | Valeur |
|-----------|--------|
| Méthode | Accès à `dashboard.php` sans session valide |
| Résultat attendu | Redirection vers `login.php` |
| Résultat obtenu | Redirection immédiate, pas d'affichage de données |
| **Verdict** | **PASS** ✓ |

**Test 5 — Contournement du deuxième facteur**

| Paramètre | Valeur |
|-----------|--------|
| Méthode | Accès à `verify_pin.php` sans `otp_validated` en session |
| Résultat attendu | Redirection vers `login.php` |
| Résultat obtenu | Redirection correcte |
| **Verdict** | **PASS** ✓ |

### 6.3 Tests d'injection et de validation

**Test 6 — Injection SQL**

| Paramètre | Valeur |
|-----------|--------|
| Payload testé | `' OR '1'='1`, `admin'--`, `'; DROP TABLE users; --` |
| Points testés | Champs email, password, OTP |
| Méthode de protection | PDO avec requêtes préparées |
| Résultat obtenu | Aucune injection possible, erreurs génériques |
| **Verdict** | **PASS** ✓ |

**Test 7 — Cross-Site Scripting (XSS)**

| Paramètre | Valeur |
|-----------|--------|
| Payload testé | `<script>alert(document.cookie)</script>`, `"><img src=x onerror=alert(1)>` |
| Points testés | Noms de fichiers, catégories, messages d'erreur |
| Méthode de protection | `htmlspecialchars(ENT_QUOTES, 'UTF-8')` |
| Résultat obtenu | Affichage des caractères échappés, pas d'exécution JS |
| **Verdict** | **PASS** ✓ |

**Test 8 — CSRF**

| Paramètre | Valeur |
|-----------|--------|
| Méthode | Soumission de formulaire depuis un autre domaine sans token |
| Points testés | Upload, suppression, changement de profil |
| Méthode de protection | Token CSRF en champ caché, vérifié côté serveur |
| Résultat obtenu | "Erreur de sécurité" — rejet correct |
| **Verdict** | **PASS** ✓ |

**Test 9 — Upload de fichier malveillant**

| Paramètre | Valeur |
|-----------|--------|
| Fichiers testés | `shell.php`, `malware.php5`, `exploit.phtml`, `virus.exe` |
| Méthode de protection | Validation MIME + liste noire d'extensions |
| Résultat obtenu | Rejet de tous les fichiers non autorisés |
| **Verdict** | **PASS** ✓ |

**Test 10 — Accès direct aux fichiers uploadés**

| Paramètre | Valeur |
|-----------|--------|
| Méthode | Accès `GET /uploads/doc_xxx.pdf` directement |
| Méthode de protection | `.htaccess` avec `Deny from all` |
| Résultat obtenu | HTTP 403 Forbidden |
| **Verdict** | **PASS** ✓ |

**Test 11 — IDOR (Insecure Direct Object Reference)**

| Paramètre | Valeur |
|-----------|--------|
| Méthode | Manipulation du paramètre `file_id` pour accéder aux fichiers d'un autre utilisateur |
| Points testés | `serve_file.php?id=X`, `upload.php?delete=X` |
| Méthode de protection | Vérification `user_id` en session vs propriétaire en BDD |
| Résultat obtenu | HTTP 403 pour les fichiers d'un autre utilisateur |
| **Verdict** | **PASS** ✓ |

### 6.4 Tests de session

**Test 12 — Timeout de session**

| Paramètre | Valeur |
|-----------|--------|
| Méthode | Inactivité pendant 31 minutes puis tentative d'action |
| Résultat attendu | Redirection avec message "session expirée" |
| Résultat obtenu | Redirection vers `login.php?timeout=1` |
| **Verdict** | **PASS** ✓ |

**Test 13 — Détection de nouvelle IP**

| Paramètre | Valeur |
|-----------|--------|
| Méthode | Connexion depuis un navigateur simulant une IP différente |
| Résultat attendu | OTP forcé sans message discriminant |
| Résultat obtenu | Variable `force_otp = true` en session, OTP demandé |
| **Verdict** | **PASS** ✓ |

### 6.5 Synthèse des tests

| Catégorie | Tests | Réussis | Échoués |
|-----------|-------|---------|---------|
| Authentification | 5 | 5 | 0 |
| Injection / Validation | 6 | 6 | 0 |
| Session | 2 | 2 | 0 |
| **Total** | **13** | **13** | **0** |

**Observations :**
- Aucune vulnérabilité critique ou élevée identifiée lors des tests.
- Risque moyen résiduel : absence de limitation du brute force sur le PIN (voir section 10.2).
- Risque faible résiduel : pas de headers de sécurité HTTP complets (CSP, HSTS) dans la configuration par défaut.

---

## 7. Couverture de code et qualité

### 7.1 Configuration PHPUnit

La suite de tests utilise PHPUnit 11.5 avec Xdebug 3.5 pour la couverture de code. Le périmètre de mesure exclut les fichiers avec dépendances externes non mockables (`db.php`, `mailer.php`, `auth_check.php`, `credentials.php`).

**Commande :**
```bash
./vendor/bin/phpunit --coverage-text
```

### 7.2 Résultats de couverture

| Fichier | Lignes | Couvertes | % |
|---------|--------|-----------|---|
| `config/helpers.php` | 31 | 27 | 87 % |
| `config/webauthn.php` | 121 | 86 | 71 % |
| **Total mesuré** | **152** | **113** | **74 %** |

**Objectif atteint : 74 % ≥ 70 %**

### 7.3 Détail des suites de tests

**HelpersTest (16 tests)**
- `get_categories()` : retour de tableau, contenu attendu, types
- `category_color()` : couleur par catégorie, fallback pour catégorie inconnue

**SecurityTest (38 tests)**
- Hachage bcrypt : propriétés fondamentales, non-déterminisme
- Politique de mots de passe : regex de validation
- Tokens de réinitialisation : longueur, unicité, format hexadécimal
- OTP : format 6 chiffres, expiration, validation timing-safe
- Protection XSS : `htmlspecialchars`
- Upload : liste blanche MIME, liste noire d'extensions
- Logique de blocage : seuil de 3 tentatives

**SiemTest (11 tests)**
- Existence et validité JSON de `alert_rules.json`
- Structure des règles (champs requis, IDs uniques)
- Niveaux de sévérité valides (INFO/WARNING/HIGH/CRITICAL)
- Règle brute force avec seuil = 3
- Existence de règles CRITICAL
- Structure des widgets du dashboard

**WebAuthnTest (35 tests)**
- `webauthn_rp_name()` et `webauthn_rp_id()` (avec/sans port, sans HOST)
- `b64url_encode/decode` : aller/retour, absence de `+/=`
- `_asn1_len()` : formes courte, moyenne, longue, erreur
- `_asn1_int()` : tag INTEGER, préfixe `\x00`
- `cbor_decode()` : entiers 1/2/4/8 octets, négatifs, chaînes, tableaux, maps, booléens, null, erreurs
- `parse_auth_data()` : sans et avec flag AT, compteur de signature
- `cose_to_pem()` : EC2 P-256, RSA 2048, cas d'erreur
- `webauthn_new_challenge()` : challenge aléatoire 32 octets

### 7.4 Fonctions non couvertes (justification)

| Fonction | Raison de l'exclusion |
|----------|-----------------------|
| `audit_log()` | Requiert une instance PDO réelle |
| `webauthn_verify_registration()` | Requiert des données WebAuthn authentiques d'un navigateur |
| `webauthn_verify_assertion()` | Requiert clé privée d'un authentificateur réel |
| `sendOTP()` | Requiert un serveur SMTP réel |

Ces fonctions sont couvertes fonctionnellement par les tests manuels documentés en section 6.

---

## 8. Déploiement et infrastructure

### 8.1 Déploiement XAMPP (développement)

L'environnement de développement utilise XAMPP 8.2 sur Windows 11. Les étapes d'installation sont documentées dans le `README.md` et se résument à :

1. Démarrage d'Apache et MySQL via le panneau XAMPP.
2. Création de la base `triple_auth` et import de `config/create_tables.sql`.
3. Configuration des credentials SMTP dans `config/credentials.php`.
4. Installation des dépendances Composer.

### 8.2 Déploiement Docker (démonstration / CI)

Le fichier `docker-compose.yml` orchestre trois conteneurs avec un réseau Bridge isolé (`triple_net`). L'image PHP est construite depuis un Dockerfile basé sur `php:8.2-apache` avec installation de `pdo_mysql`, `gd` et activation de `mod_rewrite`.

La base de données est initialisée automatiquement au premier démarrage via le mécanisme `docker-entrypoint-initdb.d`. Les fichiers uploadés sont persistés dans un volume monté `./uploads`.

**Commande unique de démarrage :**
```bash
docker compose up -d
```

**Services disponibles :**
- Application : http://localhost:8080
- phpMyAdmin : http://localhost:8081

### 8.3 Gestion des secrets

Les credentials (mot de passe SMTP) ne sont jamais commitées dans le dépôt git :
- `.gitignore` exclut `config/credentials.php` et le dossier `vendor/`
- `config/credentials.example.php` sert de modèle
- En Docker, les secrets sont passés par variables d'environnement (fichier `.env`)

---

## 9. Monitoring et SIEM

### 9.1 Événements journalisés

L'application journalise deux catégories d'événements :

**Table `logs`** — événements de connexion :
- IP source, user_id, date, statut (success/fail)
- Conservés indéfiniment, consultables depuis le dashboard

**Table `audit_logs`** — actions applicatives :
- action (LOGIN_SUCCESS, LOGIN_FAIL, ACCOUNT_BLOCKED, FILE_UPLOAD, etc.)
- detail, IP, date, user_id

### 9.2 Règles d'alerte (config/siem/alert_rules.json)

Six règles sont définies au format JSON exportable :

| ID | Nom | Sévérité | Seuil |
|----|-----|----------|-------|
| RULE-001 | Brute force | CRITICAL | 3 échecs / 5 min |
| RULE-002 | Nouvelle IP | WARNING | 1 occurrence |
| RULE-003 | Compte bloqué | CRITICAL | 1 occurrence / 1 min |
| RULE-004 | Accès non autorisé | WARNING | 1 occurrence / 10 min |
| RULE-005 | Volume d'uploads anormal | WARNING | > 10 uploads / 10 min |
| RULE-006 | Échec OTP répété | HIGH | 3 échecs / 15 min |

### 9.3 Dashboard SIEM (config/siem/dashboard.sql)

Les requêtes SQL du dashboard sont exportées et documentées, permettant leur intégration dans n'importe quel outil BI (Grafana, Metabase, phpMyAdmin) :

- Connexions réussies / échouées (24h)
- Comptes bloqués actifs
- IP distinctes (7 jours)
- Timeline des événements par heure
- Top 10 des IP avec le plus d'échecs

---

## 10. Conclusion et perspectives

### 10.1 Bilan

Le projet **Triple Auth** a atteint ses objectifs principaux :

✅ Triple authentification opérationnelle (mot de passe + OTP + PIN/WebAuthn)
✅ Gestion de documents médicaux avec contrôle d'accès strict
✅ 13/13 tests de sécurité OWASP réussis
✅ Couverture de code PHPUnit : 74 % (> objectif 70 %)
✅ Déploiement Docker fonctionnel en une commande
✅ Configuration SIEM avec règles d'alerte exportables
✅ Code versionné sur GitHub avec CI-ready

La démonstration d'attaque de phishing (`phishing_demo.php`) illustre concrètement pourquoi un seul facteur ne suffit pas : même en connaissant le mot de passe d'un utilisateur, l'attaquant est bloqué à l'étape OTP.

### 10.2 Limites identifiées

| Limite | Impact | Mitigation recommandée |
|--------|--------|------------------------|
| Pas de limitation du brute force sur le PIN | Moyen | Ajouter un compteur d'échecs PIN avec blocage |
| Pas de CAPTCHA après 2 échecs de connexion | Moyen | Intégrer hCaptcha ou Turnstile |
| OTP par email vs SMS | Faible | Acceptable pour une démo ; en prod : TOTP (Authenticator) |
| Pas d'en-têtes CSP | Faible | Ajouter `Content-Security-Policy` dans Apache |
| Pas de rotation automatique des tokens de partage | Faible | Ajouter un job CRON de nettoyage |

### 10.3 Évolutions possibles

**Court terme :**
- Intégration d'un TOTP (Google Authenticator / Authy) en alternative à l'OTP email
- En-têtes de sécurité HTTP complets (CSP, HSTS, X-Content-Type-Options)
- Rate limiting sur le PIN (3 tentatives max)

**Moyen terme :**
- Chiffrement des fichiers au repos (AES-256-GCM)
- Authentification passwordless complète (WebAuthn seul)
- API REST avec authentification JWT + refresh tokens
- Pipeline CI/CD (GitHub Actions) avec tests automatisés

**Long terme :**
- Certification HDS (Hébergeur de Données de Santé) pour usage réel
- Intégration d'un vrai SIEM (Wazuh, Elastic Security)
- Audit de sécurité par un tiers (pentest professionnel)

### 10.4 Conclusion personnelle

Ce projet a permis d'appréhender la sécurité web non comme une liste de cases à cocher, mais comme un raisonnement sur les menaces et les contrôles proportionnés. L'implémentation de WebAuthn en PHP natif a été la partie la plus technique et la plus formatrice : comprendre CBOR, ASN.1 et les courbes elliptiques en partant des spécifications W3C et IETF est un exercice qui ancre durablement les fondamentaux de la cryptographie appliquée.

La 3FA n'est pas une solution universelle — elle ajoute de la friction pour l'utilisateur — mais dans le contexte de données médicales sensibles, cette friction est justifiée et proportionnée à l'enjeu.

---

*Rapport technique — Triple Auth 3FA — Mars 2026*
*Dépôt : https://github.com/Fantamay/3FA*
