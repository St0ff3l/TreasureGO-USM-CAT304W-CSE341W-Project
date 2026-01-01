<?php
// includes/sendgrid_mailer.php

// 1. Modify path: look for secrets.php in api/config directory
// __DIR__ is the current includes directory, so it is ../api/config/secrets.php
$secretsFile = __DIR__ . '/../api/config/secrets.php';

if (file_exists($secretsFile)) {
    require_once $secretsFile;
}

// 2. Environment variable fallback
if (!defined('SENDGRID_API_KEY')) {
    define('SENDGRID_API_KEY', getenv('SENDGRID_API_KEY') ?: '');
}

define('SENDGRID_FROM_EMAIL', 'no-reply@daombledore.fun');
define('SENDGRID_FROM_NAME', 'TreasureGo');

function sendEmail($to, $subject, $htmlContent, $textContent = null) {
    if (empty(SENDGRID_API_KEY)) {
        // Debug log
        file_put_contents(__DIR__ . '/mail_error.log', "Error: secrets.php not found in api/config/ or Key is empty.\n", FILE_APPEND);
        return false;
    }

    $url = 'https://api.sendgrid.com/v3/mail/send';

    if (!$textContent) {
        $textContent = strip_tags($htmlContent);
    }

    $data = [
        'personalizations' => [['to' => [['email' => $to]]]],
        'from' => ['email' => SENDGRID_FROM_EMAIL, 'name' => SENDGRID_FROM_NAME],
        'subject' => $subject,
        'content' => [
            ['type' => 'text/plain', 'value' => $textContent],
            ['type' => 'text/html', 'value' => $htmlContent]
        ]
    ];

    $ch = curl_init();
    // Set connection timeout to 5 seconds, execution timeout to 10 seconds
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . SENDGRID_API_KEY,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Disable SSL verification for local development
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($httpCode < 200 || $httpCode >= 300) {
        $log = "Time: " . date('Y-m-d H:i:s') . "\n";
        $log .= "HTTP Code: $httpCode\n";
        $log .= "Response: $response\n----------------\n";
        file_put_contents(__DIR__ . '/mail_error.log', $log, FILE_APPEND);
        return false;
    }

    return true;
}
?>