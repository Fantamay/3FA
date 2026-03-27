<?php
require_once __DIR__ . '/config/db.php';

$token = trim($_GET['token'] ?? '');

if (!$token) {
    http_response_code(400);
    exit('Lien invalide.');
}

$req = $pdo->prepare("
    SELECT sl.*, f.file_path, f.display_name
    FROM shared_links sl
    JOIN files f ON f.id = sl.file_id
    WHERE sl.token = ? AND sl.expires_at > NOW()
");
$req->execute([$token]);
$link = $req->fetch();

if (!$link) {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <title>Lien expiré</title>
        <style>
            body { background:#181829; color:#f5f6fa; font-family:Arial,sans-serif; display:flex; align-items:center; justify-content:center; min-height:100vh; }
            .box { background:#23234a; padding:2rem; border-radius:14px; text-align:center; }
            h2 { color:#ff5252; }
        </style>
    </head>
    <body>
    <div class="box">
        <h2>🔗 Lien expiré ou invalide</h2>
        <p>Ce lien de partage n'est plus valide.</p>
    </div>
    </body>
    </html>
    <?php
    exit;
}

$path = __DIR__ . '/' . $link['file_path'];
if (!file_exists($path)) {
    http_response_code(404);
    exit('Fichier introuvable.');
}

$download = isset($_GET['download']);
$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mime     = $finfo->file($path);
$filename = $link['display_name'] ?: basename($link['file_path']);

if ($download) {
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($filename) ?> – Triple Auth</title>
    <style>
        body { background:#181829; color:#f5f6fa; font-family:Arial,sans-serif; display:flex; align-items:center; justify-content:center; min-height:100vh; }
        .box { background:#23234a; padding:2rem; border-radius:14px; text-align:center; max-width:600px; width:100%; }
        h2 { color:#8f5fff; margin-bottom:1rem; }
        .expires { color:#888; font-size:0.9em; margin-bottom:1.5rem; }
        .btn {
            display:inline-block; padding:0.7rem 1.8rem; border-radius:30px;
            background:linear-gradient(90deg,#8f5fff,#3e8eff); color:#fff;
            text-decoration:none; font-weight:600; font-size:1rem;
        }
        .preview { max-width:100%; max-height:400px; border-radius:10px; margin-bottom:1.2rem; }
    </style>
</head>
<body>
<div class="box">
    <h2>📄 <?= htmlspecialchars($filename) ?></h2>
    <p class="expires">Lien valide jusqu'au <?= htmlspecialchars(date('d/m/Y H:i', strtotime($link['expires_at']))) ?></p>

    <?php if (in_array($mime, ['image/jpeg', 'image/png'])): ?>
        <img src="?token=<?= htmlspecialchars($token) ?>&preview=1" class="preview" alt="Aperçu">
    <?php elseif ($mime === 'application/pdf'): ?>
        <p style="font-size:3em;">📑</p>
    <?php endif; ?>

    <a href="?token=<?= htmlspecialchars($token) ?>&download=1" class="btn">⬇️ Télécharger</a>
</div>
</body>
</html>
<?php

if (isset($_GET['preview'])) {
    header('Content-Type: ' . $mime);
    readfile($path);
    exit;
}
