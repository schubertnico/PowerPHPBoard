<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Add User
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
    $adduser = Security::getInt('adduser', 'GET', 0);

    if ($adduser === 1) {
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

        // Checking all data
        if ($username === '' || $email1 === '' || $email2 === '' || $password1 === '' || $password2 === '') {
            echo '
                <tr><td bgcolor="' . Security::escape($admin_tbl3) . '">
                <b>Error message</b>
                </td></tr>
                <tr><td bgcolor="' . Security::escape($admin_tbl1) . '"><br>
                Please fill all required fields.<br><br>
                </td></tr>
                <tr><td bgcolor="' . Security::escape($admin_tbl2) . '" align="center">
                <a href="javascript:history.back()">Back to add user form</a>
                </td></tr>
            ';
        } elseif ($email1 !== $email2) {
            echo '
                <tr><td bgcolor="' . Security::escape($admin_tbl3) . '">
                <b>Error message</b>
                </td></tr>
                <tr><td bgcolor="' . Security::escape($admin_tbl1) . '"><br>
                Your eMail adresses are not correspond.<br><br>
                </td></tr>
                <tr><td bgcolor="' . Security::escape($admin_tbl2) . '" align="center">
                <a href="javascript:history.back()">Back to add user form</a>
                </td></tr>
            ';
        } elseif (!preg_match('/^([a-z0-9._&+\-]+@(([a-z0-9\-]+\.)+([a-z0-9\-]{2,3})))$/i', trim($email1))) {
            echo '
                <tr><td bgcolor="' . Security::escape($admin_tbl3) . '">
                <b>Error message</b>
                </td></tr>
                <tr><td bgcolor="' . Security::escape($admin_tbl1) . '"><br>
                Your both eMail adresses are different.<br><br>
                </td></tr>
                <tr><td bgcolor="' . Security::escape($admin_tbl2) . '" align="center">
                <a href="javascript:history.back()">Back to add user form</a>
                </td></tr>
            ';
        } elseif ($password1 !== $password2) {
            echo '
                <tr><td bgcolor="' . Security::escape($admin_tbl3) . '">
                <b>Error message</b>
                </td></tr>
                <tr><td bgcolor="' . Security::escape($admin_tbl1) . '"><br>
                Your both passwords are different.<br><br>
                </td></tr>
                <tr><td bgcolor="' . Security::escape($admin_tbl2) . '" align="center">
                <a href="javascript:history.back()">Back to add user form</a>
                </td></tr>
            ';
        } else {
            $icqInt = (int) $icq;
            $password = Security::hashPassword($password1);
            $username = strip_tags($username);
            $biography = strip_tags($biography);

            $existingUser = $db->fetchOne('SELECT * FROM ppb_users WHERE email = ?', [$email1]);

            if ($existingUser !== null) {
                echo '
                    <tr><td bgcolor="' . Security::escape($admin_tbl3) . '">
                    <b>Error message</b>
                    </td></tr>
                    <tr><td bgcolor="' . Security::escape($admin_tbl1) . '"><br>
                    This eMail adress already exists in our database.<br><br>
                    </td></tr>
                    <tr><td bgcolor="' . Security::escape($admin_tbl2) . '" align="center">
                    <a href="javascript:history.back()">Back to add user form</a> | <a href="sendpassword.php">Send password</a>
                    </td></tr>
                ';
            } else {
                // Insert new user into database
                $now = time();
                try {
                    $db->execute(
                        "INSERT INTO ppb_users (username, email, password, homepage, icq, biography, signature, hideemail, logincookie, status, registered) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Normal user', ?)",
                        [$username, $email1, $password, $homepage, $icqInt, $biography, $signature, $hideemail, $logincookie, $now]
                    );
                    CSRF::regenerate();

                    mail($email1, ($settings['boardtitle'] ?? '') . ' registration', '
Hello ' . $username . ',
you were registered successfully at ' . ($settings['boardurl'] ?? '') . '/ from ' . ($ppbuser['username'] ?? '') . '.
Here is your login information:

     Username:  ' . $username . '
     eMail   :  ' . $email1 . '
     Password:  ' . $password2 . '

You can log in here: ' . ($settings['boardurl'] ?? '') . '/login.php

Please do not answer to this automatic generated eMail!', 'FROM: ' . ($settings['adminemail'] ?? ''));

                    echo '
                        <tr><td bgcolor="' . Security::escape($admin_tbl3) . '">
                        <b>Status message</b>
                        </td></tr>
                        <tr><td bgcolor="' . Security::escape($admin_tbl1) . '"><br>
                        Your registration was successfull. You can log in now<br><br>
                        </td></tr>
                        <tr><td bgcolor="' . Security::escape($admin_tbl2) . '" align="center">
                        <a href="user.php?username=' . urlencode($username) . '">To user administration</a>
                        </td></tr>
                    ';
                } catch (Exception) {
                    echo '
                        <tr><td bgcolor="' . Security::escape($admin_tbl3) . '">
                        <b>Error message</b>
                        </td></tr>
                        <tr><td bgcolor="' . Security::escape($admin_tbl1) . '"><br>
                        There was an error while registration. Please try it again!<br><br>
                        </td></tr>
                        <tr><td bgcolor="' . Security::escape($admin_tbl2) . '" align="center">
                        <a href="javascript:history.back()">Back to add user form</a>
                        </td></tr>
                    ';
                }
            }
        }
    } else {
        // Registration form
        echo '
          <form action="adduser.php?adduser=1" method="post">
          ' . CSRF::getTokenField() . '
          <tr><td colspan="2" bgcolor="' . Security::escape($admin_tbl3) . '">
          <b>Required information</b>
          </td></tr>
          <tr><td width="*" bgcolor="' . Security::escape($admin_tbl1) . '">
          <b>Username</b>
          </td><td width="300" bgcolor="' . Security::escape($admin_tbl1) . '">
          <input name="username" size="25" maxlength="50">
          </td></tr>
          <tr><td width="*" bgcolor="' . Security::escape($admin_tbl2) . '">
          <b>eMail adress</b>
          </td><td width="300" bgcolor="' . Security::escape($admin_tbl2) . '">
          <input name="email1" size="25" maxlength="100">
          </td></tr>
          <tr><td width="*" bgcolor="' . Security::escape($admin_tbl2) . '">
          <b>eMail adress</b> <small>(Confirmation)</small>
          </td><td width="300" bgcolor="' . Security::escape($admin_tbl2) . '">
          <input name="email2" size="25" maxlength="100">
          </td></tr>
          <tr><td width="*" bgcolor="' . Security::escape($admin_tbl1) . '">
          <b>Password</b>
          </td><td width="300" bgcolor="' . Security::escape($admin_tbl1) . '">
          <input name="password1" size="25" maxlength="25" type="password">
          </td></tr>
          <tr><td width="*" bgcolor="' . Security::escape($admin_tbl1) . '">
          <b>Password</b> <small>(Confirmation)</small>
          </td><td width="300" bgcolor="' . Security::escape($admin_tbl1) . '">
          <input name="password2" size="25" maxlength="25" type="password">
          </td></tr>
          <tr><td colspan="2" bgcolor="' . Security::escape($admin_tbl3) . '">
          <b>Optional information</b>
          </td></tr>
          <tr><td width="*" bgcolor="' . Security::escape($admin_tbl1) . '">
          <b>Homepage</b>
          </td><td width="300" bgcolor="' . Security::escape($admin_tbl1) . '">
          <input name="homepage" size="25" maxlength="150" value="http://">
          </td></tr>
          <tr><td width="*" bgcolor="' . Security::escape($admin_tbl2) . '">
          <b>ICQ</b>
          </td><td width="300" bgcolor="' . Security::escape($admin_tbl2) . '">
          <input name="icq" size="10" maxlength="10">
          </td></tr>
          <tr><td width="*" bgcolor="' . Security::escape($admin_tbl1) . '" valign="top">
          <b>Biography</b> <small>(Write something about you)</small>
          </td><td width="300" bgcolor="' . Security::escape($admin_tbl1) . '">
          <textarea name="biography" cols="30" rows="5"></textarea>
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
          <textarea name="signature" cols="30" rows="5"></textarea>
          </td></tr>
          <tr><td width="*" bgcolor="' . Security::escape($admin_tbl2) . '">
          <b>Hide eMail adress</b>
          </td><td width="300" bgcolor="' . Security::escape($admin_tbl2) . '">
          <input type="radio" name="hideemail" value="YES"> yes <input type="radio" name="hideemail" value="NO" checked> no
          </td></tr>
          <tr><td width="*" bgcolor="' . Security::escape($admin_tbl1) . '">
          <b>Save login information in cookie?</b>
          </td><td width="300" bgcolor="' . Security::escape($admin_tbl1) . '">
          <input type="radio" name="logincookie" value="YES" checked> yes <input type="radio" name="logincookie" value="NO"> no
          </td></tr>
          <tr><td colspan="2" align="center" bgcolor="' . Security::escape($admin_tbl3) . '">
          <input type="submit" value="Add user"> <input type="reset" value="Reset information">
          </td></tr>
          </form>
        ';
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
