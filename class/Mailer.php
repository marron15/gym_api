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

    /**
     * Send membership expiring notification (3 days left)
     * @param string $recipientEmail
     * @param string $recipientName
     * @param string $membershipType
     * @param string $expirationDate
     * @param int $daysLeft
     * @return bool
     */
    public function sendMembershipExpiringNotification(
        string $recipientEmail,
        string $recipientName,
        string $membershipType,
        string $expirationDate,
        int $daysLeft = 3
    ): bool {
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
            $mail->Subject = 'RNR Fitness Gym - Membership Expiring Soon';

            $mail->Body = $this->buildMembershipExpiringHtmlBody($recipientName, $membershipType, $expirationDate, $daysLeft);
            $mail->AltBody = $this->buildMembershipExpiringTextBody($recipientName, $membershipType, $expirationDate, $daysLeft);

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log('Mailer Error (Membership Expiring): ' . $mail->ErrorInfo);
            throw new Exception('Failed to send membership expiring notification: ' . $e->getMessage());
        }
    }

    /**
     * Send membership expired notification
     * @param string $recipientEmail
     * @param string $recipientName
     * @param string $membershipType
     * @param string $expirationDate
     * @return bool
     */
    public function sendMembershipExpiredNotification(
        string $recipientEmail,
        string $recipientName,
        string $membershipType,
        string $expirationDate
    ): bool {
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
            $mail->Subject = 'RNR Fitness Gym - Membership Expired';

            $mail->Body = $this->buildMembershipExpiredHtmlBody($recipientName, $membershipType, $expirationDate);
            $mail->AltBody = $this->buildMembershipExpiredTextBody($recipientName, $membershipType, $expirationDate);

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log('Mailer Error (Membership Expired): ' . $mail->ErrorInfo);
            throw new Exception('Failed to send membership expired notification: ' . $e->getMessage());
        }
    }

    private function buildMembershipExpiringHtmlBody(
        string $recipientName,
        string $membershipType,
        string $expirationDate,
        int $daysLeft
    ): string {
        $safeName = htmlspecialchars($recipientName ?: 'Member');
        $safeType = htmlspecialchars($membershipType);
        $safeDate = htmlspecialchars($expirationDate);
        $formattedDate = date('F j, Y', strtotime($expirationDate));

        return "
            <div style=\"font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto;\">
                <div style=\"background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%); padding: 30px; text-align: center;\">
                    <h1 style=\"color: #fff; margin: 0; font-size: 28px;\">RNR Fitness Gym</h1>
                </div>
                <div style=\"background: #fff; padding: 30px; border: 1px solid #e0e0e0;\">
                    <h2 style=\"color: #ff9800; margin-top: 0;\">Membership Expiring Soon</h2>
                    <p>Hi {$safeName},</p>
                    <p>This is a friendly reminder that your <strong>{$safeType}</strong> membership at RNR Fitness Gym will expire in <strong style=\"color: #f57c00;\">{$daysLeft} day(s)</strong>.</p>
                    <div style=\"background: #fff3e0; border-left: 4px solid #ff9800; padding: 20px; margin: 20px 0;\">
                        <p style=\"margin: 0;\"><strong>Expiration Date:</strong> {$formattedDate}</p>
                        <p style=\"margin: 10px 0 0 0;\"><strong>Membership Type:</strong> {$safeType}</p>
                    </div>
                    <p>To continue enjoying our facilities and services, please renew your membership before it expires.</p>
                    <p>If you have any questions or need assistance with renewal, please don't hesitate to contact us.</p>
                    <p style=\"margin-top: 30px;\">Stay strong,<br/><strong>RNR Fitness Gym Team</strong></p>
                </div>
                <div style=\"background: #f5f5f5; padding: 20px; text-align: center; font-size: 12px; color: #666;\">
                    <p style=\"margin: 0;\">This is an automated notification. Please do not reply to this email.</p>
                </div>
            </div>
        ";
    }

    private function buildMembershipExpiringTextBody(
        string $recipientName,
        string $membershipType,
        string $expirationDate,
        int $daysLeft
    ): string {
        $safeName = $recipientName ?: 'Member';
        $formattedDate = date('F j, Y', strtotime($expirationDate));

        return "Hi {$safeName},\n\n" .
            "This is a friendly reminder that your {$membershipType} membership at RNR Fitness Gym will expire in {$daysLeft} day(s).\n\n" .
            "Expiration Date: {$formattedDate}\n" .
            "Membership Type: {$membershipType}\n\n" .
            "To continue enjoying our facilities and services, please renew your membership before it expires.\n\n" .
            "If you have any questions or need assistance with renewal, please don't hesitate to contact us.\n\n" .
            "Stay strong,\nRNR Fitness Gym Team";
    }

    private function buildMembershipExpiredHtmlBody(
        string $recipientName,
        string $membershipType,
        string $expirationDate
    ): string {
        $safeName = htmlspecialchars($recipientName ?: 'Member');
        $safeType = htmlspecialchars($membershipType);
        $formattedDate = date('F j, Y', strtotime($expirationDate));

        return "
            <div style=\"font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto;\">
                <div style=\"background: linear-gradient(135deg, #d32f2f 0%, #b71c1c 100%); padding: 30px; text-align: center;\">
                    <h1 style=\"color: #fff; margin: 0; font-size: 28px;\">RNR Fitness Gym</h1>
                </div>
                <div style=\"background: #fff; padding: 30px; border: 1px solid #e0e0e0;\">
                    <h2 style=\"color: #d32f2f; margin-top: 0;\">Membership Expired</h2>
                    <p>Hi {$safeName},</p>
                    <p>We wanted to inform you that your <strong>{$safeType}</strong> membership at RNR Fitness Gym has expired.</p>
                    <div style=\"background: #ffebee; border-left: 4px solid #d32f2f; padding: 20px; margin: 20px 0;\">
                        <p style=\"margin: 0;\"><strong>Expiration Date:</strong> {$formattedDate}</p>
                        <p style=\"margin: 10px 0 0 0;\"><strong>Membership Type:</strong> {$safeType}</p>
                    </div>
                    <p>To continue accessing our facilities and services, please renew your membership as soon as possible.</p>
                    <p>We'd love to have you back! If you have any questions or need assistance with renewal, please contact us.</p>
                    <p style=\"margin-top: 30px;\">Stay strong,<br/><strong>RNR Fitness Gym Team</strong></p>
                </div>
                <div style=\"background: #f5f5f5; padding: 20px; text-align: center; font-size: 12px; color: #666;\">
                    <p style=\"margin: 0;\">This is an automated notification. Please do not reply to this email.</p>
                </div>
            </div>
        ";
    }

    private function buildMembershipExpiredTextBody(
        string $recipientName,
        string $membershipType,
        string $expirationDate
    ): string {
        $safeName = $recipientName ?: 'Member';
        $formattedDate = date('F j, Y', strtotime($expirationDate));

        return "Hi {$safeName},\n\n" .
            "We wanted to inform you that your {$membershipType} membership at RNR Fitness Gym has expired.\n\n" .
            "Expiration Date: {$formattedDate}\n" .
            "Membership Type: {$membershipType}\n\n" .
            "To continue accessing our facilities and services, please renew your membership as soon as possible.\n\n" .
            "We'd love to have you back! If you have any questions or need assistance with renewal, please contact us.\n\n" .
            "Stay strong,\nRNR Fitness Gym Team";
    }
}

