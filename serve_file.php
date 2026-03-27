<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth_check.php';
require_once __DIR__ . '/config/helpers.php';

if (
    !isset($_SESSION['user_id']) ||
    empty($_SESSION['otp_validated']) ||
    empty($_SESSION['pin_validated'])
) {
    http_response_code(403);
    exit('Accès refusé.');
}

$user_id = $_SESSION['user_id'];
$id      = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    http_response_code(400);
    exit('Paramètre invalide.');
}

$req = $pdo->prepare("SELECT * FROM files WHERE id = ? AND user_id = ?");
$req->execute([$id, $user_id]);
$file = $req->fetch();

if (!$file) {
    http_response_code(404);
    exit('Fichier introuvable.');
}

$path = __DIR__ . '/' . $file['file_path'];
if (!file_exists($path)) {
    http_response_code(404);
    exit('Fichier introuvable sur le serveur.');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($path);

$download    = isset($_GET['download']);
$disposition = $download ? 'attachment' : 'inline';

$action = $download ? 'download' : 'view';
audit_log($pdo, $user_id, $action, 'Fichier : ' . ($file['display_name'] ?: basename($file['file_path'])));

header('Content-Type: ' . $mime);
header('Content-Disposition: ' . $disposition . '; filename="' . basename($file['file_path']) . '"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
