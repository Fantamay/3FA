# Document d'Architecture — Application 3FA
## Gestion sécurisée de documents médicaux avec Triple Authentification

---

**Projet :** Triple Auth (3FA)
**Version :** 1.0
**Date :** Mars 2026
**Technologie :** PHP 8.2 · MySQL 8 · WebAuthn · PHPMailer

---

## Table des matières

1. [Vue d'ensemble du système](#1-vue-densemble-du-système)
2. [Diagramme de cas d'utilisation (Use Case)](#2-diagramme-de-cas-dutilisation)
3. [Architecture applicative](#3-architecture-applicative)
4. [Flux d'authentification triple (3FA)](#4-flux-dauthentification-triple-3fa)
5. [Diagrammes de séquence](#5-diagrammes-de-séquence)
6. [Modèle de données](#6-modèle-de-données)
7. [Architecture de déploiement](#7-architecture-de-déploiement)
8. [Modèle de menaces STRIDE](#8-modèle-de-menaces-stride)
9. [Contrôles de sécurité implémentés](#9-contrôles-de-sécurité-implémentés)

---

## 1. Vue d'ensemble du système

L'application **Triple Auth** est une plateforme web de gestion de documents médicaux sécurisée par une authentification à trois facteurs (3FA). Elle répond à la problématique suivante : comment protéger des données médicales sensibles contre des attaques modernes (phishing, credential stuffing, vol de session) en imposant trois preuves d'identité successives et indépendantes ?

### 1.1 Les trois facteurs

| Facteur | Catégorie | Mécanisme | Résistance principale |
|---------|-----------|-----------|----------------------|
| **F1 — Mot de passe** | Connaissance (*something you know*) | bcrypt (cost 12), validation CSRF | Brute force, injection SQL |
| **F2 — Code OTP** | Possession (*something you have*) | TOTP 6 chiffres, TTL 90s, email SMTP/TLS | Phishing, replay |
| **F3 — PIN / Biométrie** | Inhérence (*something you are*) | PIN bcrypt ou WebAuthn (FIDO2) | Shoulder surfing, vol de terminal |

### 1.2 Principes directeurs

- **Défense en profondeur** : chaque couche est indépendante ; la compromission d'un facteur ne suffit pas.
- **Principle of Least Privilege** : les fichiers médicaux sont hors du webroot ; leur accès passe par un contrôleur PHP.
- **Fail-secure** : tout échec de vérification redirige vers la connexion, sans information discriminante.
- **Auditabilité** : chaque action sensible est journalisée dans `audit_logs`.

---

## 2. Diagramme de cas d'utilisation

```
┌─────────────────────────────────────────────────────────────┐
│                    Système Triple Auth                      │
│                                                             │
│   ┌─────────────────────────────────────────────────────┐   │
│   │          Gestion du compte                          │   │
│   │  ○ S'inscrire                                       │   │
│   │  ○ Se connecter (3FA)                               │   │
│   │  ○ Réinitialiser le mot de passe                    │   │
│   │  ○ Enregistrer la biométrie (WebAuthn)              │   │
│   └─────────────────────────────────────────────────────┘   │
│                                                             │
│   ┌─────────────────────────────────────────────────────┐   │
│   │          Gestion des documents                      │   │
│   │  ○ Uploader un document médical                     │   │
│   │  ○ Télécharger un document                          │   │
│   │  ○ Supprimer un document                            │   │
│   │  ○ Catégoriser un document                          │   │
│   │  ○ Générer un lien de partage temporaire            │   │
│   └─────────────────────────────────────────────────────┘   │
│                                                             │
│   ┌─────────────────────────────────────────────────────┐   │
│   │          Sécurité & Supervision                     │   │
│   │  ○ Consulter l'historique de connexion              │   │
│   │  ○ Voir les alertes de sécurité                     │   │
│   │  ○ Changer la photo de profil                       │   │
│   └─────────────────────────────────────────────────────┘   │
│                                                             │
└─────────────────────────────────────────────────────────────┘
          ▲
     [Utilisateur]
```

**Acteurs :**
- **Utilisateur** : patient ou professionnel de santé disposant d'un compte.
- **Système de messagerie (SMTP)** : acteur externe qui délivre le code OTP.
- **Authentificateur WebAuthn** : capteur biométrique du terminal (Windows Hello, TouchID).

---

## 3. Architecture applicative

### 3.1 Vue en couches

```
┌─────────────────────────────────────────────────────────────┐
│                     COUCHE PRÉSENTATION                     │
│   register.php  login.php  otp.php  verify_pin.php          │
│   dashboard.php  upload.php  shared.php  phishing_demo.php  │
│                       assets/ (CSS, JS)                     │
├─────────────────────────────────────────────────────────────┤
│                     COUCHE CONTRÔLE                         │
│   config/auth_check.php   (vérification de session)         │
│   config/mailer.php       (envoi OTP via PHPMailer/SMTP)    │
│   config/webauthn.php     (FIDO2 : CBOR, ASN.1, PEM)        │
│   config/helpers.php      (audit_log, catégories)           │
├─────────────────────────────────────────────────────────────┤
│                     COUCHE DONNÉES                          │
│   config/db.php           (PDO, requêtes préparées)         │
│   MySQL 8.0               (triple_auth database)            │
│   uploads/                (fichiers hors webroot)           │
├─────────────────────────────────────────────────────────────┤
│                  COUCHE INFRASTRUCTURE                      │
│   Apache 2.4 + mod_rewrite  ·  PHP 8.2  ·  Docker Compose  │
└─────────────────────────────────────────────────────────────┘
```

### 3.2 Structure des fichiers

```
3FA/
├── index.php                 # Redirection → login.php
├── register.php              # Inscription + questions secrètes
├── login.php                 # F1 : email + mot de passe
├── questionnaire.php         # Questions secrètes (récupération)
├── otp.php                   # F2 : code OTP par email
├── verify_pin.php            # F3 : PIN ou WebAuthn
├── dashboard.php             # Espace personnel (fichiers, logs)
├── upload.php                # Upload de documents médicaux
├── serve_file.php            # Téléchargement sécurisé (hors webroot)
├── serve_profile.php         # Photo de profil sécurisée
├── share_file.php            # Génération de liens de partage
├── shared.php                # Accès aux liens de partage
├── forgot_password.php       # Demande de réinitialisation
├── reset_password.php        # Réinitialisation avec token
├── logout.php                # Invalidation de session
├── webauthn_register.php     # API WebAuthn : enregistrement
├── webauthn_auth.php         # API WebAuthn : vérification
├── phishing_demo.php         # Démonstration d'attaque (soutenance)
├── config/
│   ├── db.php                # Connexion PDO
│   ├── auth_check.php        # Timeout de session (30 min)
│   ├── mailer.php            # PHPMailer SMTP/TLS
│   ├── webauthn.php          # Logique FIDO2 pure PHP
│   ├── helpers.php           # Fonctions utilitaires
│   ├── credentials.php       # Secrets SMTP (hors git)
│   └── siem/                 # Règles SIEM et dashboards
├── tests/                    # Suite PHPUnit (100 tests)
├── uploads/                  # Documents médicaux (protégés)
├── Dockerfile
└── docker-compose.yml
```

---

## 4. Flux d'authentification triple (3FA)

### 4.1 Vue d'ensemble du flux

```
  [Navigateur]              [Serveur PHP]            [MySQL]     [SMTP]
       │                         │                      │          │
       │── POST /login ─────────►│                      │          │
       │   email + password      │── SELECT users ──────►│          │
       │   csrf_token            │◄── user record ───────│          │
       │                         │                      │          │
       │                         │ [Vérifications F1]   │          │
       │                         │ • CSRF token         │          │
       │                         │ • email valide       │          │
       │                         │ • compte non bloqué  │          │
       │                         │ • password_verify()  │          │
       │                         │                      │          │
       │◄── Redirect /otp ───────│── INSERT otp_codes ──►│          │
       │                         │── sendOTP() ─────────────────────►│
       │                         │                                   │
       │── POST /otp ────────────►│                      │          │
       │   code + csrf           │── SELECT otp_codes ──►│          │
       │                         │ [Vérifications F2]   │          │
       │                         │ • CSRF token         │          │
       │                         │ • expiration < now   │          │
       │                         │ • attempts < 3       │          │
       │                         │ • hash_equals()      │          │
       │                         │                      │          │
       │◄── Redirect /verify_pin─│── DELETE otp_codes ──►│          │
       │                         │                      │          │
       │── POST /verify_pin ─────►│                      │          │
       │   pin + csrf            │── SELECT pin hash ───►│          │
       │   [ou WebAuthn]         │ [Vérifications F3]   │          │
       │                         │ • CSRF token         │          │
       │                         │ • PIN: 4 chiffres    │          │
       │                         │ • password_verify()  │          │
       │                         │   ou openssl_verify  │          │
       │                         │                      │          │
       │◄── Redirect /dashboard──│── INSERT audit_logs ─►│          │
       │                         │                      │          │
```

### 4.2 États de session

```
  [Aucune session]
        │
        ▼  POST login OK
  [secret_pending = true]         ← F1 validé
  [user_id, email, last_ip]
        │
        ▼  POST otp OK
  [otp_validated = true]          ← F2 validé
        │
        ▼  POST pin OK
  [pin_validated = true]          ← F3 validé → Accès dashboard
        │
        ▼  logout / timeout 30min
  [Session détruite]
```

### 4.3 Gestion du blocage de compte

```
  Tentative de connexion
        │
        ▼
  password_verify() → ÉCHEC
        │
        ▼
  attempts = attempts + 1
        │
        ├── attempts < 3 → Message d'erreur générique
        │
        └── attempts >= 3
              │
              ▼
        blocked = 1
        blocked_until = NOW() + 30min
        INSERT logs (status='fail')
              │
              ▼
        "Compte bloqué, réessayez après HH:MM"

  [Déblocage automatique à l'expiration de blocked_until]
```

---

## 5. Diagrammes de séquence

### 5.1 Séquence d'inscription

```
Utilisateur    register.php     db.php     mailer.php
    │               │              │            │
    │─ GET ─────────►│              │            │
    │◄── form ───────│              │            │
    │                │              │            │
    │─ POST ─────────►│              │            │
    │  email,pw,pin  │              │            │
    │                │─ SELECT ─────►│            │
    │                │◄─ not found ──│            │
    │                │              │            │
    │                │ password_hash(pw)          │
    │                │ password_hash(pin)         │
    │                │─ INSERT users ►│            │
    │                │◄─ user_id ────│            │
    │                │              │            │
    │◄── form secrètes│              │            │
    │                │              │            │
    │─ POST q/r ─────►│              │            │
    │                │ password_hash(x3)          │
    │                │─ UPDATE users ►│            │
    │◄── Redirect login│             │            │
```

### 5.2 Séquence de partage de fichier

```
Utilisateur A    share_file.php    db.php    Utilisateur B    shared.php
    │                 │               │            │               │
    │─ POST ──────────►│               │            │               │
    │  file_id, ttl   │─ verify owner ►│            │               │
    │                 │ token=bin2hex(32)           │               │
    │                 │─ INSERT shared_links ►│     │               │
    │◄── lien token ──│               │            │               │
    │                 │               │            │               │
    │  (partage du lien)              │            │               │
    │                 │               │            │─ GET /shared ──►│
    │                 │               │            │  ?token=...   │
    │                 │               │◄─ SELECT shared_links ──────│
    │                 │               │            │               │
    │                 │               │ [vérifie expires_at]       │
    │                 │               │◄─ file_path ───────────────│
    │                 │               │            │◄── fichier ───│
```

---

## 6. Modèle de données

### 6.1 Schéma entité-relation

```
┌─────────────────────┐         ┌───────────────────────┐
│       users         │         │      otp_codes        │
├─────────────────────┤    1    ├───────────────────────┤
│ PK id               │◄────────│ FK user_id            │
│    email (UNIQUE)   │         │    code CHAR(6)       │
│    password         │    0..1 │    expiration         │
│    pin              │         │    attempts           │
│    secret_q1..3     │         └───────────────────────┘
│    secret_a1..3     │
│    attempts         │         ┌───────────────────────┐
│    blocked          │    1    │         logs          │
│    blocked_until    │◄────────├───────────────────────┤
└─────────────────────┘         │ PK id                 │
          │                     │ FK user_id            │
          │ 1                   │    ip VARCHAR(45)     │
          │                     │    date               │
          ▼ *                   │    status             │
┌─────────────────────┐         └───────────────────────┘
│       files         │
├─────────────────────┤         ┌───────────────────────┐
│ PK id               │    1    │      audit_logs        │
│ FK user_id          │◄────────├───────────────────────┤
│    file_path        │         │ PK id                 │
│    display_name     │         │ FK user_id            │
│    category         │         │    action VARCHAR(50) │
│    upload_date      │         │    detail             │
└─────────────────────┘         │    ip, date           │
          │                     └───────────────────────┘
          │ 1
          ▼ *
┌─────────────────────┐         ┌───────────────────────┐
│   shared_links      │         │   password_resets     │
├─────────────────────┤         ├───────────────────────┤
│ PK id               │         │ PK id                 │
│ FK file_id          │         │ FK user_id            │
│ FK user_id          │         │    token VARCHAR(64)  │
│    token VARCHAR(64)│         │    expires_at         │
│    expires_at       │         │    used TINYINT       │
└─────────────────────┘         └───────────────────────┘
```

### 6.2 Description des tables

| Table | Lignes typiques | Données sensibles | Protection |
|-------|----------------|-------------------|------------|
| `users` | 1 par compte | password, pin, réponses secrètes | bcrypt, jamais en clair |
| `otp_codes` | 0–1 par user actif | code OTP | TTL 90s, suppression après usage |
| `logs` | N par user | IP, date, statut | Lecture seule depuis dashboard |
| `audit_logs` | N par user | actions, IP | Append only, export SIEM |
| `files` | N par user | chemin fichier | Accès via contrôleur, pas direct |
| `shared_links` | N par fichier | token de partage | TTL configurable, UNIQUE |
| `password_resets` | 0–1 par user | token | TTL court, usage unique (`used=1`) |

---

## 7. Architecture de déploiement

### 7.1 Déploiement local (XAMPP)

```
  [Navigateur]
       │ HTTP :80
       ▼
  ┌─────────────┐
  │ Apache 2.4  │  C:\xampp\apache
  │ mod_rewrite │
  └──────┬──────┘
         │ FastCGI
         ▼
  ┌─────────────┐     ┌──────────────┐
  │  PHP 8.2    │────►│  MySQL 8.0   │
  │  + Xdebug   │     │  triple_auth │
  └─────────────┘     └──────────────┘
         │
         ▼
  uploads/   (hors webroot via serve_file.php)
```

### 7.2 Déploiement Docker Compose

```
  [Navigateur]
       │ HTTP :8080          │ HTTP :8081
       ▼                     ▼
  ┌──────────────┐    ┌─────────────────┐
  │  triple_app  │    │ triple_phpmyadmin│
  │  PHP 8.2     │    │   phpMyAdmin     │
  │  Apache      │    └────────┬────────┘
  └──────┬───────┘             │
         │                     │
         └──────────┬──────────┘
                    │ réseau triple_net
                    ▼
            ┌───────────────┐
            │  triple_db    │
            │  MySQL 8.0    │
            │  Volume db_data│
            └───────────────┘

  Volume monté : ./uploads → /var/www/html/uploads
  Init SQL : config/create_tables.sql → /docker-entrypoint-initdb.d/
```

### 7.3 Flux réseau et ports

| Service | Port externe | Port interne | Protocole |
|---------|-------------|-------------|-----------|
| Application PHP | 8080 | 80 | HTTP |
| phpMyAdmin | 8081 | 80 | HTTP |
| MySQL | — | 3306 | TCP (interne) |
| SMTP Gmail | — | 587 | STARTTLS |

---

## 8. Modèle de menaces STRIDE

La méthode STRIDE (Microsoft) classe les menaces en six catégories. L'analyse porte sur les composants critiques de l'application.

### 8.1 Tableau STRIDE complet

| # | Menace | Composant cible | Description | Mesure implémentée | Sévérité résiduelle |
|---|--------|-----------------|-------------|-------------------|---------------------|
| S1 | **Spoofing** (usurpation) | login.php | Connexion avec des identifiants volés (phishing, credential stuffing) | OTP email obligatoire (F2) + PIN (F3) → usurpation d'identité impossible sans les 3 facteurs | **Faible** |
| S2 | **Spoofing** | otp.php | Deviner le code OTP (bruteforce 10⁶ possibilités) | 3 tentatives max, TTL 90s, invalidation après usage | **Faible** |
| S3 | **Spoofing** | Session PHP | Vol de cookie de session (XSS, réseau) | HttpOnly, `session_regenerate_id()`, timeout 30 min, `hash_equals()` | **Moyen** |
| T1 | **Tampering** (altération) | upload.php | Upload de fichier malveillant (.php, .exe) | Validation MIME, liste noire d'extensions, stockage hors webroot | **Faible** |
| T2 | **Tampering** | Requêtes POST | Modification de paramètres (IDOR, CSRF) | Token CSRF sur tous les formulaires, vérification `user_id` en session | **Faible** |
| T3 | **Tampering** | Base de données | Injection SQL | PDO avec requêtes préparées sur toutes les requêtes | **Très faible** |
| R1 | **Repudiation** (répudiation) | Actions utilisateur | Un utilisateur nie avoir uploadé/partagé un fichier | `audit_logs` : chaque action est journalisée avec IP, date, user_id | **Faible** |
| R2 | **Repudiation** | Connexions | Nier une tentative de connexion depuis une IP suspecte | Table `logs` avec IP, date, statut pour chaque tentative | **Faible** |
| I1 | **Information Disclosure** (divulgation) | serve_file.php | Accès direct à un fichier médical via URL | Fichiers hors webroot, contrôleur vérifie `user_id` en session | **Très faible** |
| I2 | **Information Disclosure** | Messages d'erreur | Enumération de comptes via messages différenciés | Messages d'erreur génériques ("email ou mot de passe incorrect") | **Faible** |
| I3 | **Information Disclosure** | config/credentials.php | Exposition des secrets SMTP en production | Fichier exclu du git, variables d'environnement Docker | **Faible** |
| D1 | **Denial of Service** | login.php | Bloquer un compte en forçant 3 échecs | Protection partielle : pas de CAPTCHA, attaque ciblée possible | **Moyen** |
| D2 | **Denial of Service** | otp.php | Saturation SMTP par renvois répétés | Limite de 3 renvois par session | **Faible** |
| E1 | **Elevation of Privilege** | dashboard.php | Accéder au dashboard sans 3FA complet | `auth_check.php` vérifie `otp_validated` ET `pin_validated` | **Très faible** |
| E2 | **Elevation of Privilege** | shared.php | Accéder aux fichiers d'un autre utilisateur | Vérification `user_id` sur chaque accès fichier | **Très faible** |

### 8.2 Synthèse des risques

```
         IMPACT
           │
  Critique │  [D1] Blocage       [S1] Phishing
           │  de compte          (sans 3FA)
    Élevé  │
           │  [S3] Vol session
    Moyen  │
           │  [I3] Credentials   [T1] Upload
    Faible │  [R1] Répudiation   [S2] OTP bruteforce
           │  [E1] Privil. esc.
     ──────┼──────────────────────────────────► PROBABILITÉ
           │  Faible    Moyen    Élevé
```

> **Risque résiduel principal** : le verrouillage de compte (D1) peut être exploité pour du déni de service ciblé. Mitigation recommandée : CAPTCHA après 2 échecs.

### 8.3 Arbre d'attaque — Compromission de compte

```
Objectif : Accéder au dashboard sans credentials valides
│
├── [1] Compromettre F1 (mot de passe)
│   ├── Brute force → BLOQUÉ (3 essais max, 30 min)
│   ├── Phishing → INSUFFISANT (F2+F3 requis)
│   └── Credential stuffing → INSUFFISANT (F2+F3 requis)
│
├── [2] Compromettre F2 (OTP)
│   ├── Interception email → TTL 90s, usage unique
│   ├── Bruteforce OTP → BLOQUÉ (3 tentatives max)
│   └── SIM swapping → Non applicable (email, pas SMS)
│
├── [3] Compromettre F3 (PIN)
│   ├── Shoulder surfing → Champ masqué (type="password")
│   ├── Bruteforce PIN → Pas de limitation implémentée ⚠️
│   └── Vol WebAuthn → Clé privée sur l'appareil, non exportable
│
└── [4] Contourner la session
    ├── Vol de cookie → HttpOnly, timeout 30 min
    └── CSRF → Token sur tous les formulaires POST
```

---

## 9. Contrôles de sécurité implémentés

### 9.1 Authentification

| Contrôle | Implémentation | Fichier |
|----------|----------------|---------|
| Hachage bcrypt | `password_hash($pw, PASSWORD_BCRYPT)` | register.php |
| CSRF token | `bin2hex(random_bytes(32))` en session | Tous les formulaires |
| Timeout de session | 1800 secondes d'inactivité | config/auth_check.php |
| Blocage compte | 3 échecs → bloqué 30 min | login.php |
| Détection nouvelle IP | Session `$_SESSION['last_ip']` comparée | login.php |
| OTP à usage unique | DELETE après validation | otp.php |
| Comparaison timing-safe | `hash_equals()` pour OTP | otp.php |

### 9.2 Gestion des fichiers

| Contrôle | Implémentation | Fichier |
|----------|----------------|---------|
| Stockage hors webroot | `uploads/` avec `.htaccess` Deny all | .htaccess |
| Validation MIME | Vérification type MIME réel | upload.php |
| Nom aléatoire | `uniqid()` + suffixe random | upload.php |
| Contrôle d'accès | Vérification `user_id` session | serve_file.php |
| Liens temporaires | Token 64 hex + `expires_at` | share_file.php |

### 9.3 Données

| Contrôle | Implémentation | Norme |
|----------|----------------|-------|
| Requêtes préparées | PDO `prepare()` + `execute([...])` | OWASP A03 |
| Échappement XSS | `htmlspecialchars(ENT_QUOTES)` | OWASP A03 |
| Validation email | `filter_var(FILTER_VALIDATE_EMAIL)` | PHP standard |
| En-têtes sécurité | `.htaccess` : X-Frame-Options, etc. | OWASP A05 |

---

*Document généré le 27 mars 2026 — Version 1.0*
