<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Send Password Reminder
 *
 * MIT License
 *
 * Copyright (c) 2024 PowerScripts
 */

use PowerPHPBoard\Database;
use PowerPHPBoard\Session;
use PowerPHPBoard\Security;
use PowerPHPBoard\CSRF;

// Load configuration
require_once __DIR__ . '/config.inc.php';

// Start session
Session::start();

// Connect to database
try {
    $db = Database::getInstance($mysql);
} catch (PDOException $e) {
    die('Database connection failed');
}

// Load settings
$settings = $db->fetchOne("SELECT * FROM ppb_config WHERE id = ?", [1]) ?? [];

// Load language file
$langFile = match ($settings['language'] ?? 'English') {
    'Deutsch-Sie' => 'deutsch-sie.inc.php',
    'Deutsch-Du' => 'deutsch-du.inc.php',
    default => 'english.inc.php',
};
require_once __DIR__ . '/' . $langFile;
require_once __DIR__ . '/functions.inc.php';

$catid = Security::getInt('catid');
$boardid = Security::getInt('boardid');
$send = Security::getInt('send');
?>
<?php include __DIR__ . '/header.inc.php'; ?>

<table border="0" cellpadding="2" cellspacing="1" width="100%">

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $send === 1) {
    // Validate CSRF
    if (!CSRF::validateFromPost()) {
        default_error(
            'Security token invalid. Please try again.',
            'javascript:history.back()',
            $lang_backtosendpwdform ?? 'Back',
            $settings['tablebg3'] ?? '#cccccc',
            $settings['tablebg2'] ?? '#eeeeee',
            $settings['tablebg1'] ?? '#ffffff'
        );
    } else {
        $email = Security::getString('email', 'POST');

        if ($email === '') {
            default_error(
                $lang_insertemail ?? 'Please enter your email',
                'javascript:history.back()',
                $lang_backtosendpwdform ?? 'Back',
                $settings['tablebg3'] ?? '#cccccc',
                $settings['tablebg2'] ?? '#eeeeee',
                $settings['tablebg1'] ?? '#ffffff'
            );
        } else {
            $user = $db->fetchOne("SELECT * FROM ppb_users WHERE email = ?", [$email]);

            if ($user === null) {
                default_error(
                    $lang_nouserwithemail ?? 'No user with this email',
                    'javascript:history.back()',
                    $lang_backtosendpwdform ?? 'Back',
                    $settings['tablebg3'] ?? '#cccccc',
                    $settings['tablebg2'] ?? '#eeeeee',
                    $settings['tablebg1'] ?? '#ffffff'
                );
            } else {
                // Generate a new random password
                $newPassword = bin2hex(random_bytes(8));
                $hashedPassword = Security::hashPassword($newPassword);

                // Update user password
                $db->query("UPDATE ppb_users SET password = ? WHERE id = ?", [$hashedPassword, $user['id']]);

                // Send email with new password
                $subject = ($settings['boardtitle'] ?? 'PowerPHPBoard') . ' - ' . ($lang_passwordreminder ?? 'Password Reminder');
                $message = ($lang_hello ?? 'Hello') . ' ' . $user['username'] . ",\n\n" .
                    ($lang_hereisyourrequestedlogininfo ?? 'Here is your requested login information for') . ' ' . ($settings['boardurl'] ?? '') . "\n\n" .
                    ($lang_ifyoudidntrequestmail ?? 'If you did not request this email, please ignore it.') . "\n\n" .
                    ($lang_username ?? 'Username') . ": " . $user['username'] . "\n" .
                    ($lang_email ?? 'Email') . ": " . $user['email'] . "\n" .
                    ($lang_password ?? 'Password') . ": " . $newPassword . "\n\n" .
                    ($lang_donotanswertoautomail ?? 'Please do not reply to this automated message.');

                $headers = 'From: ' . ($settings['adminemail'] ?? 'noreply@example.com');
                mail($email, $subject, $message, $headers);

                CSRF::regenerate();

                echo '
                <tr><td bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '">
                <b>' . ($lang_statusmessage ?? 'Status') . '</b>
                </td></tr>
                <tr><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '"><br>
                ' . ($lang_logininfosentto ?? 'Login info sent to') . ' <b>' . Security::escape($email) . '</b>!<br><br>
                </td></tr>
                <tr><td bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '" align="center">
                <a href="index.php">Home</a>
                </td></tr>
                ';
            }
        }
    }
} else {
    echo '
    <tr><td bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '">
    <b>' . ($lang_sendpwd ?? 'Send Password') . '</b>
    </td></tr>
    <form action="sendpassword.php?send=1&catid=' . $catid . '&boardid=' . $boardid . '" method="post">
    ' . CSRF::getTokenField() . '
    <tr><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '" align="center"><br>
    ' . ($lang_email ?? 'Email') . ':<br>
    <br>
    <input name="email" size="25" maxlength="100" type="email">&nbsp;&nbsp;&nbsp;<input type="submit" value="' . ($lang_send ?? 'Send') . '"><br><br>
    </td></tr>
    </form>
    ';
}
?>

</table>

<?php include __DIR__ . '/footer.inc.php'; ?>
