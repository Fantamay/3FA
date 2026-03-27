<?php

session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/webauthn.php';
require_once __DIR__ . '/config/helpers.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || empty($_SESSION['otp_validated'])) {
    echo json_encode(['error' => 'Non authentifié']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action  = $_GET['action'] ?? '';

if ($action === 'challenge') {
    $challenge = webauthn_new_challenge('webauthn_auth_challenge');

    $req = $pdo->prepare("SELECT credential_id FROM webauthn_credentials WHERE user_id = ?");
    $req->execute([$user_id]);
    $allow = array_map(fn($r) => ['type' => 'public-key', 'id' => $r['credential_id']], $req->fetchAll());

    echo json_encode([
        'challenge'        => $challenge,
        'rpId'             => webauthn_rp_id(),
        'allowCredentials' => $allow,
        'userVerification' => 'required',
        'timeout'          => 60000,
    ]);
    exit;
}

if ($action === 'verify' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    try {
        $expected = $_SESSION['webauthn_auth_challenge'] ?? '';
        if (!$expected) throw new \RuntimeException("Aucun challenge en session");

        $req = $pdo->prepare("SELECT * FROM webauthn_credentials WHERE credential_id = ? AND user_id = ?");
        $req->execute([$input['id'] ?? '', $user_id]);
        $cred = $req->fetch();
        if (!$cred) throw new \RuntimeException("Credential introuvable");

        $new_count = webauthn_verify_assertion(
            $input,
            $expected,
            $cred['public_key'],
            (int)$cred['sign_count']
        );

        
        $pdo->prepare("UPDATE webauthn_credentials SET sign_count = ? WHERE id = ?")
            ->execute([$new_count, $cred['id']]);

        unset($_SESSION['webauthn_auth_challenge']);

        $_SESSION['pin_validated'] = true;
        audit_log($pdo, $user_id, 'biometric_auth', 'Authentification biométrique réussie');

        echo json_encode(['success' => true, 'redirect' => 'dashboard.php']);
    } catch (\Throwable $e) {
        
        error_log('WebAuthn auth error: ' . $e->getMessage());
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['error' => 'Action invalide']);
