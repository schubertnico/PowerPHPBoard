<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Send Mail to User
 *
 * MIT License
 *
 * Copyright (c) 2026 PowerScripts
 */

use PowerPHPBoard\CSRF;
use PowerPHPBoard\Database;
use PowerPHPBoard\Security;
use PowerPHPBoard\Session;

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
$settings = $db->fetchOne('SELECT * FROM ppb_config WHERE id = ?', [1]) ?? [];

// Load language file
$langFile = match ($settings['language'] ?? 'English') {
    'Deutsch-Sie' => 'deutsch-sie.inc.php',
    'Deutsch-Du' => 'deutsch-du.inc.php',
    default => 'english.inc.php',
};
require_once __DIR__ . '/' . $langFile;
require_once __DIR__ . '/functions.inc.php';

// Get user info from session
$ppbuser = [];
$loggedin = 'NO';
$userId = Session::getUserId();

if ($userId !== null) {
    $userRow = $db->fetchOne('SELECT * FROM ppb_users WHERE id = ?', [$userId]);
    if ($userRow !== null) {
        $loggedin = 'YES';
        $ppbuser = $userRow;
    }
}

$catid = Security::getInt('catid');
$boardid = Security::getInt('boardid');
$userid = Security::getInt('userid');
$sendmail = Security::getString('sendmail');
?>
<?php include __DIR__ . '/header.inc.php'; ?>

<table border="0" cellpadding="2" cellspacing="1" width="100%">

<?php
if ($userid === 0) {
    default_error(
        $lang_chooseuser ?? 'Please choose a user',
        'index.php',
        $lang_boardlist ?? 'Board list',
        $settings['tablebg3'] ?? '#cccccc',
        $settings['tablebg2'] ?? '#eeeeee',
        $settings['tablebg1'] ?? '#ffffff'
    );
} else {
    if ($loggedin === 'YES') {
        $recipient = $db->fetchOne('SELECT * FROM ppb_users WHERE id = ?', [$userid]);

        if ($recipient === null) {
            default_error(
                $lang_chooseexistinguser ?? 'User does not exist',
                'index.php',
                $lang_boardlist ?? 'Board list',
                $settings['tablebg3'] ?? '#cccccc',
                $settings['tablebg2'] ?? '#eeeeee',
                $settings['tablebg1'] ?? '#ffffff'
            );
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $sendmail === 'YES') {
            // Validate CSRF
            if (!CSRF::validateFromPost()) {
                default_error(
                    'Security token invalid. Please try again.',
                    'javascript:history.back()',
                    $lang_backtosendmailform ?? 'Back',
                    $settings['tablebg3'] ?? '#cccccc',
                    $settings['tablebg2'] ?? '#eeeeee',
                    $settings['tablebg1'] ?? '#ffffff'
                );
            } else {
                $title = Security::getString('title', 'POST');
                $emailcontent = Security::getString('emailcontent', 'POST');

                if ($title === '' || $emailcontent === '') {
                    default_error(
                        $lang_insertvaluesforall ?? 'Please fill in all fields',
                        'javascript:history.back()',
                        $lang_backtosendmailform ?? 'Back',
                        $settings['tablebg3'] ?? '#cccccc',
                        $settings['tablebg2'] ?? '#eeeeee',
                        $settings['tablebg1'] ?? '#ffffff'
                    );
                } else {
                    // Send the email
                    $message = $emailcontent . "\n\n\n" .
                        ($lang_thisemailwassentthrough ?? 'This email was sent through') . ' ' .
                        ($settings['boardurl'] ?? '') . "\n" .
                        'PowerPHPBoard (C) Copyright 2024 by PowerScripts (www.powerscripts.org)';

                    $headers = 'From: ' . $ppbuser['username'] . ' <' . $ppbuser['email'] . '>';

                    mail((string) $recipient['email'], $title, $message, $headers);

                    CSRF::regenerate();

                    echo '
                    <tr><td bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '">
                    <b>' . ($lang_statusmessage ?? 'Status') . '</b>
                    </td></tr>
                    <tr><td bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '"><br>
                    ' . ($lang_emailsentsuccessfull ?? 'Email sent successfully') . '<br><br>
                    </td></tr>
                    <tr><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '" align="center">
                    <a href="showprofile.php?userid=' . $userid . '">' . Security::escape($recipient['username']) . '\'s ' . ($lang_profile ?? 'Profile') . '</a>
                    </td></tr>
                    ';
                }
            }
        } else {
            // Show send mail form
            echo '
            <form action="sendmail.php?sendmail=YES&userid=' . $userid . '&catid=' . $catid . '&boardid=' . $boardid . '" method="post">
            ' . CSRF::getTokenField() . '
            <tr><td bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '" colspan="2">
            <b>' . ($lang_sendmail ?? 'Send Email') . '</b>
            </td></tr>
            <tr><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '" width="*">
            <b>' . ($lang_from ?? 'From') . '</b>
            </td><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '" width="300">
            <a href="showprofile.php?userid=' . (int) $ppbuser['id'] . '">' . Security::escape($ppbuser['username']) . '</a>
            </td></tr>
            <tr><td bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '" width="*">
            <b>' . ($lang_to ?? 'To') . '</b>
            </td><td bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '" width="300">
            <a href="showprofile.php?userid=' . (int) $recipient['id'] . '">' . Security::escape($recipient['username']) . '</a>
            </td></tr>
            <tr><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '" width="*">
            <b>' . ($lang_title ?? 'Title') . '</b>
            </td><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '" width="300">
            <input name="title" size="50" maxlength="150" value="eMail through PowerPHPBoard">
            </td></tr>
            <tr><td bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '" width="*" valign="top">
            <b>' . ($lang_text ?? 'Text') . '</b>
            </td><td bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '" width="300">
            <textarea name="emailcontent" cols="40" rows="10"></textarea>
            </td></tr>
            <tr><td colspan="2" align="center" bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '">
            <input type="submit" value="' . ($lang_send ?? 'Send') . '">
            </td></tr>
            </form>
            ';
        }
    } else {
        default_error(
            $lang_loginfirst ?? 'Please log in first',
            'index.php',
            $lang_boardlist ?? 'Board list',
            $settings['tablebg3'] ?? '#cccccc',
            $settings['tablebg2'] ?? '#eeeeee',
            $settings['tablebg1'] ?? '#ffffff'
        );
    }
}
?>

</table>

<?php include __DIR__ . '/footer.inc.php'; ?>
