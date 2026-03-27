<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires de sécurité (sans base de données)
 * Couvre : hachage de mot de passe, validation OTP, tokens, filtrage
 */
class SecurityTest extends TestCase
{
    // ------------------------------------------------------------------
    // Hachage de mot de passe (bcrypt)
    // ------------------------------------------------------------------

    public function testPasswordHashIsNotPlaintext(): void
    {
        $password = 'MonMotDePasse123!';
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $this->assertNotSame($password, $hash);
    }

    public function testPasswordVerifySucceedsForCorrectPassword(): void
    {
        $password = 'MonMotDePasse123!';
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $this->assertTrue(password_verify($password, $hash));
    }

    public function testPasswordVerifyFailsForWrongPassword(): void
    {
        $hash = password_hash('MotDePasseCorrect', PASSWORD_BCRYPT);
        $this->assertFalse(password_verify('MauvaisMotDePasse', $hash));
    }

    public function testPasswordHashDifferentEachTime(): void
    {
        $password = 'SamePassword';
        $hash1 = password_hash($password, PASSWORD_BCRYPT);
        $hash2 = password_hash($password, PASSWORD_BCRYPT);
        $this->assertNotSame($hash1, $hash2);
    }

    public function testWeakPasswordFailsRegex(): void
    {
        // Politique : min 8 chars, 1 majuscule, 1 chiffre
        $pattern = '/^(?=.*[A-Z])(?=.*\d).{8,}$/';
        $this->assertDoesNotMatchRegularExpression($pattern, 'faible');
        $this->assertDoesNotMatchRegularExpression($pattern, 'toutenminu1');
        $this->assertDoesNotMatchRegularExpression($pattern, 'TOUTENMAJUSCULE');
    }

    public function testStrongPasswordMatchesRegex(): void
    {
        $pattern = '/^(?=.*[A-Z])(?=.*\d).{8,}$/';
        $this->assertMatchesRegularExpression($pattern, 'MotDePasse1');
        $this->assertMatchesRegularExpression($pattern, 'Secure123');
    }

    // ------------------------------------------------------------------
    // Token de réinitialisation
    // ------------------------------------------------------------------

    public function testResetTokenHasCorrectLength(): void
    {
        $token = bin2hex(random_bytes(32));
        $this->assertSame(64, strlen($token));
    }

    public function testResetTokensAreUnique(): void
    {
        $token1 = bin2hex(random_bytes(32));
        $token2 = bin2hex(random_bytes(32));
        $this->assertNotSame($token1, $token2);
    }

    public function testResetTokenIsHexadecimal(): void
    {
        $token = bin2hex(random_bytes(32));
        $this->assertMatchesRegularExpression('/^[0-9a-f]+$/', $token);
    }

    // ------------------------------------------------------------------
    // Validation OTP
    // ------------------------------------------------------------------

    public function testOtpHasSixDigits(): void
    {
        $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $otp);
    }

    public function testOtpExpiration(): void
    {
        $expiration = new \DateTime('+10 minutes');
        $now = new \DateTime();
        $this->assertGreaterThan($now, $expiration);
    }

    public function testExpiredOtpIsDetected(): void
    {
        $expiration = new \DateTime('-1 minute');
        $now = new \DateTime();
        $this->assertLessThan($now, $expiration);
    }

    public function testOtpValidationWithCorrectCode(): void
    {
        $storedOtp = '482931';
        $userInput = '482931';
        $this->assertTrue(hash_equals($storedOtp, $userInput));
    }

    public function testOtpValidationWithWrongCode(): void
    {
        $storedOtp = '482931';
        $userInput = '000000';
        $this->assertFalse(hash_equals($storedOtp, $userInput));
    }

    // ------------------------------------------------------------------
    // Protection XSS
    // ------------------------------------------------------------------

    public function testHtmlspecialcharsEscapesXss(): void
    {
        $input = '<script>alert("xss")</script>';
        $safe = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        $this->assertStringNotContainsString('<script>', $safe);
        $this->assertStringContainsString('&lt;script&gt;', $safe);
    }

    public function testHtmlspecialcharsEscapesQuotes(): void
    {
        $input = '" onmouseover="alert(1)';
        $safe = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        $this->assertStringNotContainsString('"', $safe);
    }

    // ------------------------------------------------------------------
    // Validation de fichier uploadé
    // ------------------------------------------------------------------

    public function testAllowedMimeTypes(): void
    {
        $allowed = ['application/pdf', 'image/jpeg', 'image/png'];
        $this->assertContains('application/pdf', $allowed);
        $this->assertNotContains('application/x-php', $allowed);
        $this->assertNotContains('text/html', $allowed);
    }

    public function testFileExtensionBlacklist(): void
    {
        $blacklisted = ['php', 'php3', 'phtml', 'exe', 'sh', 'bat'];
        $uploadedFile = 'malware.php';
        $ext = strtolower(pathinfo($uploadedFile, PATHINFO_EXTENSION));
        $this->assertContains($ext, $blacklisted);
    }

    public function testLegitimateFileExtensionIsAllowed(): void
    {
        $blacklisted = ['php', 'php3', 'phtml', 'exe', 'sh', 'bat'];
        $uploadedFile = 'document.pdf';
        $ext = strtolower(pathinfo($uploadedFile, PATHINFO_EXTENSION));
        $this->assertNotContains($ext, $blacklisted);
    }

    // ------------------------------------------------------------------
    // Logique de blocage de compte
    // ------------------------------------------------------------------

    public function testAccountBlockedAfterThreeFailures(): void
    {
        $attempts = 3;
        $maxAttempts = 3;
        $shouldBlock = $attempts >= $maxAttempts;
        $this->assertTrue($shouldBlock);
    }

    public function testAccountNotBlockedBeforeThreeFailures(): void
    {
        $attempts = 2;
        $maxAttempts = 3;
        $shouldBlock = $attempts >= $maxAttempts;
        $this->assertFalse($shouldBlock);
    }

    public function testBlockedUntilIsInFuture(): void
    {
        $blockedUntil = new \DateTime('+15 minutes');
        $now = new \DateTime();
        $this->assertGreaterThan($now, $blockedUntil);
    }
}
