<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - User Registration
 *
 * MIT License
 *
 * Copyright (c) 2026 PowerScripts
 */

use PowerPHPBoard\CSRF;
use PowerPHPBoard\Mailer;
use PowerPHPBoard\Security;
use PowerPHPBoard\Validator;

include __DIR__ . '/header.inc.php';
?>

<table border="0" cellpadding="2" cellspacing="1" width="100%">

<?php
$acception = Security::getInt('acception', 'REQUEST');
$register = Security::getInt('register', 'POST');

if ($acception === 0) {
    // Display board rules
    echo '
      <tr><td align="center" bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '">
      <b>' . ($lang_boardrules ?? 'Board Rules') . '</b>
      </td></tr>
      <tr><td bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '">
      ' . ($lang_boardrulescontent ?? 'Please read and accept the board rules.') . '
      </td></tr>
      <tr><td align="center" bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '"><br>
      <form action="register.php" method="get">
      <input type="hidden" name="acception" value="1">
      <input type="hidden" name="catid" value="' . (int) $catid . '">
      <input type="hidden" name="boardid" value="' . (int) $boardid . '">
      <input type="submit" value="' . ($lang_agree ?? 'I Agree') . '">
      </form>
      <form action="index.php" method="get">
      <input type="submit" value="' . ($lang_disagree ?? 'I Disagree') . '">
      </form>
      </td></tr>
    ';
} else {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $register === 1) {
        // Validate CSRF token
        if (!CSRF::validateFromPost()) {
            default_error(
                'Security token invalid. Please try again.',
                'javascript:history.back()',
                $lang_backtoregform ?? 'Back to registration',
                $settings['tablebg3'] ?? '#cccccc',
                $settings['tablebg2'] ?? '#eeeeee',
                $settings['tablebg1'] ?? '#ffffff'
            );
        } else {
            // Get form data safely
            $username = Security::getString('username', 'POST');
            $email1 = Security::getString('email1', 'POST');
            $email2 = Security::getString('email2', 'POST');
            $password1 = Security::getString('password1', 'POST');
            $password2 = Security::getString('password2', 'POST');
            $homepage = Security::getString('homepage', 'POST');
            $icq = Security::getString('icq', 'POST');
            $biography = Security::getString('biography', 'POST');
            $signature = Security::getString('signature', 'POST');
            $hideemail = Security::getString('hideemail', 'POST');
            $logincookie = Security::getString('logincookie', 'POST');

            // Validate input
            if ($username === '' || $username === '0' || ($email1 === '' || $email1 === '0') || ($email2 === '' || $email2 === '0') || ($password1 === '' || $password1 === '0') || ($password2 === '' || $password2 === '0')) {
                default_error(
                    $lang_insertvaluesforall ?? 'Please fill in all required fields',
                    'javascript:history.back()',
                    $lang_backtoregform ?? 'Back',
                    $settings['tablebg3'] ?? '#cccccc',
                    $settings['tablebg2'] ?? '#eeeeee',
                    $settings['tablebg1'] ?? '#ffffff'
                );
            } elseif ($email1 !== $email2) {
                default_error(
                    $lang_emailsdifferent ?? 'Email addresses do not match',
                    'javascript:history.back()',
                    $lang_backtoregform ?? 'Back',
                    $settings['tablebg3'] ?? '#cccccc',
                    $settings['tablebg2'] ?? '#eeeeee',
                    $settings['tablebg1'] ?? '#ffffff'
                );
            } elseif (!Security::isValidEmail($email1)) {
                // Use preg_match instead of deprecated eregi
                default_error(
                    $lang_emailnotcorrect ?? 'Invalid email address format',
                    'javascript:history.back()',
                    $lang_backtoregform ?? 'Back',
                    $settings['tablebg3'] ?? '#cccccc',
                    $settings['tablebg2'] ?? '#eeeeee',
                    $settings['tablebg1'] ?? '#ffffff'
                );
            } elseif ($password1 !== $password2) {
                default_error(
                    $lang_pwdsdifferent ?? 'Passwords do not match',
                    'javascript:history.back()',
                    $lang_backtoregform ?? 'Back',
                    $settings['tablebg3'] ?? '#cccccc',
                    $settings['tablebg2'] ?? '#eeeeee',
                    $settings['tablebg1'] ?? '#ffffff'
                );
            } elseif (!Validator::isStrongPassword($password1)) {
                default_error(
                    $lang_pwdtooshort ?? 'Password must be at least 8 characters',
                    'javascript:history.back()',
                    $lang_backtoregform ?? 'Back',
                    $settings['tablebg3'] ?? '#cccccc',
                    $settings['tablebg2'] ?? '#eeeeee',
                    $settings['tablebg1'] ?? '#ffffff'
                );
            } elseif (!Validator::isValidUsername($username)) {
                default_error(
                    $lang_usernameinvalid ?? 'Username must be 2-50 chars and only contain letters, digits, . _ -',
                    'javascript:history.back()',
                    $lang_backtoregform ?? 'Back',
                    $settings['tablebg3'] ?? '#cccccc',
                    $settings['tablebg2'] ?? '#eeeeee',
                    $settings['tablebg1'] ?? '#ffffff'
                );
            } elseif (!Validator::withinLength($biography, Validator::BIOGRAPHY_MAX)
                || !Validator::withinLength($signature, Validator::SIGNATURE_MAX)
                || !Validator::withinLength($homepage, Validator::HOMEPAGE_MAX)) {
                default_error(
                    $lang_inputstoolong ?? 'One or more fields exceed the allowed length',
                    'javascript:history.back()',
                    $lang_backtoregform ?? 'Back',
                    $settings['tablebg3'] ?? '#cccccc',
                    $settings['tablebg2'] ?? '#eeeeee',
                    $settings['tablebg1'] ?? '#ffffff'
                );
            } else {
                // Validate ICQ (if provided)
                $icqNum = 0;
                if ($icq !== '' && $icq !== '0') {
                    $icqNum = filter_var($icq, FILTER_VALIDATE_INT);
                    if ($icqNum === false) {
                        default_error(
                            $lang_icqnotcorrect ?? 'ICQ number must be numeric',
                            'javascript:history.back()',
                            $lang_backtoregform ?? 'Back',
                            $settings['tablebg3'] ?? '#cccccc',
                            $settings['tablebg2'] ?? '#eeeeee',
                            $settings['tablebg1'] ?? '#ffffff'
                        );
                        goto end_registration;
                    }
                }

                // Check if email already exists (using prepared statement)
                $existing = $db->fetchOne('SELECT id FROM ppb_users WHERE email = ?', [$email1]);
                $existingUsername = $existing !== null ? null : $db->fetchOne('SELECT id FROM ppb_users WHERE username = ?', [$username]);

                if ($existing !== null) {
                    default_error(
                        $lang_emailalreadyexists ?? 'Email address already registered',
                        'javascript:history.back()',
                        $lang_backtoregform ?? 'Back',
                        $settings['tablebg3'] ?? '#cccccc',
                        $settings['tablebg2'] ?? '#eeeeee',
                        $settings['tablebg1'] ?? '#ffffff'
                    );
                } elseif ($existingUsername !== null) {
                    default_error(
                        $lang_usernametaken ?? 'This username is already taken',
                        'javascript:history.back()',
                        $lang_backtoregform ?? 'Back',
                        $settings['tablebg3'] ?? '#cccccc',
                        $settings['tablebg2'] ?? '#eeeeee',
                        $settings['tablebg1'] ?? '#ffffff'
                    );
                } else {
                    // Hash password using modern algorithm (not base64!)
                    $passwordHash = Security::hashPassword($password1);

                    // Sanitize inputs
                    $biography = strip_tags($biography);
                    // Signature: whitelist safe inline tags only (BUG-010)
                    $signature = strip_tags($signature, '<b><i><u><strong><em><br><a>');
                    $homepage = filter_var($homepage, FILTER_VALIDATE_URL) ? $homepage : '';
                    $hideemail = in_array($hideemail, ['YES', 'NO'], true) ? $hideemail : 'NO';
                    $logincookie = in_array($logincookie, ['YES', 'NO'], true) ? $logincookie : 'YES';

                    $now = time();

                    // Insert user with prepared statement (prevents SQL injection)
                    try {
                        $db->query(
                            "INSERT INTO ppb_users
                             (username, email, password, homepage, icq, biography, signature, hideemail, logincookie, status, registered)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Normal user', ?)",
                            [$username, $email1, $passwordHash, $homepage, $icqNum, $biography, $signature, $hideemail, $logincookie, $now]
                        );

                        // Send registration email
                        $subject = ($settings['boardtitle'] ?? 'PowerPHPBoard') . ' ' . ($lang_registration ?? 'Registration');
                        $message = ($lang_hello ?? 'Hello') . " $username,\n\n" .
                            ($lang_youregisteredsuccessfull ?? 'You have registered successfully at') . ' ' . ($settings['boardurl'] ?? '') . "\n\n" .
                            ($lang_hereisyourlogininformation ?? 'Your login information:') . "\n\n" .
                            '     ' . ($lang_username ?? 'Username') . ":  $username\n" .
                            '     ' . ($lang_email ?? 'Email') . ":  $email1\n\n" .
                            ($lang_youcanloginhere ?? 'Login here') . ': ' . ($settings['boardurl'] ?? '') . "/login.php\n\n" .
                            ($lang_donotanswertoautomail ?? 'This is an automated message, please do not reply.');

                        $mailer = new Mailer(
                            (string) ($mail['host'] ?? 'mailpit'),
                            (int) ($mail['port'] ?? 1025)
                        );
                        $fromAddress = (string) ($settings['adminemail'] ?? '');
                        if ($fromAddress === '' || !Security::isValidEmail($fromAddress)) {
                            $fromAddress = (string) ($mail['from'] ?? 'noreply@powerphpboard.local');
                        }
                        $mailer->send($email1, $fromAddress, $subject, $message);

                        echo '
                            <tr><td bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '">
                            <b>' . ($lang_statusmessage ?? 'Status') . '</b>
                            </td></tr>
                            <tr><td bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '"><br>
                            ' . ($lang_registrationsuccessfull ?? 'Registration successful!') . '<br><br>
                            </td></tr>
                            <tr><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '" align="center">
                        ';
                        if ($logincookie === 'YES') {
                            echo '<a href="login.php?catid=' . (int) $catid . '&boardid=' . (int) $boardid . '">' . ($lang_login ?? 'Login') . '</a>';
                        } else {
                            echo '<a href="index.php">Home</a>';
                        }
                        echo '
                            </td></tr>
                        ';
                    } catch (PDOException) {
                        default_error(
                            $lang_errorwhilereg ?? 'An error occurred during registration',
                            'javascript:history.back()',
                            $lang_backtoregform ?? 'Back',
                            $settings['tablebg3'] ?? '#cccccc',
                            $settings['tablebg2'] ?? '#eeeeee',
                            $settings['tablebg1'] ?? '#ffffff'
                        );
                    }
                }
            }
        }
        end_registration:
    } else {
        // Display registration form
        echo '
        <form action="register.php?acception=1" method="post">
        ' . CSRF::getTokenField() . '
        <input type="hidden" name="acception" value="1">
        <input type="hidden" name="register" value="1">
        <input type="hidden" name="catid" value="' . (int) $catid . '">
        <input type="hidden" name="boardid" value="' . (int) $boardid . '">
        <tr><td colspan="2" bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '">
        <b>' . ($lang_requiredinfo ?? 'Required Information') . '</b>
        </td></tr>
        <tr><td width="*" bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '">
        <b>' . ($lang_username ?? 'Username') . '</b>
        </td><td width="300" bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '">
        <input name="username" size="25" maxlength="50" required>
        </td></tr>
        <tr><td width="*" bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '">
        <b>' . ($lang_email ?? 'Email') . '</b>
        </td><td width="300" bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '">
        <input name="email1" size="25" maxlength="100" type="email" required>
        </td></tr>
        <tr><td width="*" bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '">
        <b>' . ($lang_email ?? 'Email') . '</b> <small>(' . ($lang_confirmation ?? 'Confirmation') . ')</small>
        </td><td width="300" bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '">
        <input name="email2" size="25" maxlength="100" type="email" required>
        </td></tr>
        <tr><td width="*" bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '">
        <b>' . ($lang_password ?? 'Password') . '</b>
        </td><td width="300" bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '">
        <input name="password1" size="25" maxlength="255" type="password" required minlength="8">
        </td></tr>
        <tr><td width="*" bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '">
        <b>' . ($lang_password ?? 'Password') . '</b> <small>(' . ($lang_confirmation ?? 'Confirmation') . ')</small>
        </td><td width="300" bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '">
        <input name="password2" size="25" maxlength="255" type="password" required minlength="8">
        </td></tr>
        <tr><td colspan="2" bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '">
        <b>' . ($lang_optionalinfo ?? 'Optional Information') . '</b>
        </td></tr>
        <tr><td width="*" bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '">
        <b>' . ($lang_homepage ?? 'Homepage') . '</b>
        </td><td width="300" bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '">
        <input name="homepage" size="25" maxlength="150" value="https://" type="url">
        </td></tr>
        <tr><td width="*" bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '">
        <b>' . ($lang_icq ?? 'ICQ') . '</b>
        </td><td width="300" bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '">
        <input name="icq" size="10" maxlength="10" type="number">
        </td></tr>
        <tr><td width="*" bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '" valign="top">
        <b>' . ($lang_biography ?? 'Biography') . '</b> <small>(' . ($lang_writesomethingaboutyou ?? 'Tell us about yourself') . ')</small>
        </td><td width="300" bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '">
        <textarea name="biography" cols="30" rows="5"></textarea>
        </td></tr>
        <tr><td colspan="2" bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '">
        <b>' . ($lang_othersettings ?? 'Other Settings') . '</b>
        </td></tr>
        <tr><td width="*" bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '" valign="top">
        <b>' . ($lang_signature ?? 'Signature') . '</b><br>
        <small><br>
        ' . ($lang_htmlcodeis ?? 'HTML is') . ' <b>' . Security::escape($settings['htmlcode'] ?? 'OFF') . '</b><br>
        <a href="bbcode.php" target="_blank">' . ($lang_bbcodeis ?? 'BBCode is') . ' <b>' . Security::escape($settings['bbcode'] ?? 'ON') . '</b></a><br>
        <a href="smilies.php" target="_blank">' . ($lang_smiliesare ?? 'Smilies are') . ' <b>' . Security::escape($settings['smilies'] ?? 'ON') . '</b></a><br>
        </small>
        </td><td width="300" bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '">
        <textarea name="signature" cols="30" rows="5"></textarea>
        </td></tr>
        <tr><td width="*" bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '">
        <b>' . ($lang_hideemail ?? 'Hide Email') . '</b>
        </td><td width="300" bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '">
        <input type="radio" name="hideemail" value="YES"> ' . ($lang_yes ?? 'yes') . ' <input type="radio" name="hideemail" value="NO" checked> ' . ($lang_no ?? 'no') . '
        </td></tr>
        <tr><td width="*" bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '">
        <b>' . ($lang_saveloginincookie ?? 'Remember Login') . '</b>
        </td><td width="300" bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '">
        <input type="radio" name="logincookie" value="YES" checked> ' . ($lang_yes ?? 'yes') . ' <input type="radio" name="logincookie" value="NO"> ' . ($lang_no ?? 'no') . '
        </td></tr>
        <tr><td colspan="2" align="center" bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '">
        <input type="submit" value="' . ($lang_send ?? 'Submit') . '"> <input type="reset" value="' . ($lang_reset ?? 'Reset') . '">
        </td></tr>
        </form>
        ';
    }
}
?>

</table>

<?php include __DIR__ . '/footer.inc.php'; ?>
