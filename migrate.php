<?php

require_once __DIR__ . '/config/db.php';

$migrations = [
    
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS blocked_until DATETIME DEFAULT NULL",
    "ALTER TABLE users MODIFY COLUMN pin VARCHAR(255) NOT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS secret_question1 VARCHAR(255)",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS secret_answer1 VARCHAR(255)",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS secret_question2 VARCHAR(255)",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS secret_answer2 VARCHAR(255)",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS secret_question3 VARCHAR(255)",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS secret_answer3 VARCHAR(255)",

    
    "ALTER TABLE files ADD COLUMN IF NOT EXISTS category VARCHAR(50) DEFAULT 'Autres'",
    "ALTER TABLE files ADD COLUMN IF NOT EXISTS display_name VARCHAR(80) DEFAULT NULL",

    
    "CREATE TABLE IF NOT EXISTS webauthn_credentials (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        credential_id VARCHAR(512) NOT NULL UNIQUE,
        public_key TEXT NOT NULL,
        algo INT DEFAULT -7,
        sign_count INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )",

    
    "CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token VARCHAR(64) NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL,
        used TINYINT(1) DEFAULT 0,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )",
    "CREATE TABLE IF NOT EXISTS shared_links (
        id INT AUTO_INCREMENT PRIMARY KEY,
        file_id INT NOT NULL,
        user_id INT NOT NULL,
        token VARCHAR(64) NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL,
        FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )",
    "CREATE TABLE IF NOT EXISTS audit_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        action VARCHAR(50),
        detail VARCHAR(255),
        ip VARCHAR(45),
        date DATETIME,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )",
];

$results = [];
foreach ($migrations as $sql) {
    try {
        $pdo->exec($sql);
        $results[] = ['ok' => true, 'sql' => $sql];
    } catch (PDOException $e) {
        $results[] = ['ok' => false, 'sql' => $sql, 'error' => $e->getMessage()];
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Migration</title>
    <style>
        body { font-family: monospace; background: #111; color: #eee; padding: 2rem; }
        .ok  { color: #00e676; }
        .err { color: #ff5252; }
        .sql { color: #888; font-size: 0.85em; margin-left: 1rem; }
        .done { background: #1a3a1a; border: 1px solid #00e676; border-radius: 8px; padding: 1rem 1.5rem; margin-top: 1.5rem; color: #00e676; font-size: 1.2em; }
        .warn { background: #3a2a00; border: 1px solid #ffb300; border-radius: 8px; padding: 1rem 1.5rem; margin-top: 1rem; color: #ffb300; }
    </style>
</head>
<body>
<h2>Migration base de données</h2>
<?php foreach ($results as $r): ?>
    <div>
        <span class="<?= $r['ok'] ? 'ok' : 'err' ?>"><?= $r['ok'] ? '✓' : '✗' ?></span>
        <span class="sql"><?= htmlspecialchars(substr($r['sql'], 0, 80)) ?>...</span>
        <?php if (!$r['ok']): ?>
            <br><span class="err" style="margin-left:2rem;">→ <?= htmlspecialchars($r['error']) ?></span>
        <?php endif; ?>
    </div>
<?php endforeach; ?>

<div class="done">✓ Migration terminée.</div>
<div class="warn">⚠️ <b>Supprime ce fichier immédiatement après :</b> <code>migrate.php</code></div>
</body>
</html>
