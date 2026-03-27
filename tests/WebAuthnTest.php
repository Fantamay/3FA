<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour config/webauthn.php
 */
class WebAuthnTest extends TestCase
{
    protected function setUp(): void
    {
        if (!function_exists('b64url_encode')) {
            require_once __DIR__ . '/../config/webauthn.php';
        }
    }

    // ------------------------------------------------------------------
    // webauthn_rp_name()
    // ------------------------------------------------------------------

    public function testRpNameReturnsString(): void
    {
        $this->assertIsString(webauthn_rp_name());
    }

    public function testRpNameValue(): void
    {
        $this->assertSame('Triple Auth', webauthn_rp_name());
    }

    // ------------------------------------------------------------------
    // webauthn_rp_id()
    // ------------------------------------------------------------------

    public function testRpIdFromHttpHost(): void
    {
        $_SERVER['HTTP_HOST'] = 'example.com';
        $this->assertSame('example.com', webauthn_rp_id());
    }

    public function testRpIdStripsPort(): void
    {
        $_SERVER['HTTP_HOST'] = 'localhost:8080';
        $this->assertSame('localhost', webauthn_rp_id());
    }

    public function testRpIdFallbackWhenNoHost(): void
    {
        unset($_SERVER['HTTP_HOST']);
        $this->assertSame('localhost', webauthn_rp_id());
    }

    // ------------------------------------------------------------------
    // b64url_encode / b64url_decode
    // ------------------------------------------------------------------

    public function testB64urlRoundtripSimple(): void
    {
        $original = 'hello world';
        $this->assertSame($original, b64url_decode(b64url_encode($original)));
    }

    public function testB64urlRoundtripBinary(): void
    {
        $binary = random_bytes(32);
        $this->assertSame($binary, b64url_decode(b64url_encode($binary)));
    }

    public function testB64urlEncodedContainsNoPaddingEquals(): void
    {
        $this->assertStringNotContainsString('=', b64url_encode(random_bytes(10)));
    }

    public function testB64urlEncodedContainsNoPlus(): void
    {
        $this->assertStringNotContainsString('+', b64url_encode(str_repeat("\xfb", 30)));
    }

    public function testB64urlEncodedContainsNoSlash(): void
    {
        $this->assertStringNotContainsString('/', b64url_encode(str_repeat("\xff", 30)));
    }

    public function testB64urlEncodeEmptyString(): void
    {
        $this->assertSame('', b64url_encode(''));
    }

    public function testB64urlDecodeEmptyString(): void
    {
        $this->assertSame('', b64url_decode(''));
    }

    public function testB64urlRoundtripLongData(): void
    {
        $data = random_bytes(256);
        $this->assertSame($data, b64url_decode(b64url_encode($data)));
    }

    // ------------------------------------------------------------------
    // _asn1_len()
    // ------------------------------------------------------------------

    public function testAsn1LenShortForm(): void
    {
        $result = _asn1_len(10);
        $this->assertSame(1, strlen($result));
        $this->assertSame(10, ord($result[0]));
    }

    public function testAsn1LenShortFormBoundary(): void
    {
        $result = _asn1_len(0x7F);
        $this->assertSame(1, strlen($result));
        $this->assertSame(0x7F, ord($result[0]));
    }

    public function testAsn1LenMediumForm(): void
    {
        $result = _asn1_len(0x80);
        $this->assertSame(2, strlen($result));
        $this->assertSame(0x81, ord($result[0]));
        $this->assertSame(0x80, ord($result[1]));
    }

    public function testAsn1LenLongForm(): void
    {
        $result = _asn1_len(0x0201);
        $this->assertSame(3, strlen($result));
        $this->assertSame(0x82, ord($result[0]));
        $this->assertSame(0x02, ord($result[1]));
        $this->assertSame(0x01, ord($result[2]));
    }

    public function testAsn1LenThrowsForTooLarge(): void
    {
        $this->expectException(\RuntimeException::class);
        _asn1_len(0x10000);
    }

    // ------------------------------------------------------------------
    // _asn1_int()
    // ------------------------------------------------------------------

    public function testAsn1IntStartsWithIntegerTag(): void
    {
        $result = _asn1_int("\x01\x02\x03");
        $this->assertSame(0x02, ord($result[0]));
    }

    public function testAsn1IntAddsLeadingZeroForHighBit(): void
    {
        $result = _asn1_int("\x80\x01");
        $this->assertSame(3, ord($result[1])); // \x00 + 2 octets = 3
    }

    public function testAsn1IntNoLeadingZeroWhenNotNeeded(): void
    {
        $result = _asn1_int("\x01\x02");
        $this->assertSame(2, ord($result[1]));
    }

    // ------------------------------------------------------------------
    // cbor_decode — types scalaires
    // ------------------------------------------------------------------

    public function testCborDecodeUnsignedInt(): void
    {
        $this->assertSame(0, cbor_decode("\x00"));
        $this->assertSame(1, cbor_decode("\x01"));
        $this->assertSame(23, cbor_decode("\x17"));
    }

    public function testCborDecodeUnsignedIntOneByteExtended(): void
    {
        $this->assertSame(24, cbor_decode("\x18\x18"));
    }

    public function testCborDecodeUnsignedIntTwoByteExtended(): void
    {
        // info=25 → lire 2 octets big-endian : \x01\x00 = 256
        $this->assertSame(256, cbor_decode("\x19\x01\x00"));
    }

    public function testCborDecodeUnsignedIntFourByteExtended(): void
    {
        // info=26 → lire 4 octets big-endian : \x00\x01\x00\x00 = 65536
        $this->assertSame(65536, cbor_decode("\x1a\x00\x01\x00\x00"));
    }

    public function testCborDecodeNegativeInt(): void
    {
        $this->assertSame(-1, cbor_decode("\x20"));
        $this->assertSame(-2, cbor_decode("\x21"));
    }

    public function testCborDecodeTextString(): void
    {
        $this->assertSame('hello', cbor_decode("\x65hello"));
    }

    public function testCborDecodeByteString(): void
    {
        $this->assertSame("\x01\x02\x03", cbor_decode("\x43\x01\x02\x03"));
    }

    public function testCborDecodeTrue(): void
    {
        $this->assertTrue(cbor_decode("\xf5"));
    }

    public function testCborDecodeFalse(): void
    {
        $this->assertFalse(cbor_decode("\xf4"));
    }

    public function testCborDecodeNull(): void
    {
        $this->assertNull(cbor_decode("\xf6"));
    }

    public function testCborDecodeSimpleArray(): void
    {
        $this->assertSame([1, 2, 3], cbor_decode("\x83\x01\x02\x03"));
    }

    public function testCborDecodeMap(): void
    {
        $result = cbor_decode("\xa1\x01\x02");
        $this->assertSame(2, $result[1]);
    }

    public function testCborDecodeThrowsOnTruncatedData(): void
    {
        $this->expectException(\RuntimeException::class);
        cbor_decode('');
    }

    public function testCborDecodeUnsignedIntEightByteExtended(): void
    {
        // info=27 → lire 8 octets : \x00\x00\x00\x01\x00\x00\x00\x00 = 4294967296
        $this->assertSame(4294967296, cbor_decode("\x1b\x00\x00\x00\x01\x00\x00\x00\x00"));
    }

    public function testCborDecodeThrowsForUnsupportedSimpleValue(): void
    {
        // type 7, info=19 → simple non supporté
        $this->expectException(\RuntimeException::class);
        cbor_decode("\xf3");
    }

    public function testCborDecodeThrowsForUnsupportedType(): void
    {
        // type 6 (tag) info=0 → non supporté
        $this->expectException(\RuntimeException::class);
        cbor_decode("\xc0");
    }

    public function testCborDecodeThrowsForUnsupportedInfo(): void
    {
        // info=31 (indefinite length) → non supporté
        $this->expectException(\RuntimeException::class);
        cbor_decode("\x1f");
    }

    public function testCborDecodeNestedArray(): void
    {
        // \x82\x81\x01\x02 = [[1], 2]
        $result = cbor_decode("\x82\x81\x01\x02");
        $this->assertSame([[1], 2], $result);
    }

    // ------------------------------------------------------------------
    // parse_auth_data() — sans attested credential (flag AT=0)
    // ------------------------------------------------------------------

    public function testParseAuthDataBasic(): void
    {
        // rpIdHash (32 bytes) + flags (1 byte, UP=1) + signCount (4 bytes, big-endian = 42)
        $rpIdHash  = str_repeat("\xaa", 32);
        $flags     = chr(0x01); // UP=true
        $signCount = pack('N', 42);
        $raw = $rpIdHash . $flags . $signCount;

        $result = parse_auth_data($raw);

        $this->assertSame($rpIdHash, $result['rpIdHash']);
        $this->assertSame(0x01, $result['flags']);
        $this->assertSame(42, $result['signCount']);
        $this->assertTrue($result['UP']);
        $this->assertFalse($result['UV']);
        $this->assertFalse($result['AT']);
    }

    public function testParseAuthDataWithUVFlag(): void
    {
        $raw = str_repeat("\x00", 32) . chr(0x05) . pack('N', 0);
        // 0x05 = UP (0x01) | UV (0x04)
        $result = parse_auth_data($raw);
        $this->assertTrue($result['UP']);
        $this->assertTrue($result['UV']);
    }

    public function testParseAuthDataSignCountZero(): void
    {
        $raw = str_repeat("\x00", 32) . chr(0x01) . pack('N', 0);
        $result = parse_auth_data($raw);
        $this->assertSame(0, $result['signCount']);
    }

    public function testParseAuthDataSignCountLarge(): void
    {
        $raw = str_repeat("\x00", 32) . chr(0x01) . pack('N', 99999);
        $result = parse_auth_data($raw);
        $this->assertSame(99999, $result['signCount']);
    }

    public function testParseAuthDataWithAtFlag(): void
    {
        // Construit des auth data avec AT flag (0x40) activé
        $rpIdHash  = str_repeat("\xbb", 32);
        $flags     = chr(0x41); // UP (0x01) | AT (0x40)
        $signCount = pack('N', 1);
        $aaguid    = str_repeat("\x00", 16);
        $credId    = "testcredid12"; // 12 octets
        $credIdLen = pack('n', strlen($credId));
        // COSE key minimal : map {1:2, -2: 32bytes, -3: 32bytes}
        // Encodé manuellement en CBOR
        $x = str_repeat("\x01", 32);
        $y = str_repeat("\x02", 32);
        // \xa3 = map(3), \x01\x02 = 1:2, \x21 = -2, \x58\x20 = bytes(32), \x22 = -3
        $coseKey = "\xa3\x01\x02\x21\x58\x20" . $x . "\x22\x58\x20" . $y;

        $raw = $rpIdHash . $flags . $signCount . $aaguid . $credIdLen . $credId . $coseKey;

        $result = parse_auth_data($raw);

        $this->assertTrue($result['AT']);
        $this->assertTrue($result['UP']);
        $this->assertSame($credId, $result['credentialId']);
        $this->assertArrayHasKey('coseKey', $result);
        $this->assertArrayHasKey('aaguid', $result);
    }

    public function testWebauthnNewChallenge(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $challenge = webauthn_new_challenge('test_challenge_key');
        $this->assertNotEmpty($challenge);
        $this->assertSame($challenge, $_SESSION['test_challenge_key']);
        // Doit être du base64url valide (aller/retour)
        $decoded = b64url_decode($challenge);
        $this->assertSame(32, strlen($decoded));
    }

    // ------------------------------------------------------------------
    // cose_to_pem() — cas d'erreur
    // ------------------------------------------------------------------

    public function testCoseToPemThrowsForUnknownKeyType(): void
    {
        $this->expectException(\RuntimeException::class);
        cose_to_pem([1 => 99]); // kty=99 non supporté
    }

    public function testCoseToPemThrowsWhenNoKty(): void
    {
        $this->expectException(\RuntimeException::class);
        cose_to_pem([]); // pas de kty
    }

    // ------------------------------------------------------------------
    // _ec2_to_pem() — via cose_to_pem(kty=2)
    // ------------------------------------------------------------------

    public function testEc2ToPemReturnsPemString(): void
    {
        $cose = [
            1  => 2,              // kty = EC2
            -2 => str_repeat("\x01", 32), // x
            -3 => str_repeat("\x02", 32), // y
        ];
        $pem = cose_to_pem($cose);
        $this->assertStringContainsString('BEGIN PUBLIC KEY', $pem);
        $this->assertStringContainsString('END PUBLIC KEY', $pem);
    }

    public function testEc2ToPemThrowsForInvalidKeyLength(): void
    {
        $this->expectException(\RuntimeException::class);
        cose_to_pem([
            1  => 2,
            -2 => 'toocourt', // pas 32 octets
            -3 => str_repeat("\x02", 32),
        ]);
    }

    // ------------------------------------------------------------------
    // _rsa_to_pem() — via cose_to_pem(kty=3)
    // ------------------------------------------------------------------

    public function testRsaToPemReturnsPemString(): void
    {
        $cose = [
            1  => 3,                       // kty = RSA
            -1 => str_repeat("\x01", 256), // n (2048-bit)
            -2 => "\x01\x00\x01",          // e (65537)
        ];
        $pem = cose_to_pem($cose);
        $this->assertStringContainsString('BEGIN PUBLIC KEY', $pem);
        $this->assertStringContainsString('END PUBLIC KEY', $pem);
    }

    public function testRsaToPemThrowsForMissingModulus(): void
    {
        $this->expectException(\RuntimeException::class);
        cose_to_pem([
            1  => 3,
            -1 => '',    // n vide
            -2 => "\x03",
        ]);
    }
}
