<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth_check.php';
require_once __DIR__ . '/config/helpers.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['otp_validated']) || empty($_SESSION['pin_validated'])) {
    header('Location: login.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['message'] = "Erreur de sécurité.";
        header('Location: dashboard.php');
        exit;
    }

    $file_id  = filter_input(INPUT_POST, 'file_id', FILTER_VALIDATE_INT);
    $duration = (int)($_POST['duration'] ?? 24); 
    if ($duration < 1)   $duration = 1;
    if ($duration > 168) $duration = 168; 

    
    $req = $pdo->prepare("SELECT * FROM files WHERE id = ? AND user_id = ?");
    $req->execute([$file_id, $user_id]);
    $file = $req->fetch();

    if (!$file) {
        $_SESSION['message'] = "Fichier introuvable.";
        header('Location: dashboard.php');
        exit;
    }

    
    $pdo->prepare("DELETE FROM shared_links WHERE file_id = ? AND user_id = ?")->execute([$file_id, $user_id]);

    $token      = bin2hex(random_bytes(32));
    $expires_at = date('Y-m-d H:i:s', time() + $duration * 3600);

    $pdo->prepare("INSERT INTO shared_links (file_id, user_id, token, expires_at) VALUES (?, ?, ?, ?)")
        ->execute([$file_id, $user_id, $token, $expires_at]);

    
    $protocol  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host      = $_SERVER['HTTP_HOST'];
    $path      = dirname($_SERVER['SCRIPT_NAME']);
    $share_url = $protocol . '://' . $host . rtrim($path, '/') . '/shared.php?token=' . $token;

    audit_log($pdo, $user_id, 'share', 'Fichier partagé : ' . $file['display_name']);

    $_SESSION['share_url']     = $share_url;
    $_SESSION['share_expires'] = date('d/m/Y H:i', time() + $duration * 3600);
    header('Location: dashboard.php#share-result');
    exit;
}

header('Location: dashboard.php');
exit;
