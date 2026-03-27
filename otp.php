<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/mailer.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function generateSendOTP($pdo, $user_id) {
    $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $exp  = date('Y-m-d H:i:s', time() + 90);

    $pdo->prepare("REPLACE INTO otp_codes (user_id, code, expiration, attempts) VALUES (?, ?, ?, 0)")
        ->execute([$user_id, $code, $exp]);

    $req = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $req->execute([$user_id]);
    $user = $req->fetch();

    if ($user) {
        $ok = sendOTP($user['email'], $code);
        if (!$ok) error_log("OTP non envoyé user=$user_id");
    }
}

if (isset($_POST['resend'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['message'] = "Erreur de sécurité.";
        header('Location: otp.php');
        exit;
    }

    $_SESSION['otp_resend_count'] = ($_SESSION['otp_resend_count'] ?? 0) + 1;
    if ($_SESSION['otp_resend_count'] > 3) {
        $_SESSION['message'] = "Trop de renvois. Reconnectez-vous.";
        session_destroy();
        header('Location: login.php');
        exit;
    }

    generateSendOTP($pdo, $user_id);
    $_SESSION['message'] = "Nouveau code envoyé.";
    header('Location: otp.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['resend'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['message'] = "Erreur de sécurité.";
        header('Location: otp.php');
        exit;
    }

    $entered_otp = trim($_POST['otp'] ?? '');

    $req = $pdo->prepare("SELECT * FROM otp_codes WHERE user_id = ?");
    $req->execute([$user_id]);
    $otp = $req->fetch();

    if (!$otp) {
        $_SESSION['message'] = "Aucun code actif. Cliquez sur \"Renvoyer le code\".";
        header('Location: otp.php');
        exit;
    }

    $exp = $otp['expiration'] ? strtotime($otp['expiration']) : 0;
    if (!$exp || $exp < time()) {
        $pdo->prepare("DELETE FROM otp_codes WHERE user_id = ?")->execute([$user_id]);
        $_SESSION['message'] = "Code expiré, demandez-en un nouveau.";
        header('Location: otp.php');
        exit;
    }

    if ($otp['attempts'] >= 3) {
        $pdo->prepare("DELETE FROM otp_codes WHERE user_id = ?")->execute([$user_id]);
        $_SESSION['message'] = "Trop de tentatives. Reconnectez-vous.";
        session_destroy();
        header('Location: login.php');
        exit;
    }

    if ($entered_otp === $otp['code']) {
        $_SESSION['otp_validated'] = true;
        unset($_SESSION['otp_resend_count']);
        $pdo->prepare("DELETE FROM otp_codes WHERE user_id = ?")->execute([$user_id]);
        header('Location: verify_pin.php');
        exit;
    }

    $pdo->prepare("UPDATE otp_codes SET attempts = attempts + 1 WHERE user_id = ?")->execute([$user_id]);
    $_SESSION['message'] = "Code incorrect.";
    header('Location: otp.php');
    exit;
}

$req = $pdo->prepare("SELECT * FROM otp_codes WHERE user_id = ?");
$req->execute([$user_id]);
$otp = $req->fetch();

if (!$otp || !$otp['expiration'] || strtotime($otp['expiration']) < time()) {
    generateSendOTP($pdo, $user_id);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification OTP – Triple Auth</title>
    <link rel="stylesheet" href="assets/fontawesome/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: #181829;
            font-family: 'Montserrat', sans-serif;
            color: #f5f6fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .otp-container {
            background: #23234a;
            padding: 2rem 1.8rem 2.5rem;
            border-radius: 14px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.4);
            width: 100%;
            max-width: 380px;
            text-align: center;
        }
        .otp-container h2 {
            color: #8f5fff;
            margin: 1rem 0 1.4rem;
            font-size: 1.3rem;
        }
        .otp-container form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .otp-container input[type="text"] {
            padding: 0.75rem;
            border-radius: 8px;
            border: 1px solid #3a3a6a;
            font-size: 1.2rem;
            background: #181829;
            color: #fff;
            text-align: center;
            tracking: wide;
        }
        .otp-container input[type="submit"],
        .otp-container button {
            padding: 0.65rem 1.4rem;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            background: #8f5fff;
            color: #fff;
            transition: background 0.2s;
        }
        .otp-container input[type="submit"]:hover,
        .otp-container button:hover { background: #7a4de0; }
        .message {
            padding: 10px 12px;
            border-radius: 6px;
            margin-bottom: 12px;
            background: #2d1a4d;
            color: #c4a0ff;
            font-size: 0.9rem;
        }
        .info {
            font-size: 0.85rem;
            color: #aaa;
            margin-bottom: 1.2rem;
            line-height: 1.5;
        }
        .resend-form { margin-top: 0.8rem; }
    </style>
</head>
<body>
    <div class="otp-container">
        <i class="fas fa-lock" style="font-size:2rem;color:#8f5fff;"></i>
        <h2>Code de vérification</h2>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="message">
                <?= htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); ?>
            </div>
        <?php endif; ?>

        <p class="info">Un code vous a été envoyé par email. Saisissez-le ci-dessous.</p>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="text" name="otp" maxlength="6" placeholder="______" required autocomplete="one-time-code">
            <input type="submit" value="Valider">
        </form>

        <div class="resend-form">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <button type="submit" name="resend" style="background:transparent;color:#8f5fff;border:1px solid #8f5fff;">
                    Renvoyer le code
                </button>
            </form>
        </div>
    </div>
</body>
</html>
