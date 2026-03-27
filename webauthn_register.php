<?php

session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/webauthn.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || empty($_SESSION['otp_validated'])) {
    echo json_encode(['error' => 'Non authentifié']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action  = $_GET['action'] ?? '';

if ($action === 'challenge') {
    $challenge = webauthn_new_challenge('webauthn_reg_challenge');

    $req = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $req->execute([$user_id]);
    $user = $req->fetch();

    
    $req = $pdo->prepare("SELECT credential_id FROM webauthn_credentials WHERE user_id = ?");
    $req->execute([$user_id]);
    $exclude = array_map(fn($r) => ['type' => 'public-key', 'id' => $r['credential_id']], $req->fetchAll());

    echo json_encode([
        'challenge'   => $challenge,
        'rp'          => ['name' => webauthn_rp_name(), 'id' => webauthn_rp_id()],
        'user'        => [
            'id'          => b64url_encode((string)$user_id),
            'name'        => $user['email'],
            'displayName' => $user['email'],
        ],
        'pubKeyCredParams' => [
            ['type' => 'public-key', 'alg' => -7],   
            ['type' => 'public-key', 'alg' => -257],  
        ],
        'authenticatorSelection' => [
            'authenticatorAttachment' => 'platform',  
            'userVerification'        => 'required',
            'residentKey'             => 'discouraged',
        ],
        'excludeCredentials' => $exclude,
        'timeout'     => 60000,
        'attestation' => 'none',
    ]);
    exit;
}

if ($action === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    try {
        $expected = $_SESSION['webauthn_reg_challenge'] ?? '';
        if (!$expected) throw new \RuntimeException("Aucun challenge en session");

        $res = webauthn_verify_registration($input, $expected);

        
        $pdo->prepare("DELETE FROM webauthn_credentials WHERE user_id = ?")->execute([$user_id]);
        $pdo->prepare(
            "INSERT INTO webauthn_credentials (user_id, credential_id, public_key, algo, sign_count)
             VALUES (?, ?, ?, ?, ?)"
        )->execute([$user_id, $res['credentialId'], $res['publicKeyPem'], $res['algo'], $res['signCount']]);

        unset($_SESSION['webauthn_reg_challenge']);

        echo json_encode(['success' => true, 'message' => 'Biométrie enregistrée avec succès !']);
    } catch (\Throwable $e) {
        error_log('WebAuthn register error: ' . $e->getMessage());
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['error' => 'Action invalide']);
