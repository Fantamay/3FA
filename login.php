<?php
session_start();
require_once __DIR__ . '/config/db.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['message'] = "Erreur de sécurité.";
        header('Location: login.php');
        exit;
    }

    $email    = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'];
    $ip       = $_SERVER['REMOTE_ADDR'];

    if ($email === false || empty($password)) {
        $_SESSION['message'] = "Informations manquantes.";
        header('Location: login.php');
        exit;
    }

    $req = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $req->execute([$email]);
    $user = $req->fetch();

    if (!$user) {
        $_SESSION['message'] = "Email ou mot de passe incorrect.";
        header('Location: login.php');
        exit;
    }

    
    if (!empty($user['blocked']) && $user['blocked_until'] && strtotime($user['blocked_until']) < time()) {
        $pdo->prepare("UPDATE users SET blocked = 0, attempts = 0, blocked_until = NULL WHERE id = ?")
            ->execute([$user['id']]);
        $user['blocked']  = 0;
        $user['attempts'] = 0;
    }

    if (!empty($user['blocked'])) {
        $until = $user['blocked_until'] ? date('H:i', strtotime($user['blocked_until'])) : '';
        $_SESSION['message'] = "Compte bloqué après 3 échecs." . ($until ? " Réessayez après $until." : '');
        header('Location: login.php');
        exit;
    }

    if (!password_verify($password, $user['password'])) {
        $new_attempts = ($user['attempts'] ?? 0) + 1;
        $pdo->prepare("UPDATE users SET attempts = attempts + 1 WHERE id = ?")->execute([$user['id']]);

        
        if ($new_attempts >= 3) {
            $blocked_until = date('Y-m-d H:i:s', time() + 1800); 
            $pdo->prepare("UPDATE users SET blocked = 1, blocked_until = ? WHERE id = ?")->execute([$blocked_until, $user['id']]);
        }

        $pdo->prepare("INSERT INTO logs (user_id, ip, date, status) VALUES (?, ?, NOW(), 'fail')")->execute([$user['id'], $ip]);
        $_SESSION['message'] = "Email ou mot de passe incorrect.";
        header('Location: login.php');
        exit;
    }

    
    $pdo->prepare("UPDATE users SET attempts = 0, blocked = 0, blocked_until = NULL WHERE id = ?")->execute([$user['id']]);
    $pdo->prepare("INSERT INTO logs (user_id, ip, date, status) VALUES (?, ?, NOW(), 'success')")->execute([$user['id'], $ip]);

    
    $last_ip = $_SESSION['last_ip'] ?? null;
    if ($last_ip && $last_ip !== $ip) {
        $_SESSION['force_otp'] = true;
    }

    $_SESSION['last_ip']        = $ip;
    $_SESSION['user_id']        = $user['id'];
    $_SESSION['email']          = $user['email'];
    $_SESSION['secret_pending'] = true;
    $_SESSION['last_activity']  = time();
    unset($_SESSION['otp_validated'], $_SESSION['pin_validated']);

    header('Location: questionnaire.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Connexion - Triple Auth</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="container">
    <h1>Connexion</h1>
    <?php if (isset($_GET['timeout'])): ?>
        <div class="message" style="background:#3a2a00;color:#ffb300;">Session expirée pour inactivité. Veuillez vous reconnecter.</div>
    <?php endif; ?>
    <?php if (isset($_SESSION['message'])): ?>
        <div class="message"><?= htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); ?></div>
    <?php endif; ?>
    <form method="post" action="">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="password" placeholder="Mot de passe" required>
        <button type="submit">Se connecter</button>
        <p><a href="forgot_password.php">Mot de passe oublié ?</a></p>
        <p>Pas encore de compte ? <a href="register.php">Inscription</a></p>
    </form>
</div>
</body>
</html>
