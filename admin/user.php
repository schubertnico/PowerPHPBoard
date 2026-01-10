<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - User Administration
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

use PowerPHPBoard\Database;
use PowerPHPBoard\Security;
use PowerPHPBoard\CSRF;

include __DIR__ . '/header.inc.php';
?>

<table border="0" cellpadding="2" cellspacing="1" width="100%">
<?php
$username = Security::getString('username', 'POST');

echo '
    <tr><td bgcolor="' . Security::escape($admin_tbl3) . '" colspan="4">
      <table border="0" cellpadding="0" cellspacing="0" width="100%">
      <tr><td>
      <b>User administration</b>
      </td><td align="right">
      <a href="adduser.php">Add user</a>
      </td></tr>
      </table>
    </td></tr>
    <tr><td bgcolor="' . Security::escape($admin_tbl2) . '" colspan="4"><br>
    Search an user:<br>
    <br>
    <center>
      <form action="user.php" method="post">
      ' . CSRF::getTokenField() . '
      <table border="0" cellpadding="4" cellspacing="0">
      <tr><td>
      <b>Username</b>
      </td><td>
      <input name="username" size="25" maxlength="50">
      </td></tr>
      <tr><td colspan="2" align="center">
      <input type="submit" value="Search user">
      </td></tr>
      </table>
      </form>
    </center>
    </td></tr>
    <tr><td bgcolor="' . Security::escape($admin_tbl3) . '" width="30%">
    <b>Username</b>
    </td><td bgcolor="' . Security::escape($admin_tbl3) . '" width="30%">
    <b>eMail adress</b>
    </td><td bgcolor="' . Security::escape($admin_tbl3) . '" width="20%">
    <b>User status</b>
    </td><td bgcolor="' . Security::escape($admin_tbl3) . '" width="20%">
    <b>Adminfunctions</b>
    </td></tr>
';

if ($username === '') {
    echo '
      <tr><td colspan="4" align="center" bgcolor="' . Security::escape($admin_tbl1) . '">
      Please insert an user name
      </td></tr>
    ';
} else {
    $users = $db->fetchAll(
        "SELECT * FROM ppb_users WHERE username LIKE ? ORDER BY id",
        ['%' . $username . '%']
    );

    if (count($users) === 0) {
        echo '
          <tr><td colspan="4" align="center" bgcolor="' . Security::escape($admin_tbl1) . '">
          No user found
          </td></tr>
        ';
    } else {
        $i = 1;
        foreach ($users as $row) {
            $tablebg = $i === 1 ? $admin_tbl1 : $admin_tbl2;
            $i = $i === 1 ? 2 : 1;

            echo '
            <tr><td bgcolor="' . Security::escape($tablebg) . '">
            ' . Security::escape($row['username']) . '
            </td><td bgcolor="' . Security::escape($tablebg) . '">
            <a href="mailto:' . Security::escape($row['email']) . '">' . Security::escape($row['email']) . '</a>
            </td><td bgcolor="' . Security::escape($tablebg) . '">
            ' . Security::escape($row['status']) . '
            </td><td bgcolor="' . Security::escape($tablebg) . '">
            <a href="edituser.php?userid=' . (int)$row['id'] . '">Edit user</a>
            </td></tr>
            ';
        }
    }
}
?>
</table>

<?php include __DIR__ . '/footer.inc.php'; ?>
