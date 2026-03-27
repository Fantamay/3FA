<?php

function webauthn_rp_id(): string {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return explode(':', $host)[0]; 
}

function webauthn_rp_name(): string {
    return 'Triple Auth';
}

function b64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function b64url_decode(string $data): string {
    $pad  = (4 - strlen($data) % 4) % 4;
    return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', $pad));
}

function cbor_decode(string $data): mixed {
    $pos = 0;
    return _cbor_read($data, $pos);
}

function _cbor_read(string $d, int &$p): mixed {
    if ($p >= strlen($d)) throw new \RuntimeException('CBOR: fin inattendue');
    $b    = ord($d[$p++]);
    $type = ($b >> 5) & 7;
    $info = $b & 31;

    if      ($info <  24) $v = $info;
    elseif  ($info === 24) $v = ord($d[$p++]);
    elseif  ($info === 25) { $v = unpack('n', substr($d,$p,2))[1]; $p+=2; }
    elseif  ($info === 26) { $v = unpack('N', substr($d,$p,4))[1]; $p+=4; }
    elseif  ($info === 27) {
        $hi = unpack('N', substr($d,$p,4))[1];
        $lo = unpack('N', substr($d,$p+4,4))[1];
        $v  = ($hi * 4294967296) + $lo; $p += 8;
    } else throw new \RuntimeException("CBOR: info non supporté $info");

    switch ($type) {
        case 0: return $v;
        case 1: return -1 - $v;
        case 2: $r = substr($d,$p,$v); $p+=$v; return $r;
        case 3: $r = substr($d,$p,$v); $p+=$v; return $r;
        case 4:
            $r = [];
            for ($i=0;$i<$v;$i++) $r[] = _cbor_read($d,$p);
            return $r;
        case 5:
            $r = [];
            for ($i=0;$i<$v;$i++) { $k=_cbor_read($d,$p); $r[$k]=_cbor_read($d,$p); }
            return $r;
        case 7:
            if ($info===20) return false;
            if ($info===21) return true;
            if ($info===22) return null;
            throw new \RuntimeException("CBOR: simple non supporté $info");
        default: throw new \RuntimeException("CBOR: type non supporté $type");
    }
}

function parse_auth_data(string $raw): array {
    $rp_id_hash = substr($raw, 0, 32);
    $flags      = ord($raw[32]);
    $sign_count = unpack('N', substr($raw, 33, 4))[1];

    $result = [
        'rpIdHash'  => $rp_id_hash,
        'flags'     => $flags,
        'signCount' => $sign_count,
        'UP'        => (bool)($flags & 0x01),
        'UV'        => (bool)($flags & 0x04),
        'AT'        => (bool)($flags & 0x40),
    ];

    if ($result['AT'] && strlen($raw) > 37) {
        $off     = 37;
        $aaguid  = substr($raw, $off, 16); $off += 16;
        $id_len  = unpack('n', substr($raw, $off, 2))[1]; $off += 2;
        $cred_id = substr($raw, $off, $id_len); $off += $id_len;
        $cose_raw = substr($raw, $off);

        $result['credentialId'] = $cred_id;
        $result['coseKey']      = cbor_decode($cose_raw);
        $result['aaguid']       = $aaguid;
    }

    return $result;
}

function _asn1_len(int $len): string {
    if ($len < 0x80)    return chr($len);
    if ($len < 0x100)   return "\x81" . chr($len);
    if ($len < 0x10000) return "\x82" . chr($len >> 8) . chr($len & 0xff);
    throw new \RuntimeException("ASN.1: longueur trop grande");
}

function _asn1_int(string $bytes): string {
    if (ord($bytes[0]) >= 0x80) $bytes = "\x00" . $bytes; 
    return "\x02" . _asn1_len(strlen($bytes)) . $bytes;
}

function cose_to_pem(array $cose): string {
    $kty = $cose[1] ?? null;
    if ($kty === 2) return _ec2_to_pem($cose);  
    if ($kty === 3) return _rsa_to_pem($cose);  
    throw new \RuntimeException("Type de clé COSE non supporté : $kty");
}

function _ec2_to_pem(array $cose): string {
    $x = $cose[-2] ?? '';
    $y = $cose[-3] ?? '';
    if (strlen($x) !== 32 || strlen($y) !== 32) throw new \RuntimeException("Clé EC invalide");

    $point   = "\x04" . $x . $y;
    $oid_ec  = "\x2a\x86\x48\xce\x3d\x02\x01";
    $oid_256 = "\x2a\x86\x48\xce\x3d\x03\x01\x07";

    $alg = "\x30" . _asn1_len(2 + strlen($oid_ec) + 2 + strlen($oid_256))
         . "\x06" . chr(strlen($oid_ec)) . $oid_ec
         . "\x06" . chr(strlen($oid_256)) . $oid_256;

    $bs   = "\x03" . _asn1_len(strlen($point)+1) . "\x00" . $point;
    $spki = "\x30" . _asn1_len(strlen($alg)+strlen($bs)) . $alg . $bs;

    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki),64,"\n") . "-----END PUBLIC KEY-----\n";
}

function _rsa_to_pem(array $cose): string {
    $n = $cose[-1] ?? '';
    $e = $cose[-2] ?? '';
    if (!$n || !$e) throw new \RuntimeException("Clé RSA invalide");

    $seq = "\x30" . _asn1_len(strlen(_asn1_int($n)) + strlen(_asn1_int($e)))
         . _asn1_int($n) . _asn1_int($e);

    $oid_rsa = "\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01";
    $alg  = "\x30" . _asn1_len(2 + strlen($oid_rsa) + 2)
          . "\x06" . chr(strlen($oid_rsa)) . $oid_rsa . "\x05\x00";
    $bs   = "\x03" . _asn1_len(strlen($seq)+1) . "\x00" . $seq;
    $spki = "\x30" . _asn1_len(strlen($alg)+strlen($bs)) . $alg . $bs;

    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki),64,"\n") . "-----END PUBLIC KEY-----\n";
}

function webauthn_new_challenge(string $session_key = 'webauthn_challenge'): string {
    $challenge = b64url_encode(random_bytes(32));
    $_SESSION[$session_key] = $challenge;
    return $challenge;
}

function webauthn_verify_registration(array $resp, string $expected_challenge): array {
    
    $cd_raw = b64url_decode($resp['clientDataJSON']);
    $cd     = json_decode($cd_raw, true);
    if ($cd['type'] !== 'webauthn.create') throw new \RuntimeException("Type client data invalide");
    if ($cd['challenge'] !== $expected_challenge) throw new \RuntimeException("Challenge incorrect");

    
    $att  = cbor_decode(b64url_decode($resp['attestationObject']));
    $auth = parse_auth_data($att['authData']);

    
    if (!hash_equals(hash('sha256', webauthn_rp_id(), true), $auth['rpIdHash'])) {
        throw new \RuntimeException("RP ID hash incorrect");
    }

    
    if (!$auth['UP']) throw new \RuntimeException("User presence manquant");

    
    if (empty($auth['credentialId']) || empty($auth['coseKey'])) {
        throw new \RuntimeException("Aucune clé dans authenticator data");
    }

    $pem  = cose_to_pem($auth['coseKey']);
    $algo = ($auth['coseKey'][1] ?? 0) === 3 ? -257 : -7; 

    return [
        'credentialId' => b64url_encode($auth['credentialId']),
        'publicKeyPem' => $pem,
        'signCount'    => $auth['signCount'],
        'algo'         => $algo,
    ];
}

function webauthn_verify_assertion(array $resp, string $expected_challenge, string $public_key_pem, int $stored_count): int {
    
    $cd_raw = b64url_decode($resp['clientDataJSON']);
    $cd     = json_decode($cd_raw, true);
    if ($cd['type'] !== 'webauthn.get') throw new \RuntimeException("Type client data invalide");
    if ($cd['challenge'] !== $expected_challenge) throw new \RuntimeException("Challenge incorrect");

    
    $auth_raw = b64url_decode($resp['authenticatorData']);
    $auth     = parse_auth_data($auth_raw);

    
    if (!hash_equals(hash('sha256', webauthn_rp_id(), true), $auth['rpIdHash'])) {
        throw new \RuntimeException("RP ID hash incorrect");
    }

    if (!$auth['UP']) throw new \RuntimeException("User presence manquant");

    
    $signed = $auth_raw . hash('sha256', $cd_raw, true);
    $sig    = b64url_decode($resp['signature']);
    $ok     = openssl_verify($signed, $sig, $public_key_pem, OPENSSL_ALGO_SHA256);
    if ($ok !== 1) throw new \RuntimeException("Signature invalide");

    
    $new_count = $auth['signCount'];
    if ($new_count !== 0 && $new_count <= $stored_count) {
        throw new \RuntimeException("Attaque replay détectée");
    }

    return $new_count;
}
