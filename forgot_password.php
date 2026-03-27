<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/mailer.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['msg'] = ['type' => 'error', 'text' => "Erreur de sécurité."];
        header('Location: forgot_password.php');
        exit;
    }

    $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);

    
    $_SESSION['msg'] = ['type' => 'success', 'text' => "Si cet email est enregistré, vous recevrez un lien de réinitialisation."];

    if ($email) {
        $req = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $req->execute([$email]);
        $user = $req->fetch();

        if ($user) {
            
            $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?")->execute([$user['id']]);

            $token      = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', time() + 3600); 

            $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)")
                ->execute([$user['id'], $token, $expires_at]);

            sendPasswordReset($email, $token);
        }
    }

    header('Location: forgot_password.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mot de passe oublié - Triple Auth</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #181829 0%, #2d1e4f 100%);
            font-family: 'Montserrat', Arial, sans-serif;
            color: #f5f6fa;
            min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
        }
        .box {
            background: #23234a;
            padding: 2.5rem 2rem;
            border-radius: 18px;
            box-shadow: 0 2px 16px rgba(143,95,255,0.10);
            max-width: 400px; width: 100%; text-align: center;
        }
        h2 { color: #8f5fff; margin-bottom: 1.2rem; }
        input[type="email"] {
            width: 100%; padding: 0.8rem; border-radius: 8px; border: none;
            font-size: 1rem; background: #181829; color: #fff; margin-bottom: 1rem; box-sizing: border-box;
        }
        button {
            padding: 0.7rem 1.6rem; border-radius: 30px; border: none;
            font-weight: 600; cursor: pointer;
            background: linear-gradient(90deg, #8f5fff, #3e8eff); color: #fff; width: 100%;
        }
        .msg-success { background: #1a3a1a; color: #00e676; padding: 10px; border-radius: 8px; margin-bottom: 1rem; }
        .msg-error   { background: #3a1a1a; color: #ff5252; padding: 10px; border-radius: 8px; margin-bottom: 1rem; }
        a { color: #8f5fff; }
    </style>
</head>
<body>
<div class="box">
    <h2>🔑 Mot de passe oublié</h2>
    <?php if (isset($_SESSION['msg'])): ?>
        <div class="msg-<?= $_SESSION['msg']['type'] ?>"><?= htmlspecialchars($_SESSION['msg']['text']); unset($_SESSION['msg']); ?></div>
    <?php endif; ?>
    <p style="color:#bdbde6;margin-bottom:1.2rem;">Entrez votre email pour recevoir un lien de réinitialisation.</p>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <input type="email" name="email" placeholder="Votre email" required>
        <button type="submit">Envoyer le lien</button>
    </form>
    <p style="margin-top:1.2rem;"><a href="login.php">← Retour à la connexion</a></p>
</div>
</body>
</html>
