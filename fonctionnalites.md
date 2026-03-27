# Fonctionnalités — Triple Auth

Application de gestion sécurisée de documents médicaux avec authentification triple facteur (3FA).

**Stack :** PHP 8.2 · MySQL · HTML/CSS/JS · PHPMailer · WebAuthn (natif)

---

## 1. Authentification Triple Facteur (3FA)

### Facteur 1 — Mot de passe
- Inscription avec email, mot de passe (min. 6 car., max. 128) et confirmation
- Hachage bcrypt via `password_hash()`
- Validation email côté serveur (`FILTER_VALIDATE_EMAIL`)
- Vérification CSRF sur tous les formulaires

### Facteur 2a — Questions secrètes
- 3 questions prédéfinies lors de l'inscription :
  - Ville de naissance
  - Nom du premier animal
  - Prénom de la mère
- Réponses **hachées** en base de données (`password_hash`)
- Vérification insensible à la casse (`mb_strtolower`)
- Vérification via `password_verify()`

### Facteur 2b — OTP par email
- Code à 6 chiffres généré aléatoirement (`random_int`)
- Envoi via Gmail SMTP (PHPMailer)
- Expiration : **2 minutes**
- Maximum **3 tentatives** avant blocage
- Maximum **3 renvois** par session (rate limiting)
- CSRF sur le formulaire et le bouton "Renvoyer"
- Bouton "Renvoyer le code" avec protection anti-spam

### Facteur 3 — PIN + Biométrie WebAuthn
- Code PIN à 4 chiffres stocké **haché** (`password_hash`)
- **WebAuthn (W3C standard)** — authentification biométrique réelle :
  - Windows Hello (empreinte, visage, PIN Windows)
  - Touch ID / Face ID (macOS, iOS)
  - Clés de sécurité FIDO2 (YubiKey, etc.)
  - Algorithmes supportés : **ES256** (ECDSA P-256) et **RS256** (RSA)
  - Vérification cryptographique côté serveur (sans bibliothèque externe)
  - Protection anti-replay via `signCount`
  - Enregistrement de la clé publique en base de données
  - Lancement automatique de la biométrie si déjà enregistrée
  - PIN comme méthode de repli si biométrie refusée

---

## 2. Gestion des comptes

### Inscription
- Formulaire email + mot de passe + PIN
- Redirection vers les questions secrètes après création du compte
- Vérification de l'unicité de l'email en base

### Connexion
- Détection de nouvelle IP → alerte dans le dashboard
- Log de chaque tentative (réussie ou échouée) en base de données

### Blocage de compte
- Blocage automatique après **3 échecs** de mot de passe
- Durée de blocage : **30 minutes** (temporaire, pas définitif)
- Déblocage automatique à la prochaine tentative après expiration
- Message indiquant l'heure de déblocage

### Réinitialisation de mot de passe
- Formulaire "Mot de passe oublié" (`forgot_password.php`)
- Envoi d'un lien sécurisé par email (token aléatoire 64 caractères)
- Validité du lien : **1 heure**
- Token marqué "utilisé" après emploi (usage unique)
- Réinitialisation débloque aussi le compte bloqué

### Déconnexion
- Destruction complète de la session
- Redirection vers la page de connexion

---

## 3. Sécurité de session

### Timeout automatique
- Déconnexion après **30 minutes d'inactivité**
- Vérification côté serveur à chaque page protégée (`config/auth_check.php`)
- Message d'information à la reconnexion

### Barre de compte à rebours (dashboard)
- Affichage du temps restant en temps réel (JavaScript)
- Avertissement visuel en orange dans les **5 dernières minutes**
- Redirection automatique à l'expiration

### Protection CSRF
- Token CSRF généré par `bin2hex(random_bytes(32))`
- Vérifié sur **tous** les formulaires POST
- Vérifié sur les actions sensibles (suppression, téléchargement, partage)

---

## 4. Gestion des documents médicaux

### Upload
- Formats acceptés : **PDF, JPG, PNG**
- Taille maximale : **5 Mo**
- Validation du type MIME réel (via `finfo`, pas seulement l'extension)
- Nom de fichier personnalisé (optionnel, max 80 caractères)
- Choix de la **catégorie** lors de l'upload

### Catégories
- Ordonnances
- Radios / Imagerie
- Analyses / Biologie
- Vaccins
- Comptes-rendus
- Autres
- Badge coloré par catégorie sur chaque fichier
- Filtres de catégorie dans le dashboard (affichage dynamique JS)

### Consultation
- Aperçu **miniature** pour les images directement dans le dashboard
- Ouverture en nouvel onglet via `serve_file.php`
- Téléchargement individuel sécurisé
- Accès protégé : vérification 3FA obligatoire pour tout accès aux fichiers

### Renommage
- Formulaire inline par fichier (icône stylo)
- Mis à jour en base de données (max 80 caractères)

### Suppression
- Suppression unitaire avec confirmation JavaScript
- **Suppression en masse** : cases à cocher + bouton "Supprimer la sélection"
- Suppression physique du fichier ET de l'entrée en base
- CSRF sur toutes les suppressions

### Export ZIP
- Téléchargement de tous les documents en une archive ZIP
- Action POST avec vérification CSRF
- Nommage automatique avec date/heure

### Partage sécurisé
- Génération d'un **lien temporaire** par fichier
- Durées disponibles : 1h, 6h, 24h, 3 jours, 7 jours
- Lien public accessible sans connexion (`shared.php`)
- Prévisualisation et téléchargement sur la page de partage
- Bouton "Copier le lien" (Clipboard API)
- Un seul lien actif par fichier (l'ancien est remplacé)

---

## 5. Profil utilisateur

### Photo de profil
- Upload JPG ou PNG (max 2 Mo)
- Validation MIME réelle
- Sauvegarde avec extension correcte (`profile_{id}.jpg` ou `.png`)
- Servie via `serve_profile.php` (authentification requise, pas d'accès direct)
- Suppression automatique de l'ancienne photo lors du remplacement
- Image par défaut si aucune photo uploadée

---

## 6. Journaux et traçabilité

### Historique des connexions
- Enregistrement de chaque tentative (réussie / échouée)
- Date, IP, statut
- Affichage des 10 dernières entrées dans le dashboard

### Journal d'activité (audit)
- Actions tracées :
  - `upload` — upload de document
  - `download` — téléchargement de fichier
  - `view` — consultation de fichier
  - `delete` — suppression de fichier
  - `bulk_delete` — suppression en masse
  - `share` — partage de fichier
  - `rename` — renommage de fichier
  - `download_all` — export ZIP
  - `profile_update` — changement de photo de profil
  - `biometric_auth` — authentification biométrique réussie
- Affichage des 15 dernières entrées avec badges colorés par type

### Détection d'activité suspecte
- Alerte visuelle si connexion depuis une nouvelle adresse IP

---

## 7. Sécurité des données

### Stockage sécurisé
- Mots de passe hachés (bcrypt, `PASSWORD_DEFAULT`)
- PIN haché (bcrypt)
- Réponses secrètes hachées (bcrypt)
- Clé publique WebAuthn stockée en PEM
- Credentials email externalisés dans `config/credentials.php` (hors versioning)

### Accès aux fichiers
- Dossier `uploads/` bloqué par `.htaccess` (accès direct interdit)
- Tous les fichiers servis via PHP avec vérification d'authentification
- Vérification que le fichier appartient à l'utilisateur connecté

### En-têtes HTTP de sécurité (`.htaccess`)
- `X-Frame-Options: SAMEORIGIN` — protection contre le clickjacking
- `X-Content-Type-Options: nosniff` — protection MIME sniffing
- `X-XSS-Protection: 1; mode=block` — protection XSS navigateur
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy` — désactivation géolocalisation, micro, caméra

### Autres protections
- Requêtes préparées PDO partout (protection SQL injection)
- Encodage `htmlspecialchars()` sur toutes les sorties (protection XSS)
- `X-Content-Type-Options: nosniff` sur les fichiers servis
- Erreurs PHP masquées en production (`display_errors off`)

---

## 8. Structure technique

### Fichiers principaux
| Fichier | Rôle |
|---|---|
| `register.php` | Inscription + questions secrètes |
| `login.php` | Connexion (facteur 1) |
| `questionnaire.php` | Questions secrètes (facteur 2a) |
| `otp.php` | Code OTP email (facteur 2b) |
| `verify_pin.php` | PIN + WebAuthn (facteur 3) |
| `dashboard.php` | Interface principale |
| `forgot_password.php` | Demande de réinitialisation |
| `reset_password.php` | Nouveau mot de passe |
| `serve_file.php` | Serveur de fichiers sécurisé |
| `serve_profile.php` | Serveur de photo de profil |
| `share_file.php` | Génération de lien de partage |
| `shared.php` | Accès public à un fichier partagé |
| `upload.php` | Upload de document (page dédiée) |
| `logout.php` | Déconnexion |
| `webauthn_register.php` | API enregistrement biométrique |
| `webauthn_auth.php` | API authentification biométrique |

### Fichiers de configuration
| Fichier | Rôle |
|---|---|
| `config/db.php` | Connexion PDO MySQL |
| `config/mailer.php` | Envoi d'emails (OTP + reset) |
| `config/credentials.php` | Identifiants SMTP (non versionné) |
| `config/auth_check.php` | Vérification timeout de session |
| `config/helpers.php` | Fonctions utilitaires (audit_log, catégories) |
| `config/webauthn.php` | Implémentation WebAuthn (CBOR, COSE, crypto) |

### Base de données
| Table | Contenu |
|---|---|
| `users` | Comptes utilisateurs (email, password, pin, questions secrètes, blocage) |
| `otp_codes` | Codes OTP temporaires |
| `files` | Métadonnées des documents uploadés |
| `logs` | Historique des connexions |
| `audit_logs` | Journal d'activité détaillé |
| `password_resets` | Tokens de réinitialisation de mot de passe |
| `shared_links` | Liens de partage temporaires |
| `webauthn_credentials` | Clés publiques biométriques (WebAuthn) |
