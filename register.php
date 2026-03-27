<?php
session_start();
require_once __DIR__ . '/config/db.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (isset($_SESSION['register_user_id'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['secret_answer1'], $_POST['secret_answer2'], $_POST['secret_answer3'])) {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $_SESSION['message'] = "Erreur de sécurité.";
            header('Location: register.php');
            exit;
        }

        
        
        $q1 = "Quelle est votre ville de naissance ?";
        $q2 = "Quel est le nom de votre premier animal ?";
        $q3 = "Quel est le prénom de votre mère ?";

        $a1 = trim($_POST['secret_answer1']);
        $a2 = trim($_POST['secret_answer2']);
        $a3 = trim($_POST['secret_answer3']);

        if ($a1 === '' || $a2 === '' || $a3 === '') {
            $_SESSION['message'] = "Toutes les réponses sont obligatoires.";
            header('Location: register.php');
            exit;
        }

        
        $h1 = password_hash(mb_strtolower($a1), PASSWORD_DEFAULT);
        $h2 = password_hash(mb_strtolower($a2), PASSWORD_DEFAULT);
        $h3 = password_hash(mb_strtolower($a3), PASSWORD_DEFAULT);

        $uid = $_SESSION['register_user_id'];
        $req = $pdo->prepare("UPDATE users SET secret_question1 = ?, secret_answer1 = ?, secret_question2 = ?, secret_answer2 = ?, secret_question3 = ?, secret_answer3 = ? WHERE id = ?");
        $req->execute([$q1, $h1, $q2, $h2, $q3, $h3, $uid]);
        unset($_SESSION['register_user_id']);
        header('Location: login.php');
        exit;
    }
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <title>Questions secrètes</title>
        <style>
            body {
                background: linear-gradient(135deg, #181829 0%, #2d1e4f 100%);
                font-family: 'Montserrat', Arial, sans-serif;
                color: #f5f6fa;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .secret-container {
                background: #23234a;
                padding: 2.5rem 2rem;
                border-radius: 18px;
                box-shadow: 0 2px 16px 0 rgba(143,95,255,0.10);
                max-width: 400px;
                width: 100%;
                text-align: center;
            }
            .secret-container h2 { color: #8f5fff; margin-bottom: 1.5rem; font-size: 1.5rem; }
            .secret-container form { display: flex; flex-direction: column; gap: 1.2rem; }
            .secret-container label { text-align: left; color: #bdbde6; margin-bottom: 0.2rem; }
            .secret-container input[type="text"] {
                padding: 0.8rem;
                border-radius: 8px;
                border: none;
                font-size: 1.1rem;
                background: #181829;
                color: #fff;
            }
            .secret-container button {
                padding: 0.7rem 1.6rem;
                border-radius: 30px;
                border: none;
                font-weight: 600;
                font-size: 1rem;
                cursor: pointer;
                background: linear-gradient(90deg, #8f5fff 0%, #3e8eff 100%);
                color: #fff;
                transition: background 0.2s;
            }
            .secret-container button:hover {
                background: linear-gradient(90deg, #3e8eff 0%, #8f5fff 100%);
            }
            .message { color: #ff5252; margin-bottom: 1rem; }
        </style>
    </head>
    <body>
        <div class="secret-container">
            <h2>Questions secrètes</h2>
            <?php if (!empty($_SESSION['message'])): ?>
                <p class="message"><?= htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); ?></p>
            <?php endif; ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <label for="secret_answer1">Quelle est votre ville de naissance ?</label>
                <input type="text" name="secret_answer1" id="secret_answer1" maxlength="100" required>

                <label for="secret_answer2">Quel est le nom de votre premier animal ?</label>
                <input type="text" name="secret_answer2" id="secret_answer2" maxlength="100" required>

                <label for="secret_answer3">Quel est le prénom de votre mère ?</label>
                <input type="text" name="secret_answer3" id="secret_answer3" maxlength="100" required>

                <button type="submit">Valider</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['message'] = "Erreur de sécurité.";
        header('Location: register.php');
        exit;
    }

    $email            = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
    $password         = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];
    $pin              = $_POST['pin'];

    if (!$email || strlen($password) < 6 || strlen($password) > 128 || $password !== $password_confirm || !preg_match('/^\d{4}$/', $pin)) {
        $_SESSION['message'] = "Vérifiez vos informations.";
        header('Location: register.php');
        exit;
    }

    
    $req = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $req->execute([$email]);
    if ($req->fetch()) {
        $_SESSION['message'] = "Email déjà utilisé.";
        header('Location: register.php');
        exit;
    }

    $hash     = password_hash($password, PASSWORD_DEFAULT);
    $pin_hash = password_hash($pin, PASSWORD_DEFAULT);

    $req = $pdo->prepare("INSERT INTO users (email, password, pin) VALUES (?, ?, ?)");
    $req->execute([$email, $hash, $pin_hash]);
    $user_id = $pdo->lastInsertId();
    $_SESSION['register_user_id'] = $user_id;

    header('Location: register.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Inscription - Triple Auth</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="container">
        <h1>Inscription</h1>
        <?php if (!empty($_SESSION['message'])): ?>
            <p style="color:red"><?= htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); ?></p>
        <?php endif; ?>
        <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="text" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Mot de passe (min 6 caractères)" required>
            <input type="password" name="password_confirm" placeholder="Confirmer le mot de passe" required>
            <input type="text" name="pin" placeholder="Code PIN (4 chiffres)" pattern="\d{4}" maxlength="4" required>
            <button type="submit">S'inscrire</button>
            <p>Déjà inscrit ? <a href="login.php">Connexion</a></p>
        </form>
    </div>
</body>
</html>
