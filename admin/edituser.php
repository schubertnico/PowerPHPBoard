<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Edit User
 *
 * MIT License
 *
 * Copyright (c) 2024 PowerScripts
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
 */

use PowerPHPBoard\CSRF;
use PowerPHPBoard\Database;
use PowerPHPBoard\Security;

include __DIR__ . '/header.inc.php';
?>

<table border="0" cellpadding="2" cellspacing="1" width="100%">
<?php
if (($ppbuser['status'] ?? '') === 'Administrator') {
    $userid = Security::getInt('userid', 'GET', 0);
    $edituser = Security::getInt('edituser', 'GET', 0);

    $row = $db->fetchOne('SELECT * FROM ppb_users WHERE id = ?', [$userid]);

    if ($row === null) {
        echo '
            <tr><td bgcolor="' . Security::escape($admin_tbl3) . '">
            <b>Error message</b>
            </td></tr>
            <tr><td bgcolor="' . Security::escape($admin_tbl1) . '"><br>
            There is no user registered with this ID!<br><br>
            </td></tr>
            <tr><td bgcolor="' . Security::escape($admin_tbl2) . '" align="center">
            <a href="javascript:history.back()">Back to search results</a>
            </td></tr>
        ';
    } else {
        if ($edituser === 1) {
            // Verify CSRF token
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                CSRF::validateOrDie();
            }

            $username = Security::getString('username', 'POST');
            $email1 = Security::getString('email1', 'POST');
            $email2 = Security::getString('email2', 'POST');
            $password1 = Security::getString('password1', 'POST');
            $password2 = Security::getString('password2', 'POST');
            $homepage = Security::getString('homepage', 'POST');
            $icq = Security::getString('icq', 'POST');
            $biography = Security::getString('biography', 'POST');
            $signature = Security::getString('signature', 'POST');
            $hideemail = Security::getString('hideemail', 'POST', 'NO');
            $logincookie = Security::getString('logincookie', 'POST', 'YES');
            $status = Security::getString('status', 'POST', 'Normal user');

            if ($username === '' || $email1 === '' || $email2 === '' || $password1 === '' || $password2 === '') {
                echo '
                    <tr><td bgcolor="' . Security::escape($admin_tbl3) . '">
                    <b>Error message</b>
                    </td></tr>
                    <tr><td bgcolor="' . Security::escape($admin_tbl1) . '"><br>
                    Please fill all required fields.<br><br>
                    </td></tr>
                    <tr><td bgcolor="' . Security::escape($admin_tbl2) . '" align="center">
                    <a href="user.php">Back to edit user form</a>
                    </td></tr>
                ';
            } elseif ($email1 !== $email2) {
                echo '
                    <tr><td bgcolor="' . Security::escape($admin_tbl3) . '">
                    <b>Error message</b>
                    </td></tr>
                    <tr><td bgcolor="' . Security::escape($admin_tbl1) . '"><br>
                    The eMail adresses are different.<br><br>
                    </td></tr>
                    <tr><td bgcolor="' . Security::escape($admin_tbl2) . '" align="center">
                    <a href="javascript:history.back()">Back to edit user form</a>
                    </td></tr>
                ';
            } elseif (!preg_match('/^([a-z0-9._&+\-]+@(([a-z0-9\-]+\.)+([a-z0-9\-]{2,3})))$/i', trim($email1))) {
                echo '
                    <tr><td bgcolor="' . Security::escape($admin_tbl3) . '">
                    <b>Error message</b>
                    </td></tr>
                    <tr><td bgcolor="' . Security::escape($admin_tbl1) . '"><br>
                    The eMail adress is not correct.<br><br>
                    </td></tr>
                    <tr><td bgcolor="' . Security::escape($admin_tbl2) . '" align="center">
                    <a href="javascript:history.back()">Back to edit user form</a>
                    </td></tr>
                ';
            } elseif ($password1 !== $password2) {
                echo '
                    <tr><td bgcolor="' . Security::escape($admin_tbl3) . '">
                    <b>Error message</b>
                    </td></tr>
                    <tr><td bgcolor="' . Security::escape($admin_tbl1) . '"><br>
                    The passwords are different.<br><br>
                    </td></tr>
                    <tr><td bgcolor="' . Security::escape($admin_tbl2) . '" align="center">
                    <a href="javascript:history.back()">Back to edit user form</a>
                    </td></tr>
                ';
            } else {
                $icqInt = (int) $icq;
                $password = Security::hashPassword($password1);
                $username = strip_tags($username);
                $biography = strip_tags($biography);

                $existingUser = $db->fetchOne(
                    'SELECT * FROM ppb_users WHERE email = ? AND id != ?',
                    [$email1, $row['id']]
                );

                if ($existingUser !== null) {
                    echo '
                        <tr><td bgcolor="' . Security::escape($admin_tbl3) . '">
                        <b>Error message</b>
                        </td></tr>
                        <tr><td bgcolor="' . Security::escape($admin_tbl1) . '"><br>
                        This eMail adress already exists in our database.<br><br>
                        </td></tr>
                        <tr><td bgcolor="' . Security::escape($admin_tbl2) . '" align="center">
                        <a href="javascript:history.back()">Back to edit user form</a>
                        </td></tr>
                    ';
                } else {
                    // Update information in database
                    try {
                        $db->execute(
                            'UPDATE ppb_users SET username = ?, email = ?, password = ?, homepage = ?, icq = ?, biography = ?, signature = ?, hideemail = ?, logincookie = ?, status = ? WHERE id = ?',
                            [$username, $email1, $password, $homepage, $icqInt, $biography, $signature, $hideemail, $logincookie, $status, $row['id']]
                        );
                        CSRF::regenerate();
                        echo '
                            <tr><td bgcolor="' . Security::escape($admin_tbl3) . '">
                            <b>Status message</b>
                            </td></tr>
                            <tr><td bgcolor="' . Security::escape($admin_tbl1) . '"><br>
                            You edited the user successfully.<br><br>
                            </td></tr>
                            <tr><td bgcolor="' . Security::escape($admin_tbl2) . '" align="center">
                            <a href="index.php">Home</a>
                            </td></tr>
                        ';
                    } catch (Exception) {
                        echo '
                            <tr><td bgcolor="' . Security::escape($admin_tbl3) . '">
                            <b>Error message</b>
                            </td></tr>
                            <tr><td bgcolor="' . Security::escape($admin_tbl1) . '"><br>
                            There was an error while updateing the information. Please try it again!<br><br>
                            </td></tr>
                            <tr><td bgcolor="' . Security::escape($admin_tbl2) . '" align="center">
                            <a href="javascript:history.back()">Back to edit user form</a>
                            </td></tr>
                        ';
                    }
                }
            }
        } else {
            $hideemail_checked_yes = ($row['hideemail'] === 'YES') ? 'checked' : '';
            $hideemail_checked_no = ($row['hideemail'] !== 'YES') ? 'checked' : '';
            $logincookie_checked_yes = ($row['logincookie'] === 'YES') ? 'checked' : '';
            $logincookie_checked_no = ($row['logincookie'] !== 'YES') ? 'checked' : '';

            // For display purposes, decode legacy base64 password (migration)
            $displayPassword = '';
            if (Security::isLegacyHash($row['password'])) {
                $displayPassword = base64_decode((string) $row['password']);
            }

            echo '
              <form action="edituser.php?edituser=1&userid=' . (int) $row['id'] . '" method="post">
              ' . CSRF::getTokenField() . '
              <tr><td colspan="2" bgcolor="' . Security::escape($admin_tbl3) . '">
              <b>Required information</b>
              </td></tr>
              <tr><td width="*" bgcolor="' . Security::escape($admin_tbl1) . '">
              <b>Username</b>
              </td><td width="300" bgcolor="' . Security::escape($admin_tbl1) . '">
              <input name="username" size="25" maxlength="50" value="' . Security::escape($row['username']) . '">
              </td></tr>
              <tr><td width="*" bgcolor="' . Security::escape($admin_tbl2) . '">
              <b>eMail adress</b>
              </td><td width="300" bgcolor="' . Security::escape($admin_tbl2) . '">
              <input name="email1" size="25" maxlength="100" value="' . Security::escape($row['email']) . '">
              </td></tr>
              <tr><td width="*" bgcolor="' . Security::escape($admin_tbl2) . '">
              <b>eMail adress</b> <small>(Confirmation)</small>
              </td><td width="300" bgcolor="' . Security::escape($admin_tbl2) . '">
              <input name="email2" size="25" maxlength="100" value="' . Security::escape($row['email']) . '">
              </td></tr>
              <tr><td width="*" bgcolor="' . Security::escape($admin_tbl1) . '">
              <b>Password</b>
              </td><td width="300" bgcolor="' . Security::escape($admin_tbl1) . '">
              <input name="password1" size="25" maxlength="25" type="password" value="' . Security::escape($displayPassword) . '">
              </td></tr>
              <tr><td width="*" bgcolor="' . Security::escape($admin_tbl1) . '">
              <b>Password</b> <small>(Confirmation)</small>
              </td><td width="300" bgcolor="' . Security::escape($admin_tbl1) . '">
              <input name="password2" size="25" maxlength="25" type="password" value="' . Security::escape($displayPassword) . '">
              </td></tr>
              <tr><td colspan="2" bgcolor="' . Security::escape($admin_tbl3) . '">
              <b>Optional information</b>
              </td></tr>
              <tr><td width="*" bgcolor="' . Security::escape($admin_tbl1) . '">
              <b>Homepage</b>
              </td><td width="300" bgcolor="' . Security::escape($admin_tbl1) . '">
              <input name="homepage" size="25" maxlength="150" value="' . Security::escape($row['homepage']) . '">
              </td></tr>
              <tr><td width="*" bgcolor="' . Security::escape($admin_tbl2) . '">
              <b>ICQ</b>
              </td><td width="300" bgcolor="' . Security::escape($admin_tbl2) . '">
              <input name="icq" size="10" maxlength="10" value="' . Security::escape((string) $row['icq']) . '">
              </td></tr>
              <tr><td width="*" bgcolor="' . Security::escape($admin_tbl1) . '" valign="top">
              <b>Biography</b> <small>(Write something about you)</small>
              </td><td width="300" bgcolor="' . Security::escape($admin_tbl1) . '">
              <textarea name="biography" cols="30" rows="5">' . Security::escape($row['biography']) . '</textarea>
              </td></tr>
              <tr><td colspan="2" bgcolor="' . Security::escape($admin_tbl3) . '">
              <b>Other settings</b>
              </td></tr>
              <tr><td width="*" bgcolor="' . Security::escape($admin_tbl1) . '" valign="top">
              <b>Signature</b><br>
              <small><br>
              HTML Code is <b>' . Security::escape($settings['htmlcode'] ?? '') . '</b><br>
              <a href="bbcode.php" target="_new">vB Code</a> is <b>' . Security::escape($settings['bbcode'] ?? '') . '</b><br>
              <a href="smilies.php" target="_new">Smilies</a> are <b>' . Security::escape($settings['smilies'] ?? '') . '</b><br>
              </small>
              </td><td width="300" bgcolor="' . Security::escape($admin_tbl1) . '">
              <textarea name="signature" cols="30" rows="5">' . Security::escape($row['signature']) . '</textarea>
              </td></tr>
              <tr><td width="*" bgcolor="' . Security::escape($admin_tbl2) . '">
              <b>Hide eMail adress</b>
              </td><td width="300" bgcolor="' . Security::escape($admin_tbl2) . '">
              <input type="radio" name="hideemail" value="YES" ' . $hideemail_checked_yes . '> yes <input type="radio" name="hideemail" value="NO" ' . $hideemail_checked_no . '> no
              </td></tr>
              <tr><td width="*" bgcolor="' . Security::escape($admin_tbl1) . '">
              <b>Save login information in cookie?</b>
              </td><td width="300" bgcolor="' . Security::escape($admin_tbl1) . '">
              <input type="radio" name="logincookie" value="YES" ' . $logincookie_checked_yes . '> yes <input type="radio" name="logincookie" value="NO" ' . $logincookie_checked_no . '> no
              </td></tr>
              <tr><td width="*" bgcolor="' . Security::escape($admin_tbl2) . '">
              <b>User status</b>
              </td><td width="300" bgcolor="' . Security::escape($admin_tbl2) . '">
              <select name="status" size="1">
                <option value="' . Security::escape($row['status']) . '">' . Security::escape($row['status']) . '
                <option>-----
                <option value="Deactivated">Deactivated
                <option value="Normal user">Normal user
                <option value="Administrator">Administrator
              </select>
              </td></tr>
              <tr><td colspan="2" align="center" bgcolor="' . Security::escape($admin_tbl3) . '">
              <input type="submit" value="Edit profile"> <input type="reset" value="Reset information">
              </td></tr>
              </form>
            ';
        }
    }
} else {
    echo '
        <tr><td bgcolor="' . Security::escape($admin_tbl3) . '">
        <b>Error message</b>
        </td></tr>
        <tr><td bgcolor="' . Security::escape($admin_tbl2) . '"><br>
        Only administrators can add users!<br><br>
        </td></tr>
        <tr><td bgcolor="' . Security::escape($admin_tbl1) . '" align="center">
        <a href="../index.php">' . Security::escape($settings['boardtitle'] ?? '') . '</a>
        </td></tr>
    ';
}
?>
</table>

<?php include __DIR__ . '/footer.inc.php'; ?>
