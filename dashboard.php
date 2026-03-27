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
$email   = $_SESSION['email'] ?? '';

define('ALLOWED_DOC_MIMES',   ['application/pdf', 'image/jpeg', 'image/png']);
define('ALLOWED_PHOTO_MIMES', ['image/jpeg', 'image/png']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_file'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) { $_SESSION['message'] = "Erreur de sécurité."; header('Location: dashboard.php'); exit; }
    $fid = filter_input(INPUT_POST, 'delete_file', FILTER_VALIDATE_INT);
    if ($fid) {
        $req = $pdo->prepare("SELECT * FROM files WHERE id = ? AND user_id = ?");
        $req->execute([$fid, $user_id]);
        $file = $req->fetch();
        if ($file) {
            $fp = __DIR__ . '/' . $file['file_path'];
            if (file_exists($fp)) unlink($fp);
            $pdo->prepare("DELETE FROM files WHERE id = ?")->execute([$fid]);
            audit_log($pdo, $user_id, 'delete', 'Fichier supprimé : ' . ($file['display_name'] ?: basename($file['file_path'])));
            $_SESSION['message'] = "Fichier supprimé.";
        }
    }
    header('Location: dashboard.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) { $_SESSION['message'] = "Erreur de sécurité."; header('Location: dashboard.php'); exit; }
    $ids     = $_POST['file_ids'] ?? [];
    $deleted = 0;
    foreach ($ids as $fid) {
        $fid = (int)$fid;
        if (!$fid) continue;
        $req = $pdo->prepare("SELECT * FROM files WHERE id = ? AND user_id = ?");
        $req->execute([$fid, $user_id]);
        $file = $req->fetch();
        if ($file) {
            $fp = __DIR__ . '/' . $file['file_path'];
            if (file_exists($fp)) unlink($fp);
            $pdo->prepare("DELETE FROM files WHERE id = ?")->execute([$fid]);
            $deleted++;
        }
    }
    if ($deleted) audit_log($pdo, $user_id, 'bulk_delete', "$deleted fichier(s) supprimé(s)");
    $_SESSION['message'] = "$deleted fichier(s) supprimé(s).";
    header('Location: dashboard.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['download_all'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) { $_SESSION['message'] = "Erreur de sécurité."; header('Location: dashboard.php'); exit; }
    $req = $pdo->prepare("SELECT id, file_path FROM files WHERE user_id = ?");
    $req->execute([$user_id]);
    $all_files = $req->fetchAll();
    $zip     = new ZipArchive();
    $tmp_zip = tempnam(sys_get_temp_dir(), 'zip');
    if ($zip->open($tmp_zip, ZipArchive::CREATE) === true) {
        foreach ($all_files as $f) {
            $path = __DIR__ . '/' . $f['file_path'];
            if (file_exists($path)) $zip->addFile($path, basename($f['file_path']));
        }
        $zip->close();
        audit_log($pdo, $user_id, 'download_all', 'Export ZIP de tous les documents');
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="documents_' . date('Ymd_His') . '.zip"');
        header('Content-Length: ' . filesize($tmp_zip));
        readfile($tmp_zip);
        unlink($tmp_zip);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_profile'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) { $_SESSION['message'] = "Erreur de sécurité."; header('Location: dashboard.php'); exit; }
    if (isset($_FILES['profile_photo'])) {
        $photo = $_FILES['profile_photo'];
        if ($photo['error'] === 0 && $photo['size'] < 2 * 1024 * 1024) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($photo['tmp_name']);
            $ext_map_photo = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
            if (isset($ext_map_photo[$mime])) {
                
                foreach (['jpg', 'png'] as $old_ext) { $old = __DIR__ . "/uploads/profile_$user_id.$old_ext"; if (file_exists($old)) unlink($old); }
                $ext = $ext_map_photo[$mime];
                move_uploaded_file($photo['tmp_name'], __DIR__ . "/uploads/profile_$user_id.$ext");
                audit_log($pdo, $user_id, 'profile_update', 'Photo de profil mise à jour');
                $_SESSION['message'] = "Photo de profil mise à jour.";
            } else { $_SESSION['message'] = "Erreur : photo non valide (jpg, png)."; }
        } else { $_SESSION['message'] = "Erreur : photo non valide (<2Mo)."; }
    }
    header('Location: dashboard.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_file'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) { $_SESSION['message'] = "Erreur de sécurité."; header('Location: dashboard.php'); exit; }
    $file        = $_FILES['file'] ?? null;
    $custom_name = trim($_POST['custom_name'] ?? '');
    $category    = $_POST['category'] ?? 'Autres';
    if (!in_array($category, get_categories())) $category = 'Autres';
    if ($file && $file['error'] === 0 && $file['size'] < 5 * 1024 * 1024) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);
        if (in_array($mime, ALLOWED_DOC_MIMES)) {
            $ext_map  = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
            $ext      = $ext_map[$mime];
            $filename = uniqid('doc_', true) . '.' . $ext;
            $path     = 'uploads/' . $filename;
            if (!is_dir(__DIR__ . '/uploads')) mkdir(__DIR__ . '/uploads', 0755);
            move_uploaded_file($file['tmp_name'], __DIR__ . '/' . $path);
            $display_name = $custom_name !== '' ? substr($custom_name, 0, 80) : basename($file['name']);
            $pdo->prepare("INSERT INTO files (user_id, file_path, upload_date, display_name, category) VALUES (?, ?, NOW(), ?, ?)")
                ->execute([$user_id, $path, $display_name, $category]);
            audit_log($pdo, $user_id, 'upload', 'Upload : ' . $display_name . " [$category]");
            $_SESSION['message'] = "Document uploadé avec succès.";
        } else { $_SESSION['message'] = "Type de fichier non autorisé (pdf, jpg, png)."; }
    } else { $_SESSION['message'] = "Fichier non valide (<5Mo)."; }
    header('Location: dashboard.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rename_file'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) { $_SESSION['message'] = "Erreur de sécurité."; header('Location: dashboard.php'); exit; }
    $fid      = filter_input(INPUT_POST, 'rename_file', FILTER_VALIDATE_INT);
    $new_name = trim($_POST['new_name'] ?? '');
    if ($fid && $new_name !== '') {
        $pdo->prepare("UPDATE files SET display_name = ? WHERE id = ? AND user_id = ?")
            ->execute([substr($new_name, 0, 80), $fid, $user_id]);
        audit_log($pdo, $user_id, 'rename', "Fichier #$fid renommé en : $new_name");
        $_SESSION['message'] = "Fichier renommé.";
    }
    header('Location: dashboard.php'); exit;
}

$req = $pdo->prepare("SELECT attempts, blocked, blocked_until FROM users WHERE id = ?");
$req->execute([$user_id]);
$user = $req->fetch();

$req = $pdo->prepare("SELECT date, ip FROM logs WHERE user_id = ? AND status = 'success' ORDER BY date DESC LIMIT 1");
$req->execute([$user_id]);
$last_conn = $req->fetch();

$req = $pdo->prepare("SELECT COUNT(*) FROM logs WHERE user_id = ? AND status = 'fail' AND date > DATE_SUB(NOW(), INTERVAL 1 DAY)");
$req->execute([$user_id]);
$fail_count = $req->fetchColumn();

$req = $pdo->prepare("SELECT id, file_path, upload_date, display_name AS shown_name, category FROM files WHERE user_id = ? ORDER BY upload_date DESC");
$req->execute([$user_id]);
$files = $req->fetchAll();

$req = $pdo->prepare("SELECT * FROM logs WHERE user_id = ? ORDER BY date DESC LIMIT 10");
$req->execute([$user_id]);
$logs = $req->fetchAll();

$req = $pdo->prepare("SELECT * FROM audit_logs WHERE user_id = ? ORDER BY date DESC LIMIT 15");
$req->execute([$user_id]);
$audit_logs = $req->fetchAll();

$status = $user['blocked'] ? 'Bloqué 🔒' : 'Actif 🟢';
$alert  = '';
if (isset($_SESSION['last_ip']) && $_SESSION['last_ip'] !== ($_SERVER['REMOTE_ADDR'] ?? '')) {
    $alert = "Nouvelle IP détectée : activité suspecte !";
}

$scan_done = isset($_SESSION['scan_done']);
if (!$scan_done) $_SESSION['scan_done'] = true;

$categories = get_categories();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Triple Auth</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/fontawesome/css/all.min.css">
    <style>
        body { background:#181828; font-family:'Segoe UI',Arial,sans-serif; }
        .dashboard-container { max-width:980px; margin:32px auto; padding:0 12px; }

        /* header */
        .header-card { background:#23234a; border-radius:14px; padding:20px 28px; display:flex; align-items:center; justify-content:space-between; box-shadow:0 0 24px #2d1a4d88; margin-bottom:20px; flex-wrap:wrap; gap:12px; }
        .header-left { display:flex; align-items:center; gap:16px; }
        .profile-photo { width:56px; height:56px; border-radius:50%; object-fit:cover; border:2px solid #8f5fff; }
        .welcome-msg { color:#8f5fff; font-size:1.05em; font-weight:600; }
        .security-badge { background:#1e90ff; color:#fff; border-radius:20px; padding:5px 14px; font-size:0.9em; font-weight:bold; display:inline-flex; align-items:center; gap:6px; margin-top:4px; }
        .profile-upload-form { display:flex; gap:6px; align-items:center; margin-top:6px; }
        .profile-upload-btn { background:#23234a; border:1px solid #8f5fff; color:#8f5fff; border-radius:6px; padding:4px 10px; font-size:0.85em; cursor:pointer; }
        .logout-btn { background:#8f5fff; color:#fff; border:none; border-radius:6px; padding:8px 16px; cursor:pointer; font-size:0.95em; }
        .logout-btn:hover { background:#1e90ff; }

        /* barre de timeout */
        #timeout-bar { background:#23234a; border-radius:8px; padding:6px 14px; font-size:0.85em; color:#888; margin-bottom:14px; display:flex; align-items:center; gap:8px; }
        #timeout-bar.warning { background:#3a2a00; color:#ffb300; }

        /* cards */
        .cards-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px; }
        .card { background:#23234a; border-radius:12px; box-shadow:0 2px 8px #1e90ff22; padding:22px 18px; }
        .card-title { color:#8f5fff; font-size:1.1em; font-weight:bold; margin-bottom:14px; display:flex; align-items:center; gap:8px; }
        .security-info { color:#e0e0ff; font-size:0.97em; margin-bottom:8px; }
        .status-badge { display:inline-block; padding:3px 10px; border-radius:10px; font-size:0.9em; font-weight:bold; }
        .status-badge.active { background:#00e676; color:#000; }
        .status-badge.blocked { background:#ff5252; color:#fff; }

        /* upload */
        .upload-row { display:flex; flex-wrap:wrap; gap:8px; align-items:center; margin-bottom:10px; }
        .upload-row input[type="text"] { background:#181829; border:1px solid #8f5fff44; border-radius:6px; color:#fff; padding:6px 10px; font-size:0.9em; flex:1; min-width:120px; }
        .upload-row select { background:#181829; border:1px solid #8f5fff44; border-radius:6px; color:#fff; padding:6px 10px; font-size:0.9em; }
        .upload-btn { background:linear-gradient(90deg,#8f5fff,#1e90ff); color:#fff; border:none; border-radius:6px; padding:8px 16px; cursor:pointer; font-size:0.9em; }
        .download-all-btn { background:#23234a; color:#8f5fff; border:1px solid #8f5fff; border-radius:6px; padding:6px 14px; font-size:0.9em; cursor:pointer; margin-bottom:10px; }
        .download-all-btn:hover { background:#8f5fff; color:#fff; }

        /* filtres catégories */
        .cat-filters { display:flex; flex-wrap:wrap; gap:6px; margin-bottom:14px; }
        .cat-btn { border:none; border-radius:20px; padding:4px 14px; font-size:0.85em; cursor:pointer; font-weight:600; opacity:.65; transition:opacity .15s; }
        .cat-btn.active,.cat-btn:hover { opacity:1; }

        /* grille fichiers */
        .files-grid { display:flex; flex-wrap:wrap; gap:14px; }
        .file-card { background:#2d1a4d; border-radius:10px; padding:12px 10px; display:flex; flex-direction:column; align-items:center; width:155px; box-shadow:0 2px 8px #1e90ff22; transition:transform .12s; position:relative; }
        .file-card:hover { transform:scale(1.03); }
        .file-card .cb { position:absolute; top:6px; left:6px; }
        .file-thumb { width:100%; height:70px; object-fit:cover; border-radius:6px; margin-bottom:6px; }
        .file-icon { font-size:2.2em; margin-bottom:6px; }
        .file-name { color:#e0e0ff; font-size:0.88em; font-weight:bold; text-align:center; word-break:break-all; margin-bottom:3px; }
        .file-date { color:#8f5fff; font-size:0.78em; margin-bottom:5px; }
        .cat-badge { border-radius:10px; padding:2px 8px; font-size:0.75em; font-weight:600; color:#fff; margin-bottom:6px; }
        .file-actions { display:flex; gap:6px; flex-wrap:wrap; justify-content:center; }
        .file-btn { border:none; border-radius:5px; padding:4px 8px; font-size:0.82em; cursor:pointer; color:#fff; }
        .file-btn.view  { background:#1e90ff; }
        .file-btn.dl    { background:#00c875; }
        .file-btn.share { background:#8f5fff; }
        .file-btn.del   { background:#ff5252; }
        .rename-form { margin-top:6px; display:none; flex-direction:column; gap:4px; width:100%; }
        .rename-form input { background:#181829; border:1px solid #8f5fff; border-radius:5px; color:#fff; padding:4px 7px; font-size:0.82em; width:100%; box-sizing:border-box; }
        .rename-form button { background:#8f5fff; color:#fff; border:none; border-radius:5px; padding:3px 8px; font-size:0.8em; cursor:pointer; }

        /* modal partage */
        .modal-overlay { display:none; position:fixed; top:0;left:0;right:0;bottom:0; background:#000a; z-index:1000; align-items:center; justify-content:center; }
        .modal-overlay.show { display:flex; }
        .modal-box { background:#23234a; border-radius:14px; padding:2rem; max-width:420px; width:90%; }
        .modal-box h3 { color:#8f5fff; margin-bottom:1rem; }
        .modal-box select, .modal-box input { background:#181829; border:1px solid #8f5fff44; border-radius:6px; color:#fff; padding:8px; width:100%; box-sizing:border-box; margin-bottom:10px; }
        .modal-box .btn-row { display:flex; gap:8px; }
        .modal-box button { flex:1; padding:8px; border:none; border-radius:6px; cursor:pointer; font-weight:600; }
        .btn-confirm { background:linear-gradient(90deg,#8f5fff,#3e8eff); color:#fff; }
        .btn-cancel  { background:#181829; color:#888; border:1px solid #444 !important; }

        /* résultat partage */
        #share-result { background:#1a2a1a; border:1px solid #00c875; border-radius:10px; padding:14px 18px; margin-bottom:18px; }
        #share-result h4 { color:#00c875; margin:0 0 8px 0; }
        #share-result .share-url { background:#181829; border-radius:6px; padding:8px 10px; color:#8f5fff; font-size:0.9em; word-break:break-all; margin-bottom:8px; }
        .copy-btn { background:#8f5fff; color:#fff; border:none; border-radius:5px; padding:5px 12px; cursor:pointer; font-size:0.85em; }

        /* barre suppression en masse */
        .bulk-bar { background:#3a1a1a; border-radius:8px; padding:8px 14px; display:none; align-items:center; gap:10px; margin-bottom:10px; }
        .bulk-bar.show { display:flex; }
        .bulk-delete-btn { background:#ff5252; color:#fff; border:none; border-radius:6px; padding:6px 14px; cursor:pointer; font-weight:600; }

        /* tableaux */
        .logs-table { width:100%; border-collapse:collapse; }
        .logs-table th,.logs-table td { padding:8px 8px; text-align:left; }
        .logs-table th { background:#181828; color:#8f5fff; font-weight:bold; font-size:0.9em; }
        .logs-table tr:nth-child(even) { background:#23234a; }
        .logs-table tr:nth-child(odd) { background:#2d1a4d; }
        .logs-table td { font-size:0.9em; color:#e0e0ff; }
        .s-success { color:#00e676; font-weight:bold; }
        .s-fail    { color:#ff5252; font-weight:bold; }
        .audit-action { display:inline-block; padding:2px 8px; border-radius:10px; font-size:0.8em; font-weight:600; }

        /* alertes */
        .alert-card  { background:#ff2d2d; color:#fff; border-radius:10px; padding:10px 16px; margin-bottom:16px; font-weight:bold; }
        .message     { background:#2d1a4d; color:#8f5fff; border-radius:8px; padding:10px 14px; margin-bottom:16px; }

        /* overlay scan */
        #scan-overlay { position:fixed; top:0;left:0;right:0;bottom:0; background:#181828ee; z-index:9999; display:flex; align-items:center; justify-content:center; flex-direction:column; font-size:1.4em; color:#8f5fff; }
        .scan-bar { width:200px; height:10px; background:#23234a; border-radius:8px; margin-top:16px; overflow:hidden; }
        .scan-bar-inner { height:100%; width:0; background:linear-gradient(90deg,#8f5fff,#1e90ff); border-radius:8px; animation:scanBarAnim 1.2s linear forwards; }
        @keyframes scanBarAnim { to { width:100%; } }

        @media(max-width:860px) { .cards-grid{grid-template-columns:1fr;} }
        @media(max-width:520px) { .file-card{width:130px;} .dashboard-container{padding:0 4px;} }
    </style>
</head>
<body>

<?php if (!$scan_done): ?>
<div id="scan-overlay" style="display:none;">
    <div><i class="fa-solid fa-shield-halved fa-bounce"></i> Scan de sécurité...</div>
    <div class="scan-bar"><div class="scan-bar-inner"></div></div>
</div>
<?php endif; ?>

<div class="dashboard-container">

    
    <div class="header-card">
        <div class="header-left">
            <img src="serve_profile.php" alt="Profil" class="profile-photo">
            <div>
                <div class="welcome-msg">Bienvenue, <b><?= htmlspecialchars($email) ?></b></div>
                <div class="security-badge"><i class="fa-solid fa-lock"></i> 3FA activé</div>
                <form class="profile-upload-form" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="file" name="profile_photo" accept=".jpg,.jpeg,.png" required style="font-size:0.8em;color:#bdbde6;">
                    <button class="profile-upload-btn" type="submit" name="upload_profile"><i class="fa-solid fa-camera"></i> Changer</button>
                </form>
            </div>
        </div>
        <div>
            <a href="logout.php"><button class="logout-btn"><i class="fa-solid fa-right-from-bracket"></i> Déconnexion</button></a>
        </div>
    </div>

    
    <div id="timeout-bar"><i class="fa-solid fa-clock"></i> <span id="timeout-text">Session active</span></div>

    <?php if ($alert): ?>
        <div class="alert-card"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($alert) ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['message'])): ?>
        <div class="message"><?= htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); ?></div>
    <?php endif; ?>

    
    <?php if (isset($_SESSION['share_url'])): ?>
    <div id="share-result">
        <h4><i class="fa-solid fa-link"></i> Lien de partage généré</h4>
        <div class="share-url" id="share-url-text"><?= htmlspecialchars($_SESSION['share_url']) ?></div>
        <small style="color:#888;">Expire le <?= htmlspecialchars($_SESSION['share_expires']) ?></small><br><br>
        <button class="copy-btn" onclick="copyShareUrl()"><i class="fa-solid fa-copy"></i> Copier le lien</button>
    </div>
    <?php unset($_SESSION['share_url'], $_SESSION['share_expires']); ?>
    <?php endif; ?>

    <div class="cards-grid">
        
        <div class="card">
            <div class="card-title"><i class="fa-solid fa-shield-halved"></i> Sécurité du compte</div>
            <div class="security-info"><b>Dernière connexion :</b><br>
                <?= $last_conn ? htmlspecialchars(date('d/m/Y H:i', strtotime($last_conn['date']))) . ' depuis ' . htmlspecialchars($last_conn['ip']) : 'Aucune' ?>
            </div>
            <div class="security-info"><b>Tentatives échouées (24h) :</b> <?= (int)$fail_count ?></div>
            <div class="security-info"><b>Statut :</b>
                <span class="status-badge <?= $user['blocked'] ? 'blocked' : 'active' ?>"><?= htmlspecialchars($status) ?></span>
            </div>
        </div>

        
        <div class="card">
            <div class="card-title"><i class="fa-solid fa-file-medical"></i> Uploader un document</div>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <div class="upload-row">
                    <input type="text" name="custom_name" maxlength="80" placeholder="Nom du fichier (optionnel)">
                    <select name="category">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="upload-row">
                    <input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png" required style="color:#bdbde6;font-size:0.9em;flex:1;">
                    <button class="upload-btn" type="submit" name="upload_file"><i class="fa-solid fa-upload"></i> Uploader</button>
                </div>
            </form>
            <form method="post" style="margin-top:8px;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <button type="submit" name="download_all" class="download-all-btn"><i class="fa-solid fa-download"></i> Tout télécharger (ZIP)</button>
            </form>
        </div>
    </div>

    
    <div class="card" style="margin-bottom:20px;">
        <div class="card-title"><i class="fa-solid fa-folder-open"></i> Mes documents (<?= count($files) ?>)</div>

        
        <div class="cat-filters">
            <button class="cat-btn active" data-cat="all" style="background:#444;color:#fff;" onclick="filterCat('all',this)">Tous</button>
            <?php foreach ($categories as $cat):
                $color = category_color($cat); ?>
                <button class="cat-btn" data-cat="<?= htmlspecialchars($cat) ?>"
                    style="background:<?= $color ?>;color:#fff;"
                    onclick="filterCat('<?= htmlspecialchars(addslashes($cat)) ?>',this)"><?= htmlspecialchars($cat) ?></button>
            <?php endforeach; ?>
        </div>

        
        <div class="bulk-bar" id="bulk-bar">
            <span id="bulk-count" style="color:#ff5252;font-weight:bold;">0 sélectionné(s)</span>
            <form method="post" id="bulk-form" onsubmit="return confirm('Supprimer les fichiers sélectionnés ?');">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <div id="bulk-inputs"></div>
                <button type="submit" name="bulk_delete" class="bulk-delete-btn"><i class="fa-solid fa-trash"></i> Supprimer la sélection</button>
            </form>
        </div>

        <div class="files-grid" id="files-grid">
            <?php foreach ($files as $file):
                $ext    = strtolower(pathinfo($file['file_path'], PATHINFO_EXTENSION));
                $is_img = in_array($ext, ['jpg', 'jpeg', 'png']);
                $color  = category_color($file['category'] ?? 'Autres');
            ?>
            <div class="file-card" data-cat="<?= htmlspecialchars($file['category'] ?? 'Autres') ?>">
                <input type="checkbox" class="cb file-cb" data-id="<?= $file['id'] ?>" onchange="updateBulk()">
                <?php if ($is_img): ?>
                    <img src="serve_file.php?id=<?= $file['id'] ?>" class="file-thumb" alt="aperçu">
                <?php else: ?>
                    <div class="file-icon"><i class="fa-solid fa-file-pdf" style="color:#ff5252;"></i></div>
                <?php endif; ?>
                <div class="cat-badge" style="background:<?= $color ?>"><?= htmlspecialchars($file['category'] ?? 'Autres') ?></div>
                <div class="file-name"><?= htmlspecialchars($file['shown_name']) ?></div>
                <div class="file-date"><?= htmlspecialchars(date('d/m/Y', strtotime($file['upload_date']))) ?></div>
                <div class="file-actions">
                    <a href="serve_file.php?id=<?= $file['id'] ?>" target="_blank"><button class="file-btn view" title="Voir"><i class="fa-solid fa-eye"></i></button></a>
                    <a href="serve_file.php?id=<?= $file['id'] ?>&download=1"><button class="file-btn dl" title="Télécharger"><i class="fa-solid fa-download"></i></button></a>
                    <button class="file-btn share" title="Partager" onclick="openShareModal(<?= $file['id'] ?>, '<?= htmlspecialchars(addslashes($file['shown_name'])) ?>')"><i class="fa-solid fa-share-nodes"></i></button>
                    <button class="file-btn" style="background:#888;" title="Renommer" onclick="toggleRename(this)"><i class="fa-solid fa-pen"></i></button>
                    <form method="post" style="display:inline;" onsubmit="return confirm('Supprimer ?');">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="delete_file" value="<?= $file['id'] ?>">
                        <button type="submit" class="file-btn del" title="Supprimer"><i class="fa-solid fa-trash"></i></button>
                    </form>
                </div>
                
                <form method="post" class="rename-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="rename_file" value="<?= $file['id'] ?>">
                    <input type="text" name="new_name" value="<?= htmlspecialchars($file['shown_name']) ?>" maxlength="80" required>
                    <button type="submit">OK</button>
                </form>
            </div>
            <?php endforeach; ?>
            <?php if (empty($files)): ?>
                <div style="color:#888;padding:20px;">Aucun document pour l'instant.</div>
            <?php endif; ?>
        </div>
    </div>

    
    <div class="card" style="margin-bottom:20px;">
        <div class="card-title"><i class="fa-solid fa-right-to-bracket"></i> Historique des connexions</div>
        <table class="logs-table">
            <tr><th>Date</th><th>IP</th><th>Statut</th></tr>
            <?php foreach ($logs as $log): ?>
            <tr>
                <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($log['date']))) ?></td>
                <td><?= htmlspecialchars($log['ip']) ?></td>
                <td class="<?= $log['status'] === 'success' ? 's-success' : 's-fail' ?>">
                    <?= $log['status'] === 'success' ? '<i class="fa-solid fa-check"></i> Succès' : '<i class="fa-solid fa-xmark"></i> Échec' ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($logs)): ?><tr><td colspan="3" style="color:#8f5fff;text-align:center;">Aucune entrée.</td></tr><?php endif; ?>
        </table>
    </div>

    
    <div class="card" style="margin-bottom:32px;">
        <div class="card-title"><i class="fa-solid fa-clipboard-list"></i> Journal d'activité</div>
        <table class="logs-table">
            <tr><th>Date</th><th>Action</th><th>Détail</th><th>IP</th></tr>
            <?php
            $action_colors = [
                'upload' => '#1e90ff', 'download' => '#00c875', 'delete' => '#ff5252',
                'bulk_delete' => '#ff5252', 'share' => '#8f5fff', 'view' => '#607d8b',
                'rename' => '#ff9800', 'profile_update' => '#e91e63', 'download_all' => '#00c875',
            ];
            foreach ($audit_logs as $al):
                $c = $action_colors[$al['action']] ?? '#607d8b';
            ?>
            <tr>
                <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($al['date']))) ?></td>
                <td><span class="audit-action" style="background:<?= $c ?>;color:#fff;"><?= htmlspecialchars($al['action']) ?></span></td>
                <td><?= htmlspecialchars($al['detail']) ?></td>
                <td><?= htmlspecialchars($al['ip']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($audit_logs)): ?><tr><td colspan="4" style="color:#8f5fff;text-align:center;">Aucune activité enregistrée.</td></tr><?php endif; ?>
        </table>
    </div>
</div>

<div class="modal-overlay" id="share-modal">
    <div class="modal-box">
        <h3><i class="fa-solid fa-share-nodes"></i> Partager un document</h3>
        <p id="share-file-name" style="color:#bdbde6;margin-bottom:1rem;"></p>
        <form method="post" action="share_file.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="file_id" id="share-file-id">
            <label style="color:#bdbde6;font-size:0.9em;">Durée de validité :</label>
            <select name="duration">
                <option value="1">1 heure</option>
                <option value="6">6 heures</option>
                <option value="24" selected>24 heures</option>
                <option value="72">3 jours</option>
                <option value="168">7 jours</option>
            </select>
            <div class="btn-row">
                <button type="button" class="btn-cancel" onclick="closeShareModal()">Annuler</button>
                <button type="submit" class="btn-confirm"><i class="fa-solid fa-link"></i> Générer le lien</button>
            </div>
        </form>
    </div>
</div>

<script>
// overlay de scan au premier chargement
window.onload = function() {
    <?php if (!$scan_done): ?>
    var ov = document.getElementById('scan-overlay');
    ov.style.display = 'flex';
    setTimeout(function(){ ov.style.display='none'; }, 1300);
    <?php endif; ?>
    startTimeoutBar();
};

// compte à rebours session (30min)
function startTimeoutBar() {
    var total = 1800, left = total;
    var bar = document.getElementById('timeout-bar');
    var txt = document.getElementById('timeout-text');
    var iv = setInterval(function(){
        left--;
        if (left <= 0) { clearInterval(iv); window.location.href = 'login.php?timeout=1'; return; }
        var m = Math.floor(left/60), s = left%60;
        txt.textContent = 'Session expire dans ' + m + 'min ' + (s<10?'0':'') + s + 's';
        if (left <= 300) { bar.classList.add('warning'); }
    }, 1000);
}

// filtre par catégorie
function filterCat(cat, btn) {
    document.querySelectorAll('.cat-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.file-card').forEach(c => {
        c.style.display = (cat === 'all' || c.dataset.cat === cat) ? '' : 'none';
    });
    updateBulk();
}

// gestion sélection pour suppression en masse
function updateBulk() {
    var checked = Array.from(document.querySelectorAll('.file-cb:checked'));
    var bar     = document.getElementById('bulk-bar');
    var inputs  = document.getElementById('bulk-inputs');
    document.getElementById('bulk-count').textContent = checked.length + ' sélectionné(s)';
    bar.classList.toggle('show', checked.length > 0);
    inputs.innerHTML = '';
    checked.forEach(function(cb){
        var inp = document.createElement('input');
        inp.type='hidden'; inp.name='file_ids[]'; inp.value=cb.dataset.id;
        inputs.appendChild(inp);
    });
}

// toggle formulaire renommer
function toggleRename(btn) {
    var form = btn.closest('.file-card').querySelector('.rename-form');
    form.style.display = form.style.display === 'flex' ? 'none' : 'flex';
}

// modal partage
function openShareModal(fileId, fileName) {
    document.getElementById('share-file-id').value = fileId;
    document.getElementById('share-file-name').textContent = 'Fichier : ' + fileName;
    document.getElementById('share-modal').classList.add('show');
}
function closeShareModal() {
    document.getElementById('share-modal').classList.remove('show');
}

// copier le lien
function copyShareUrl() {
    var url = document.getElementById('share-url-text').textContent;
    navigator.clipboard.writeText(url).then(function(){
        document.querySelector('.copy-btn').textContent = '✓ Copié !';
    });
}
</script>
</body>
</html>
