<?php
session_start();

if (
    !isset($_SESSION['user_id']) ||
    empty($_SESSION['otp_validated']) ||
    empty($_SESSION['pin_validated'])
) {
    
    header('Content-Type: image/png');
    readfile(__DIR__ . '/assets/default_profile.png');
    exit;
}

$user_id = $_SESSION['user_id'];

$profile_path = null;
foreach (['jpg', 'png'] as $ext) {
    $path = __DIR__ . "/uploads/profile_$user_id.$ext";
    if (file_exists($path)) {
        $profile_path = $path;
        break;
    }
}

if (!$profile_path) {
    header('Content-Type: image/png');
    readfile(__DIR__ . '/assets/default_profile.png');
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($profile_path);
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($profile_path));
readfile($profile_path);
exit;
