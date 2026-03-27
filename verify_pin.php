<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth_check.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!isset($_SESSION['user_id']) || empty($_SESSION['otp_validated'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

$req = $pdo->prepare("SELECT id FROM webauthn_credentials WHERE user_id = ?");
$req->execute([$user_id]);
$has_webauthn = $req->fetch() !== false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['message'] = "Erreur de sécurité.";
        header('Location: verify_pin.php');
        exit;
    }

    $pin = $_POST['pin'] ?? '';
    if (!preg_match('/^\d{4}$/', $pin)) {
        $_SESSION['message'] = "Le code PIN doit contenir exactement 4 chiffres.";
        header('Location: verify_pin.php');
        exit;
    }

    $req = $pdo->prepare("SELECT pin FROM users WHERE id = ?");
    $req->execute([$user_id]);
    $user = $req->fetch();

    if ($user && password_verify($pin, $user['pin'])) {
        $_SESSION['pin_validated'] = true;
        header('Location: dashboard.php');
        exit;
    } else {
        $_SESSION['message'] = "Code PIN incorrect.";
        header('Location: verify_pin.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification finale - Triple Auth</title>
    <link rel="stylesheet" href="assets/fontawesome/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: linear-gradient(135deg, #181829 0%, #2d1e4f 100%);
            font-family: 'Montserrat', Arial, sans-serif;
            color: #f5f6fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            width: 100%;
            max-width: 440px;
        }
        .step-title {
            text-align: center;
            color: #8f5fff;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 6px;
        }
        h1 {
            text-align: center;
            font-size: 1.5em;
            margin-bottom: 24px;
            color: #fff;
        }
        .card {
            background: #23234a;
            border-radius: 18px;
            padding: 28px 24px;
            box-shadow: 0 4px 24px rgba(143,95,255,0.15);
            margin-bottom: 16px;
        }
        .card-title {
            font-size: 1em;
            font-weight: 700;
            color: #8f5fff;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        /* carte biométrie */
        .bio-card {
            border: 2px solid #8f5fff;
            text-align: center;
        }
        .bio-icon {
            font-size: 3.5em;
            margin: 10px 0 16px;
            display: block;
        }
        .bio-desc {
            color: #bdbde6;
            font-size: 0.9em;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        .btn-bio {
            width: 100%;
            padding: 14px;
            border-radius: 30px;
            border: none;
            font-size: 1em;
            font-weight: 700;
            cursor: pointer;
            background: linear-gradient(90deg, #8f5fff 0%, #3e8eff 100%);
            color: #fff;
            transition: opacity 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .btn-bio:hover { opacity: 0.9; }
        .btn-bio:disabled { opacity: 0.5; cursor: not-allowed; }
        .bio-status {
            margin-top: 12px;
            font-size: 0.88em;
            color: #8f5fff;
            min-height: 20px;
        }
        .bio-status.error { color: #ff5252; }
        .bio-status.success { color: #00e676; }

        /* enregistrement biométrie */
        .register-bio-card {
            border: 1px dashed #8f5fff55;
            text-align: center;
        }
        .register-bio-card .desc {
            color: #888;
            font-size: 0.85em;
            margin-bottom: 14px;
        }
        .btn-register-bio {
            background: transparent;
            border: 1px solid #8f5fff;
            color: #8f5fff;
            padding: 10px 20px;
            border-radius: 30px;
            cursor: pointer;
            font-size: 0.9em;
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn-register-bio:hover { background: #8f5fff; color: #fff; }

        .separator {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #555;
            font-size: 0.85em;
            margin: 4px 0;
        }
        .separator::before, .separator::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #333;
        }

        /* formulaire PIN */
        .pin-input {
            width: 100%;
            padding: 14px;
            background: #181829;
            border: 1px solid #8f5fff44;
            border-radius: 10px;
            color: #fff;
            font-size: 1.3em;
            text-align: center;
            letter-spacing: 0.5em;
            margin-bottom: 14px;
            outline: none;
            transition: border-color 0.2s;
        }
        .pin-input:focus { border-color: #8f5fff; }
        .btn-pin {
            width: 100%;
            padding: 14px;
            border-radius: 30px;
            border: none;
            font-size: 1em;
            font-weight: 700;
            cursor: pointer;
            background: linear-gradient(90deg, #3e8eff 0%, #8f5fff 100%);
            color: #fff;
        }

        .message {
            background: #3a1a1a;
            color: #ff5252;
            padding: 10px 14px;
            border-radius: 8px;
            margin-bottom: 14px;
            font-size: 0.9em;
        }

        .not-supported {
            background: #3a2a00;
            color: #ffb300;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 0.85em;
            margin-bottom: 12px;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="step-title">Étape 3 / 3</div>
    <h1><i class="fas fa-fingerprint"></i> Vérification finale</h1>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="message"><?= htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); ?></div>
    <?php endif; ?>

    
    <?php if ($has_webauthn): ?>
    <div class="card bio-card">
        <span class="bio-icon">🔐</span>
        <div class="card-title" style="justify-content:center;">Authentification biométrique</div>
        <p class="bio-desc">Utilisez votre empreinte digitale, reconnaissance faciale ou code Windows Hello pour vous authentifier.</p>
        <button class="btn-bio" id="btn-bio" onclick="authenticateBiometric()">
            <i class="fas fa-fingerprint"></i> S'authentifier
        </button>
        <div class="bio-status" id="bio-status"></div>
    </div>

    <div class="separator">ou entrer le PIN</div>
    <?php endif; ?>

    
    <div class="card">
        <div class="card-title"><i class="fas fa-lock"></i> Code PIN</div>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="password" name="pin" class="pin-input" placeholder="• • • •"
                   pattern="\d{4}" maxlength="4" inputmode="numeric" required autocomplete="current-password">
            <button type="submit" class="btn-pin">Valider</button>
        </form>
    </div>

    
    <?php if (!$has_webauthn): ?>
    <div class="card register-bio-card" id="register-bio-section">
        <div class="card-title" style="justify-content:center;"><i class="fas fa-fingerprint"></i> Activer la biométrie</div>
        <p class="desc">Enregistrez votre empreinte ou visage pour vous connecter plus rapidement la prochaine fois.</p>
        <div id="not-supported-msg" class="not-supported" style="display:none;">
            ⚠️ WebAuthn non disponible. Assurez-vous d'utiliser Chrome/Edge sur localhost, ou HTTPS en production.
        </div>
        <button class="btn-register-bio" id="btn-register" onclick="registerBiometric()">
            <i class="fas fa-plus"></i> Enregistrer ma biométrie
        </button>
        <div class="bio-status" id="reg-status"></div>
    </div>
    <?php endif; ?>
</div>

<script>
// helpers base64url
function b64u_to_buf(b64url) {
    const b64 = b64url.replace(/-/g,'+').replace(/_/g,'/');
    const bin = atob(b64);
    const buf = new Uint8Array(bin.length);
    for (let i=0;i<bin.length;i++) buf[i] = bin.charCodeAt(i);
    return buf.buffer;
}
function buf_to_b64u(buf) {
    const bytes = new Uint8Array(buf);
    let bin = '';
    for (let b of bytes) bin += String.fromCharCode(b);
    return btoa(bin).replace(/\+/g,'-').replace(/\//g,'_').replace(/=/g,'');
}

// enregistrement biométrique
async function registerBiometric() {
    const btn    = document.getElementById('btn-register');
    const status = document.getElementById('reg-status');

    if (!window.PublicKeyCredential) {
        document.getElementById('not-supported-msg').style.display = 'block';
        return;
    }

    btn.disabled = true;
    status.className = 'bio-status';
    status.textContent = 'Récupération du challenge...';

    try {
        const optsResp = await fetch('webauthn_register.php?action=challenge');
        const opts     = await optsResp.json();
        if (opts.error) throw new Error(opts.error);

        opts.challenge    = b64u_to_buf(opts.challenge);
        opts.user.id      = b64u_to_buf(opts.user.id);
        if (opts.excludeCredentials) {
            opts.excludeCredentials = opts.excludeCredentials.map(c => ({...c, id: b64u_to_buf(c.id)}));
        }

        status.textContent = 'Veuillez utiliser votre biométrie...';

        const cred = await navigator.credentials.create({ publicKey: opts });

        status.textContent = 'Envoi au serveur...';

        const regResp = await fetch('webauthn_register.php?action=register', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                id:                cred.id,
                clientDataJSON:    buf_to_b64u(cred.response.clientDataJSON),
                attestationObject: buf_to_b64u(cred.response.attestationObject),
            })
        });
        const result = await regResp.json();

        if (result.success) {
            status.className = 'bio-status success';
            status.textContent = '✓ Biométrie enregistrée !';
            setTimeout(() => window.location.reload(), 1200);
        } else {
            throw new Error(result.error || 'Erreur inconnue');
        }
    } catch (err) {
        status.className = 'bio-status error';
        if (err.name === 'NotAllowedError') {
            status.textContent = 'Annulé ou biométrie refusée.';
        } else if (err.name === 'InvalidStateError') {
            status.textContent = 'Cet appareil est déjà enregistré.';
        } else {
            status.textContent = 'Erreur : ' + err.message;
        }
        btn.disabled = false;
    }
}

// auth biométrique
async function authenticateBiometric() {
    const btn    = document.getElementById('btn-bio');
    const status = document.getElementById('bio-status');

    if (!window.PublicKeyCredential) {
        status.className = 'bio-status error';
        status.textContent = 'WebAuthn non supporté par ce navigateur.';
        return;
    }

    btn.disabled = true;
    status.className = 'bio-status';
    status.textContent = 'Récupération du challenge...';

    try {
        const optsResp = await fetch('webauthn_auth.php?action=challenge');
        const opts     = await optsResp.json();
        if (opts.error) throw new Error(opts.error);

        opts.challenge = b64u_to_buf(opts.challenge);
        if (opts.allowCredentials) {
            opts.allowCredentials = opts.allowCredentials.map(c => ({...c, id: b64u_to_buf(c.id)}));
        }

        status.textContent = 'Veuillez utiliser votre biométrie...';

        const assertion = await navigator.credentials.get({ publicKey: opts });

        status.textContent = 'Vérification...';

        const verifyResp = await fetch('webauthn_auth.php?action=verify', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                id:               assertion.id,
                clientDataJSON:   buf_to_b64u(assertion.response.clientDataJSON),
                authenticatorData:buf_to_b64u(assertion.response.authenticatorData),
                signature:        buf_to_b64u(assertion.response.signature),
            })
        });
        const result = await verifyResp.json();

        if (result.success) {
            status.className = 'bio-status success';
            status.textContent = '✓ Authentifié !';
            window.location.href = result.redirect;
        } else {
            throw new Error(result.error || 'Erreur inconnue');
        }
    } catch (err) {
        status.className = 'bio-status error';
        if (err.name === 'NotAllowedError') {
            status.textContent = 'Annulé ou biométrie refusée. Utilisez le PIN.';
        } else {
            status.textContent = 'Erreur : ' + err.message;
        }
        btn.disabled = false;
    }
}

// auto-lancer l'auth biométrique si un credential est dispo
<?php if ($has_webauthn): ?>
window.addEventListener('load', function() {
    if (window.PublicKeyCredential) {
        setTimeout(authenticateBiometric, 500);
    }
});
<?php endif; ?>
</script>
</body>
</html>
