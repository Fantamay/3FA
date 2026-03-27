<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/credentials.php';

function _build_mailer(): PHPMailer {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = MAIL_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = MAIL_USERNAME;
    $mail->Password   = MAIL_PASSWORD;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = MAIL_PORT;
    $mail->setFrom(MAIL_FROM, 'Triple Auth');
    $mail->isHTML(true);
    return $mail;
}

function sendOTP(string $to, string $otp): bool {
    try {
        $mail = _build_mailer();
        $mail->addAddress($to);
        $mail->Subject = 'Votre code OTP - Triple Auth';
        $mail->Body    = "
            <h2 style='color:#8f5fff;'>Votre code OTP</h2>
            <p>Voici votre code : <b style='font-size:1.5em;'>" . htmlspecialchars($otp) . "</b></p>
            <p>Ce code expire dans <b>2 minutes</b>.</p>
            <p style='color:#888;font-size:0.9em;'>Si vous n'avez pas demandé ce code, ignorez cet email.</p>
        ";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Erreur envoi OTP : ' . $e->getMessage());
        return false;
    }
}

function sendPasswordReset(string $to, string $token): bool {
    try {
        
        $protocol  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host      = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $path      = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
        $reset_url = $protocol . '://' . $host . rtrim($path, '/') . '/reset_password.php?token=' . $token;

        $mail = _build_mailer();
        $mail->addAddress($to);
        $mail->Subject = 'Réinitialisation de mot de passe - Triple Auth';
        $mail->Body    = "
            <h2 style='color:#8f5fff;'>Réinitialisation de mot de passe</h2>
            <p>Cliquez sur le lien ci-dessous pour réinitialiser votre mot de passe :</p>
            <p><a href='" . htmlspecialchars($reset_url) . "' style='color:#3e8eff;'>Réinitialiser mon mot de passe</a></p>
            <p>Ce lien est valable <b>1 heure</b>.</p>
            <p style='color:#888;font-size:0.9em;'>Si vous n'avez pas fait cette demande, ignorez cet email.</p>
        ";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Erreur envoi reset : ' . $e->getMessage());
        return false;
    }
}
