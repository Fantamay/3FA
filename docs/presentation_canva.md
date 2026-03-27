# PRÉSENTATION CANVA — Triple Auth (3FA)
## Contenu slide par slide — Soutenance

---

## SLIDE 1 — TITRE

**Titre principal :**
> Triple Auth — 3FA
> Application de gestion sécurisée de documents médicaux

**Sous-titre :**
> Authentification à trois facteurs · PHP 8.2 · WebAuthn · Docker

**Visuel suggéré :** fond sombre (bleu nuit #181829), icône cadenas violet, ton nom

---

## SLIDE 2 — PROBLÉMATIQUE

**Titre :** Pourquoi la 3FA ?

**Points clés :**
- 🔓 81 % des violations de données exploitent des mots de passe volés *(Verizon DBIR 2023)*
- 🏥 Le secteur santé = cible n°1 des ransomwares en France *(ANSSI 2023)*
- ⚡ Les kits de phishing modernes contournent le 2FA en temps réel
- 🛡️ **Solution : 3 preuves d'identité indépendantes**

**Visuel suggéré :** 3 icônes en ligne (cadenas → email → empreinte) avec flèches

---

## SLIDE 3 — LES 3 FACTEURS

**Titre :** Une triple barrière de sécurité

| | Facteur | Ce que c'est | Technique |
|--|---------|--------------|-----------|
| 1️⃣ | Connaissance | Mot de passe | bcrypt |
| 2️⃣ | Possession | Code OTP email | 6 chiffres · TTL 90s |
| 3️⃣ | Inhérence | PIN ou biométrie | bcrypt · WebAuthn FIDO2 |

**Accroche :** *"Voler le mot de passe ne suffit plus."*

**Visuel suggéré :** 3 cartes colorées côte à côte (violet, bleu, vert)

---

## SLIDE 4 — DÉMO DU FLUX (SCHÉMA)

**Titre :** Le parcours de connexion

```
[ Login ] → [ OTP email ] → [ PIN / Biométrie ] → [ Dashboard ]
   F1              F2                 F3
```

**Détails à afficher :**
- F1 : CSRF token · blocage 3 essais · détection nouvelle IP
- F2 : Code usage unique · expiré en 90s · 3 tentatives max
- F3 : PIN 4 chiffres **ou** Windows Hello / TouchID

**Visuel suggéré :** timeline horizontale avec 4 étapes numérotées

---

## SLIDE 5 — FONCTIONNALITÉS

**Titre :** Ce que fait l'application

**Colonne gauche — Sécurité :**
- 🔐 Triple authentification (3FA)
- 🚫 Blocage compte après 3 échecs
- 📍 Détection de nouvelle IP
- 🔁 Réinitialisation par email + questions secrètes
- 👁️ Journal d'audit complet

**Colonne droite — Documents médicaux :**
- 📄 Upload PDF / JPEG / PNG
- 🗂️ 6 catégories (Ordonnances, Radios, Analyses...)
- 🔗 Liens de partage temporaires
- 📥 Téléchargement sécurisé
- 🖼️ Photo de profil

---

## SLIDE 6 — ARCHITECTURE TECHNIQUE

**Titre :** Stack technique

```
┌─────────────────────────────────┐
│         NAVIGATEUR              │
│   HTML · CSS · JS · WebAuthn    │
├─────────────────────────────────┤
│         PHP 8.2 + Apache        │
│   PDO · PHPMailer · WebAuthn    │
├─────────────────────────────────┤
│         MySQL 8.0               │
│   7 tables · requêtes préparées │
└─────────────────────────────────┘
      ⬇ Conteneurisé avec Docker
```

**Badges à afficher :** PHP 8.2 · MySQL 8 · Docker · WebAuthn · PHPUnit · Composer

---

## SLIDE 7 — SÉCURITÉ OWASP

**Titre :** Protections OWASP Top 10

| Attaque | Protection |
|---------|------------|
| Injection SQL | PDO requêtes préparées |
| XSS | `htmlspecialchars()` partout |
| CSRF | Token sur chaque formulaire |
| Brute Force | Blocage 3 essais · 30 min |
| Upload malveillant | Validation MIME · .htaccess |
| Accès non autorisé | Vérification session à chaque page |

**Badge vert :** 13/13 tests de sécurité réussis ✓

---

## SLIDE 8 — MODÈLE DE MENACES STRIDE

**Titre :** Analyse des risques — STRIDE

**6 catégories de menaces :**

| Lettre | Menace | Notre réponse |
|--------|--------|---------------|
| **S** | Spoofing (usurpation) | 3FA rend l'usurpation quasi impossible |
| **T** | Tampering (altération) | CSRF + PDO préparé |
| **R** | Repudiation | Logs + audit trail |
| **I** | Info Disclosure | Fichiers hors webroot |
| **D** | Denial of Service | Limite d'essais + renvois |
| **E** | Elevation of Privilege | Vérification session stricte |

---

## SLIDE 9 — BIOMÉTRIE WEBAUTHN

**Titre :** Le facteur biométrique — WebAuthn FIDO2

**Ce que c'est :**
- Standard W3C — supporté par Chrome, Edge, Firefox
- Utilise Windows Hello, TouchID, FaceID
- **La clé privée ne quitte jamais l'appareil**

**Comment ça marche :**
```
Serveur → challenge → Navigateur → Authentificateur
                                   (empreinte / visage)
                                         ↓
Serveur ← signature vérifiée ← Navigateur
```

**Implémenté en PHP natif** (CBOR · ASN.1 · ECDSA · RSA)

**Visuel suggéré :** icône empreinte digitale + schéma challenge/réponse

---

## SLIDE 10 — TESTS & QUALITÉ

**Titre :** Tests automatisés — PHPUnit

**Chiffres clés (grands et visibles) :**

```
100 tests        164 assertions        74% couverture
```

**3 suites de tests :**
- **SecurityTest** : bcrypt, OTP, tokens, XSS, upload, blocage (38 tests)
- **WebAuthnTest** : CBOR, ASN.1, b64url, parse_auth_data, PEM (35 tests)
- **HelpersTest + SiemTest** : catégories, règles SIEM (27 tests)

**Objectif atteint :** 74 % ≥ 70 % requis ✓

---

## SLIDE 11 — DÉPLOIEMENT DOCKER

**Titre :** Une commande pour tout démarrer

```bash
docker compose up -d
```

**3 services lancés automatiquement :**

| Service | URL | Rôle |
|---------|-----|------|
| 🌐 Application | localhost:8080 | PHP + Apache |
| 🗄️ Base de données | (interne) | MySQL 8 |
| 🔧 phpMyAdmin | localhost:8081 | Administration BDD |

**+ Base de données initialisée automatiquement**

---

## SLIDE 12 — MONITORING SIEM

**Titre :** Supervision de sécurité — SIEM

**6 règles d'alerte configurées :**

| Règle | Sévérité |
|-------|----------|
| Brute force (3 échecs / 5 min) | 🔴 CRITICAL |
| Compte bloqué | 🔴 CRITICAL |
| Échec OTP répété | 🟠 HIGH |
| Connexion nouvelle IP | 🟡 WARNING |
| Accès non autorisé | 🟡 WARNING |
| Volume d'uploads anormal | 🟡 WARNING |

**Format exportable JSON + requêtes SQL dashboard**

---

## SLIDE 13 — DÉMO EN DIRECT

**Titre :** Démonstration

**Scénario à montrer :**

1. 📝 Inscription avec email + mot de passe + PIN
2. 🔑 Connexion → reçoit l'OTP par email
3. ✅ Saisie OTP → étape PIN / biométrie
4. 📊 Accès au dashboard → upload d'un document
5. 🔗 Génération d'un lien de partage temporaire
6. ⛔ Test d'attaque : 3 mauvais mots de passe → compte bloqué
7. 🎭 Page de démonstration phishing

---

## SLIDE 14 — LIVRABLES

**Titre :** Ce qui a été livré

✅ Code source versionné — GitHub : `github.com/Fantamay/3FA`
✅ README complet (prérequis · install · Docker · tests)
✅ Docker Compose fonctionnel (1 commande)
✅ Config SIEM exportable (JSON + SQL)
✅ Suite de tests PHPUnit (100 tests · 74% couverture)
✅ Document d'architecture (15 pages · UML · STRIDE)
✅ Rapport technique final (20 pages)

---

## SLIDE 15 — CONCLUSION

**Titre :** Bilan & Perspectives

**Ce qu'on a prouvé :**
> Un système 3FA correctement implémenté résiste aux attaques de phishing, brute force et credential stuffing — même si un facteur est compromis.

**Axes d'amélioration :**
- TOTP (Google Authenticator) en remplacement de l'OTP email
- CAPTCHA après 2 échecs de connexion
- Chiffrement des fichiers au repos (AES-256)
- Audit de sécurité tiers (pentest)

**Questions ?**

---

## CONSEILS CANVA

### Palette de couleurs recommandée
| Couleur | Hex | Usage |
|---------|-----|-------|
| Fond principal | `#181829` | Arrière-plan slides |
| Fond carte | `#23234a` | Cartes et encadrés |
| Violet accent | `#8f5fff` | Titres, icônes, badges |
| Bleu info | `#3e8eff` | Éléments secondaires |
| Vert succès | `#00c875` | Validations, checks |
| Rouge alerte | `#ff5252` | Alertes, erreurs |
| Texte principal | `#f5f6fa` | Corps de texte |

### Police recommandée
- **Titres :** Montserrat Bold 700
- **Corps :** Montserrat Regular 400 ou Inter

### Template Canva à chercher
→ Rechercher **"Dark Tech Presentation"** ou **"Cybersecurity Pitch Deck"**

### Ordre des slides
1. Titre
2. Problématique
3. Les 3 facteurs
4. Flux de connexion
5. Fonctionnalités
6. Architecture
7. Sécurité OWASP
8. STRIDE
9. WebAuthn
10. Tests
11. Docker
12. SIEM
13. Démo
14. Livrables
15. Conclusion

**Durée estimée : 15-20 minutes de présentation**
