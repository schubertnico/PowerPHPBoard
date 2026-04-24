<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Send Password Reset Link
 *
 * MIT License
 *
 * Copyright (c) 2026 PowerScripts
 */

use PowerPHPBoard\CSRF;
use PowerPHPBoard\Database;
use PowerPHPBoard\DatabaseRateLimitStorage;
use PowerPHPBoard\Mailer;
use PowerPHPBoard\RateLimiter;
use PowerPHPBoard\Security;
use PowerPHPBoard\Session;

require_once __DIR__ . '/config.inc.php';
Session::start();

try {
    $db = Database::getInstance($mysql);
} catch (PDOException $e) {
    die('Database connection failed');
}

$settings = $db->fetchOne('SELECT * FROM ppb_config WHERE id = ?', [1]) ?? [];

$langFile = match ($settings['language'] ?? 'English') {
    'Deutsch-Sie' => 'deutsch-sie.inc.php',
    'Deutsch-Du' => 'deutsch-du.inc.php',
    default => 'english.inc.php',
};
require_once __DIR__ . '/' . $langFile;
require_once __DIR__ . '/functions.inc.php';

$catid = Security::getInt('catid');
$boardid = Security::getInt('boardid');
$send = Security::getInt('send', 'REQUEST');

$rateLimiter = new RateLimiter(
    new DatabaseRateLimitStorage($db),
    maxAttempts: 5,
    windowSeconds: 3600,
    lockSeconds: 3600
);
$rlIdent = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

include __DIR__ . '/header.inc.php';
?>

<table border="0" cellpadding="2" cellspacing="1" width="100%">

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $send === 1) {
    if (!CSRF::validateFromPost()) {
        default_error(
            'Security token invalid. Please try again.',
            'javascript:history.back()',
            $lang_backtosendpwdform ?? 'Back',
            $settings['tablebg3'] ?? '#cccccc',
            $settings['tablebg2'] ?? '#eeeeee',
            $settings['tablebg1'] ?? '#ffffff'
        );
    } elseif (!$rateLimiter->check('pwreset', $rlIdent)) {
        default_error(
            $lang_toomanyattempts ?? 'Too many attempts. Please try again later.',
            'index.php',
            'Home',
            $settings['tablebg3'] ?? '#cccccc',
            $settings['tablebg2'] ?? '#eeeeee',
            $settings['tablebg1'] ?? '#ffffff'
        );
    } else {
        $email = Security::getString('email', 'POST');
        // BUG-018: Rate-Limit zaehlt jede Anfrage unabhaengig vom Ergebnis
        $rateLimiter->recordFailure('pwreset', $rlIdent);

        $user = null;
        if ($email !== '' && Security::isValidEmail($email)) {
            $user = $db->fetchOne('SELECT * FROM ppb_users WHERE email = ?', [$email]);
        }

        if ($user !== null) {
            // BUG-016: Token-Flow statt sofortiges Passwort-Reset
            $rawToken = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $rawToken);
            $now = time();
            $expires = $now + 3600;

            // Alte, noch nicht verwendete Tokens invalidieren
            $db->query(
                'UPDATE ppb_password_resets SET used_at = ? WHERE userid = ? AND used_at = 0',
                [$now, $user['id']]
            );
            $db->query(
                'INSERT INTO ppb_password_resets (userid, token_hash, expires_at, created_at) VALUES (?, ?, ?, ?)',
                [$user['id'], $tokenHash, $expires, $now]
            );

            // Reset-URL zusammenbauen
            $baseUrl = (string) ($settings['boardurl'] ?? '');
            if ($baseUrl === '') {
                $scheme = (($_SERVER['HTTPS'] ?? '') === 'on') ? 'https' : 'http';
                $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
                $baseUrl = $scheme . '://' . $host;
            }
            $resetUrl = rtrim($baseUrl, '/') . '/resetpassword.php?token=' . $rawToken;

            $subject = ($settings['boardtitle'] ?? 'PowerPHPBoard') . ' - ' . ($lang_passwordreminder ?? 'Password Reset');
            $message = ($lang_hello ?? 'Hello') . ' ' . $user['username'] . ",\n\n"
                . ($lang_pwdresetclicklink ?? 'Click this link within one hour to reset your password:') . "\n\n"
                . $resetUrl . "\n\n"
                . ($lang_ifyoudidntrequestmail ?? 'If you did not request this, you can ignore this email.') . "\n";

            $fromAddress = (string) ($settings['adminemail'] ?? '');
            if ($fromAddress === '' || !Security::isValidEmail($fromAddress)) {
                $fromAddress = (string) ($mail['from'] ?? 'noreply@powerphpboard.local');
            }
            $mailer = new Mailer(
                (string) ($mail['host'] ?? 'mailpit'),
                (int) ($mail['port'] ?? 1025)
            );
            $mailer->send($email, $fromAddress, $subject, $message);
        }

        CSRF::regenerate();
        // BUG-017: Einheitliche Antwort unabhaengig vom Nutzer-Existenz
        echo '
        <tr><td bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '">
        <b>' . ($lang_statusmessage ?? 'Status') . '</b>
        </td></tr>
        <tr><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '"><br>
        ' . ($lang_pwdresetlinksent ?? 'If the email is registered, a reset link has been sent.') . '<br><br>
        </td></tr>
        <tr><td bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '" align="center">
        <a href="index.php">Home</a>
        </td></tr>
        ';
    }
} else {
    echo '
    <tr><td bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '">
    <b>' . ($lang_sendpwd ?? 'Send Password') . '</b>
    </td></tr>
    <form action="sendpassword.php?send=1" method="post">
    ' . CSRF::getTokenField() . '
    <input type="hidden" name="send" value="1">
    <tr><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '" align="center"><br>
    ' . ($lang_email ?? 'Email') . ':<br>
    <br>
    <input name="email" size="25" maxlength="100" type="email" required>&nbsp;&nbsp;&nbsp;<input type="submit" value="' . ($lang_send ?? 'Send') . '"><br><br>
    </td></tr>
    </form>
    ';
}
?>
</table>

<?php include __DIR__ . '/footer.inc.php'; ?>
