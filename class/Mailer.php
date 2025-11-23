<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';

class Mailer
{
    private $config = [];

    public function __construct()
    {
        $configPath = __DIR__ . '/../config/email.php';
        if (!file_exists($configPath)) {
            throw new Exception('Email configuration file not found.');
        }

        $config = require $configPath;
        $requiredKeys = ['host', 'port', 'username', 'password', 'from_email', 'from_name'];
        foreach ($requiredKeys as $key) {
            if (!isset($config[$key]) || empty($config[$key])) {
                throw new Exception("Missing email configuration value for {$key}");
            }
        }

        $this->config = $config;
    }

    public function sendVerificationCode(string $recipientEmail, string $recipientName, string $code, int $expiresInMinutes): bool
    {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = $this->config['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->config['username'];
            $mail->Password = $this->config['password'];
            $mail->SMTPSecure = $this->config['encryption'] === 'ssl'
                ? PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $this->config['port'];
            $mail->CharSet = 'UTF-8';

            $mail->setFrom($this->config['from_email'], $this->config['from_name']);
            $mail->addReplyTo($this->config['from_email'], $this->config['from_name']);
            $mail->addAddress($recipientEmail, $recipientName);

            $mail->isHTML(true);
            $mail->Subject = 'RNR Fitness Gym - Email Verification Code';

            $mail->Body = $this->buildHtmlBody($recipientName, $code, $expiresInMinutes);
            $mail->AltBody = $this->buildTextBody($recipientName, $code, $expiresInMinutes);

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log('Mailer Error: ' . $mail->ErrorInfo);
            throw new Exception('Failed to send verification email. Please try again later.');
        }
    }

    private function buildHtmlBody(string $recipientName, string $code, int $expiresInMinutes): string
    {
        $safeName = htmlspecialchars($recipientName ?: 'Member');
        return "
            <div style=\"font-family: Arial, sans-serif; line-height: 1.5; color: #111\">
                <h2 style=\"color:#ff9800;\">RNR Fitness Gym Verification</h2>
                <p>Hi {$safeName},</p>
                <p>Use the verification code below to finish creating your account.</p>
                <div style=\"background:#111; color:#fff; padding:16px; border-radius:8px;
                    font-size:24px; letter-spacing:6px; text-align:center; margin:24px 0;\">
                    <strong>{$code}</strong>
                </div>
                <p>This code expires in <strong>{$expiresInMinutes} minute(s)</strong>. If you did not request this,
                please ignore this email.</p>
                <p>Train hard,<br/>RNR Fitness Gym</p>
            </div>
        ";
    }

    private function buildTextBody(string $recipientName, string $code, int $expiresInMinutes): string
    {
        $safeName = $recipientName ?: 'Member';
        return "Hi {$safeName},\n\n" .
            "Use this verification code to finish creating your RNR Fitness Gym account:\n" .
            "{$code}\n\n" .
            "The code expires in {$expiresInMinutes} minute(s).\n\n" .
            "Train hard,\nRNR Fitness Gym";
    }
}

