<?php
session_start();
require_once __DIR__ . '/config/db.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$token       = trim($_GET['token'] ?? '');
$error       = '';
$valid_token = null;

if ($token) {
    $req = $pdo->prepare("SELECT pr.*, u.email FROM password_resets pr JOIN users u ON u.id = pr.user_id WHERE pr.token = ? AND pr.used = 0 AND pr.expires_at > NOW()");
    $req->execute([$token]);
    $valid_token = $req->fetch();
    if (!$valid_token) {
        $error = "Ce lien est invalide ou expiré.";
    }
} else {
    $error = "Token manquant.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Erreur de sécurité.";
    } else {
        $password  = $_POST['password'] ?? '';
        $password2 = $_POST['password2'] ?? '';

        if (strlen($password) < 6 || strlen($password) > 128) {
            $error = "Le mot de passe doit contenir entre 6 et 128 caractères.";
        } elseif ($password !== $password2) {
            $error = "Les mots de passe ne correspondent pas.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            
            $pdo->prepare("UPDATE users SET password = ?, attempts = 0, blocked = 0, blocked_until = NULL WHERE id = ?")
                ->execute([$hash, $valid_token['user_id']]);
            $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = ?")
                ->execute([$token]);

            $_SESSION['message'] = "Mot de passe réinitialisé avec succès. Vous pouvez vous connecter.";
            header('Location: login.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Nouveau mot de passe - Triple Auth</title>
    <style>
        body {
            background: linear-gradient(135deg, #181829 0%, #2d1e4f 100%);
            font-family: 'Montserrat', Arial, sans-serif;
            color: #f5f6fa;
            min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
        }
        .box {
            background: #23234a; padding: 2.5rem 2rem; border-radius: 18px;
            box-shadow: 0 2px 16px rgba(143,95,255,0.10);
            max-width: 400px; width: 100%; text-align: center;
        }
        h2 { color: #8f5fff; margin-bottom: 1.2rem; }
        input[type="password"] {
            width: 100%; padding: 0.8rem; border-radius: 8px; border: none;
            font-size: 1rem; background: #181829; color: #fff; margin-bottom: 1rem; box-sizing: border-box;
        }
        button {
            padding: 0.7rem 1.6rem; border-radius: 30px; border: none;
            font-weight: 600; cursor: pointer;
            background: linear-gradient(90deg, #8f5fff, #3e8eff); color: #fff; width: 100%;
        }
        .error { background: #3a1a1a; color: #ff5252; padding: 10px; border-radius: 8px; margin-bottom: 1rem; }
        a { color: #8f5fff; }
    </style>
</head>
<body>
<div class="box">
    <h2>🔐 Nouveau mot de passe</h2>
    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php if (!$valid_token): ?>
            <p><a href="forgot_password.php">← Refaire une demande</a></p>
        <?php endif; ?>
    <?php endif; ?>
    <?php if ($valid_token): ?>
        <p style="color:#bdbde6;margin-bottom:1.2rem;">Compte : <b><?= htmlspecialchars($valid_token['email']) ?></b></p>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="password" name="password" placeholder="Nouveau mot de passe (min 6 car.)" required>
            <input type="password" name="password2" placeholder="Confirmer le mot de passe" required>
            <button type="submit">Réinitialiser</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
