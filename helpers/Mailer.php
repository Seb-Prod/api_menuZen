<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Inclusion manuelle des classes PHPMailer
require_once __DIR__ . '/../lib/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../lib/PHPMailer/SMTP.php';
require_once __DIR__ . '/../lib/PHPMailer/Exception.php';


function sendVerificationEmail(string $to, string $username, string $verificationLink): bool
{
    $config = require __DIR__ . '/../config/config.php';
    $mailConfig = $config['mail'];
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = $mailConfig['smtp_host'];
        $mail->SMTPAuth = $mailConfig['smtp_auth'];
        $mail->Username = $mailConfig['smtp_username'];
        $mail->Password = $mailConfig['smtp_password'];
        $mail->Port = $mailConfig['smtp_port'];

        $secure = $mailConfig['smtp_secure'];

        // Destinataire
        $mail->setFrom($mailConfig['from_email'], $mailConfig['from_name']);
        $mail->addAddress($to, $username);

        // Contenu
        $mail->isHTML(true);
        $mail->Subject = 'Vérification de votre adresse e-mail';
        $mail->Body = "
            <p>Bonjour $username,</p>
            <p>Merci pour votre inscription. Cliquez sur le lien ci-dessous pour activer votre compte :</p>
            <p><a href=\"$verificationLink\">Activer mon compte</a></p>
            <p>Si vous n'avez pas créé de compte, ignorez ce message.</p>
        ";

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Erreur mail : " . $mail->ErrorInfo);
        return false;
    }
}