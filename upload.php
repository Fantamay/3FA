<?php
session_start();
require_once __DIR__ . '/config/db.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['otp_validated']) || empty($_SESSION['pin_validated'])) {
    header('Location: login.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$user_id = $_SESSION['user_id'];
$upload_dir = __DIR__ . '/uploads/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755);
}

const ALLOWED_MIMES = ['application/pdf', 'image/jpeg', 'image/png'];
const EXT_MAP       = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['message'] = "Erreur de sécurité.";
        header('Location: upload.php');
        exit;
    }

    $file = $_FILES['file'];
    if ($file['error'] === 0 && $file['size'] < 5 * 1024 * 1024) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);
        if (in_array($mime, ALLOWED_MIMES)) {
            $ext      = EXT_MAP[$mime];
            $filename = uniqid('doc_', true) . '.' . $ext;
            $path     = 'uploads/' . $filename;
            move_uploaded_file($file['tmp_name'], __DIR__ . '/' . $path);
            $stmt = $pdo->prepare("INSERT INTO files (user_id, file_path, upload_date) VALUES (?, ?, NOW())");
            $stmt->execute([$user_id, $path]);
            $_SESSION['message'] = "Document uploadé avec succès.";
            header('Location: dashboard.php');
            exit;
        } else {
            $_SESSION['message'] = "Erreur : type de fichier non autorisé (pdf, jpg, png).";
        }
    } else {
        $_SESSION['message'] = "Erreur : fichier non valide (<5Mo).";
    }
    header('Location: upload.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Uploader un document - Triple Auth</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="container">
    <h1>Uploader un document médical</h1>
    <?php if (isset($_SESSION['message'])): ?>
        <div class="message"><?= htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); ?></div>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png" required>
        <button type="submit">Uploader</button>
    </form>
    <a href="dashboard.php"><button style="background:#23234a;">Retour dashboard</button></a>
</div>
</body>
</html>
