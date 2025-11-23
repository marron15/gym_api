<?php
/**
 * Membership Notification Processor
 * 
 * This script should be run via cron job daily (recommended: once per day)
 * Example cron job (runs daily at 9:00 AM):
 * 0 9 * * * /usr/bin/php /path/to/gym_api/membership/processNotifications.php
 * 
 * For Hostinger, you can set this up in cPanel > Cron Jobs
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set timezone
date_default_timezone_set('Asia/Manila');

// Log file path
$logFile = __DIR__ . '/../logs/membership_notifications.log';

// Ensure logs directory exists
$logsDir = dirname($logFile);
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0755, true);
}

function logMessage(string $message): void
{
    global $logFile;
    $timestamp = '[' . date('Y-m-d H:i:s') . '] ';
    @file_put_contents($logFile, $timestamp . $message . PHP_EOL, FILE_APPEND);
}

logMessage('=== Starting Membership Notification Processor ===');

try {
    require_once __DIR__ . '/../class/MembershipNotification.php';

    $notification = new MembershipNotification();
    $results = $notification->processAllNotifications();

    $summary = [
        'timestamp' => date('Y-m-d H:i:s'),
        'three_days_left' => [
            'processed' => $results['three_days_left']['processed'],
            'sent' => $results['three_days_left']['sent'],
            'failed' => $results['three_days_left']['failed'],
            'skipped' => $results['three_days_left']['skipped']
        ],
        'expired' => [
            'processed' => $results['expired']['processed'],
            'sent' => $results['expired']['sent'],
            'failed' => $results['expired']['failed'],
            'skipped' => $results['expired']['skipped']
        ]
    ];

    logMessage('Notification Summary: ' . json_encode($summary, JSON_PRETTY_PRINT));

    if (!empty($results['three_days_left']['errors'])) {
        logMessage('3 Days Left Errors: ' . implode('; ', $results['three_days_left']['errors']));
    }

    if (!empty($results['expired']['errors'])) {
        logMessage('Expired Errors: ' . implode('; ', $results['expired']['errors']));
    }

    logMessage('=== Notification Processor Completed Successfully ===');
    
    // If running from command line, output summary
    if (php_sapi_name() === 'cli') {
        echo "Membership Notification Processor\n";
        echo "==================================\n";
        echo "Timestamp: " . $summary['timestamp'] . "\n\n";
        echo "3 Days Left Notifications:\n";
        echo "  Processed: " . $summary['three_days_left']['processed'] . "\n";
        echo "  Sent: " . $summary['three_days_left']['sent'] . "\n";
        echo "  Failed: " . $summary['three_days_left']['failed'] . "\n";
        echo "  Skipped: " . $summary['three_days_left']['skipped'] . "\n\n";
        echo "Expired Notifications:\n";
        echo "  Processed: " . $summary['expired']['processed'] . "\n";
        echo "  Sent: " . $summary['expired']['sent'] . "\n";
        echo "  Failed: " . $summary['expired']['failed'] . "\n";
        echo "  Skipped: " . $summary['expired']['skipped'] . "\n";
    }

    exit(0);
} catch (Exception $e) {
    $errorMessage = 'Fatal Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
    logMessage($errorMessage);
    logMessage('=== Notification Processor Failed ===');
    
    if (php_sapi_name() === 'cli') {
        echo "ERROR: " . $errorMessage . "\n";
    }
    
    exit(1);
}

