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

    /**
     * Send membership created success notification
     * @param string $recipientEmail
     * @param string $recipientName
     * @param string $membershipType
     * @param string $startDate
     * @param string $expirationDate
     * @return bool
     */
    public function sendMembershipCreatedNotification(
        string $recipientEmail,
        string $recipientName,
        string $membershipType,
        string $startDate,
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
            $mail->Subject = 'RNR Fitness Gym - Membership Successfully Created';

            $mail->Body = $this->buildMembershipCreatedHtmlBody($recipientName, $membershipType, $startDate, $expirationDate);
            $mail->AltBody = $this->buildMembershipCreatedTextBody($recipientName, $membershipType, $startDate, $expirationDate);

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log('Mailer Error (Membership Created): ' . $mail->ErrorInfo);
            throw new Exception('Failed to send membership created notification: ' . $e->getMessage());
        }
    }

    private function buildMembershipCreatedHtmlBody(
        string $recipientName,
        string $membershipType,
        string $startDate,
        string $expirationDate
    ): string {
        $safeName = htmlspecialchars($recipientName ?: 'Member');
        $safeType = htmlspecialchars($membershipType);
        $formattedStartDate = date('F j, Y', strtotime($startDate));
        
        // Parse expiration date - it might include time for Daily memberships
        $expirationDateTime = strtotime($expirationDate);
        $formattedExpirationDate = date('F j, Y', $expirationDateTime);
        $formattedExpirationTime = '';
        if (strpos($expirationDate, ' ') !== false) {
            $formattedExpirationTime = date('g:i A', $expirationDateTime);
        }

        // Create membership type-specific message
        $membershipMessage = $this->getMembershipTypeMessage($membershipType, $formattedExpirationDate, $formattedExpirationTime);

        return "
            <div style=\"font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto;\">
                <div style=\"background: linear-gradient(135deg, #4caf50 0%, #388e3c 100%); padding: 30px; text-align: center;\">
                    <h1 style=\"color: #fff; margin: 0; font-size: 28px;\">RNR Fitness Gym</h1>
                </div>
                <div style=\"background: #fff; padding: 30px; border: 1px solid #e0e0e0;\">
                    <h2 style=\"color: #4caf50; margin-top: 0;\">Membership Successfully Created!</h2>
                    <p>Hi {$safeName},</p>
                    <p>Congratulations! Your membership at RNR Fitness Gym has been successfully created.</p>
                    <div style=\"background: #e8f5e9; border-left: 4px solid #4caf50; padding: 20px; margin: 20px 0;\">
                        <p style=\"margin: 0;\"><strong>Membership Type:</strong> {$safeType}</p>
                        <p style=\"margin: 10px 0 0 0;\"><strong>Start Date:</strong> {$formattedStartDate}</p>
                        <p style=\"margin: 10px 0 0 0;\"><strong>Expiration Date:</strong> {$formattedExpirationDate}" . 
                        ($formattedExpirationTime ? " at {$formattedExpirationTime}" : "") . "</p>
                    </div>
                    <div style=\"background: #f1f8e9; padding: 20px; margin: 20px 0; border-radius: 8px;\">
                        {$membershipMessage}
                    </div>
                    <p>We're excited to have you as part of the RNR Fitness Gym family! If you have any questions or need assistance, please don't hesitate to contact us.</p>
                    <p style=\"margin-top: 30px;\">Welcome aboard!<br/><strong>RNR Fitness Gym Team</strong></p>
                </div>
                <div style=\"background: #f5f5f5; padding: 20px; text-align: center; font-size: 12px; color: #666;\">
                    <p style=\"margin: 0;\">This is an automated notification. Please do not reply to this email.</p>
                </div>
            </div>
        ";
    }

    private function buildMembershipCreatedTextBody(
        string $recipientName,
        string $membershipType,
        string $startDate,
        string $expirationDate
    ): string {
        $safeName = $recipientName ?: 'Member';
        $formattedStartDate = date('F j, Y', strtotime($startDate));
        $expirationDateTime = strtotime($expirationDate);
        $formattedExpirationDate = date('F j, Y', $expirationDateTime);
        $formattedExpirationTime = '';
        if (strpos($expirationDate, ' ') !== false) {
            $formattedExpirationTime = date('g:i A', $expirationDateTime);
        }

        $membershipMessage = $this->getMembershipTypeMessage($membershipType, $formattedExpirationDate, $formattedExpirationTime, false);

        return "Hi {$safeName},\n\n" .
            "Congratulations! Your membership at RNR Fitness Gym has been successfully created.\n\n" .
            "Membership Type: {$membershipType}\n" .
            "Start Date: {$formattedStartDate}\n" .
            "Expiration Date: {$formattedExpirationDate}" . 
            ($formattedExpirationTime ? " at {$formattedExpirationTime}" : "") . "\n\n" .
            "{$membershipMessage}\n\n" .
            "We're excited to have you as part of the RNR Fitness Gym family! If you have any questions or need assistance, please don't hesitate to contact us.\n\n" .
            "Welcome aboard!\nRNR Fitness Gym Team";
    }

    /**
     * Get membership type-specific message
     * @param string $membershipType
     * @param string $expirationDate
     * @param string $expirationTime
     * @param bool $isHtml
     * @return string
     */
    private function getMembershipTypeMessage(string $membershipType, string $expirationDate, string $expirationTime = '', bool $isHtml = true): string
    {
        $type = strtolower(trim($membershipType));
        
        if ($isHtml) {
            if ($type === 'daily') {
                return "<p style=\"margin: 0; font-weight: bold; color: #2e7d32;\">Your Daily membership is now active!</p>" .
                       "<p style=\"margin: 10px 0 0 0;\">You can access our facilities today until {$expirationTime}. Make the most of your day pass and enjoy all our amenities including gym equipment, training areas, and more.</p>";
            } elseif ($type === 'half month' || $type === 'halfmonth') {
                return "<p style=\"margin: 0; font-weight: bold; color: #2e7d32;\">Your Half Month membership is now active!</p>" .
                       "<p style=\"margin: 10px 0 0 0;\">You have 15 days of unlimited access to our facilities. Your membership will expire on {$expirationDate}. Enjoy our state-of-the-art equipment, training programs, and all gym amenities.</p>";
            } else {
                // Monthly (default)
                return "<p style=\"margin: 0; font-weight: bold; color: #2e7d32;\">Your Monthly membership is now active!</p>" .
                       "<p style=\"margin: 10px 0 0 0;\">You have 30 days of unlimited access to our facilities. Your membership will expire on {$expirationDate}. Take advantage of our comprehensive fitness programs, personal training options, and all gym facilities.</p>";
            }
        } else {
            // Plain text version
            if ($type === 'daily') {
                return "Your Daily membership is now active! You can access our facilities today until {$expirationTime}. Make the most of your day pass and enjoy all our amenities including gym equipment, training areas, and more.";
            } elseif ($type === 'half month' || $type === 'halfmonth') {
                return "Your Half Month membership is now active! You have 15 days of unlimited access to our facilities. Your membership will expire on {$expirationDate}. Enjoy our state-of-the-art equipment, training programs, and all gym amenities.";
            } else {
                return "Your Monthly membership is now active! You have 30 days of unlimited access to our facilities. Your membership will expire on {$expirationDate}. Take advantage of our comprehensive fitness programs, personal training options, and all gym facilities.";
            }
        }
    }
}

