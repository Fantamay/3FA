# Triple Auth - Application de gestion sécurisée de documents médicaux

## Présentation rapide pour la soutenance

Cette application web illustre la triple authentification (3FA) :
1. **Ce que l’utilisateur sait** : mot de passe sécurisé.
2. **Ce que l’utilisateur possède** : code OTP à usage unique.
3. **Ce que l’utilisateur est** : code PIN ou biométrie simulée.

Chaque étape renforce la sécurité.  
Même si un mot de passe est volé (voir la page de démonstration d’attaque), l’accès aux documents médicaux reste protégé par l’OTP et le PIN/biométrie.

Fonctionnalités avancées :
- Blocage du compte après 3 échecs.
- Logs de connexion (IP, date, statut).
- Détection d’activité suspecte (nouvelle IP → OTP).
- Upload sécurisé de documents médicaux.
- Protection contre injections SQL, XSS, CSRF.

**Le projet est sécurisé, professionnel, et prêt à être présenté.**

## Installation

1. Démarrer XAMPP (Apache/MySQL)
2. Copier le dossier `Triple` dans `c:\xampp\htdocs\`
3. Créer la base de données `triple_auth` et importer `config/create_tables.sql`
4. Accéder à [http://localhost/Triple/register.php](http://localhost/Triple/register.php)


# Comment tester l'application Triple Auth (3FA)

1. **Démarrer XAMPP**
   - Lance Apache et MySQL depuis le panneau de contrôle XAMPP.

2. **Préparer la base de données**
   - Ouvre phpMyAdmin : http://localhost/phpmyadmin
   - Crée une base de données nommée `triple_auth`.
   - Importe le fichier `config/create_tables.sql` pour créer les tables.

3. **Installer PHPMailer**
   - Télécharge PHPMailer (https://github.com/PHPMailer/PHPMailer).
   - Place le dossier `PHPMailer` dans `c:\xampp\htdocs\Triple\vendor\PHPMailer`.

4. **Configurer l'envoi d'emails**
   - Vérifie les identifiants SMTP dans `config/mailer.php` (email, mot de passe d’application Gmail, host, port).
   - Utilise un mot de passe d’application Gmail (voir documentation Google).

5. **Lancer l’application**
   - Va sur [http://localhost/Triple/register.php](http://localhost/Triple/register.php)
   - Crée un compte avec ton adresse email réelle (pour recevoir l’OTP).

6. **Tester la triple authentification**
   - Connecte-toi avec email/mot de passe.
   - Vérifie que tu reçois un email avec le code OTP (regarde dans les spams si besoin).
   - Saisis le code OTP reçu.
   - Entre ton code PIN ou utilise la simulation biométrique.
   - Accède au dashboard.

7. **Tester les fonctionnalités**
   - Upload de documents (PDF, JPG, PNG).
   - Nommer les fichiers à l’upload.
   - Changer la photo de profil.
   - Télécharger/supprimer des fichiers.
   - Voir l’historique des connexions et les alertes de sécurité.

8. **Tester la sécurité**
   - Fais 3 erreurs de mot de passe pour vérifier le blocage du compte.
   - Connecte-toi depuis un autre navigateur ou IP pour voir l’alerte nouvelle IP.
   - Tente d’accéder à `dashboard.php` sans être authentifié : tu dois être redirigé.

---

**Astuce** :  
Pour chaque étape, vérifie les messages affichés et le comportement attendu.  
Si tu rencontres une erreur, active l’affichage des erreurs PHP dans XAMPP (`display_errors = On` dans php.ini).

---

