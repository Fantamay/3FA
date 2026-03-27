<?php
session_start();
require_once __DIR__ . '/config/db.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['secret_pending'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$req = $pdo->prepare("SELECT secret_question1, secret_question2, secret_question3, secret_answer1, secret_answer2, secret_answer3 FROM users WHERE id = ?");
$req->execute([$user_id]);
$user = $req->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['message'] = "Erreur de sécurité.";
        header('Location: questionnaire.php');
        exit;
    }

    
    $a1 = trim(mb_strtolower($_POST['secret_answer1'] ?? ''));
    $a2 = trim(mb_strtolower($_POST['secret_answer2'] ?? ''));
    $a3 = trim(mb_strtolower($_POST['secret_answer3'] ?? ''));

    if ($a1 === '' || $a2 === '' || $a3 === '') {
        $_SESSION['message'] = "Toutes les réponses sont obligatoires.";
        header('Location: questionnaire.php');
        exit;
    }

    
    $ok = password_verify($a1, $user['secret_answer1'] ?? '')
       && password_verify($a2, $user['secret_answer2'] ?? '')
       && password_verify($a3, $user['secret_answer3'] ?? '');

    if ($ok) {
        unset($_SESSION['secret_pending']);
        header('Location: otp.php');
        exit;
    } else {
        $_SESSION['message'] = "Une ou plusieurs réponses sont incorrectes.";
        header('Location: questionnaire.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Questions secrètes - Triple Auth</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="container">
    <h1>Vérification questions secrètes</h1>
    <?php if (isset($_SESSION['message'])): ?>
        <div class="message"><?= htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); ?></div>
    <?php endif; ?>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <label><?= htmlspecialchars($user['secret_question1'] ?? 'Question 1') ?></label>
        <input type="text" name="secret_answer1" maxlength="100" required>
        <label><?= htmlspecialchars($user['secret_question2'] ?? 'Question 2') ?></label>
        <input type="text" name="secret_answer2" maxlength="100" required>
        <label><?= htmlspecialchars($user['secret_question3'] ?? 'Question 3') ?></label>
        <input type="text" name="secret_answer3" maxlength="100" required>
        <button type="submit">Valider</button>
    </form>
</div>
</body>
</html>
