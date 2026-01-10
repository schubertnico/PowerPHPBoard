<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Edit Board Category
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
if (($ppbuser['status'] ?? '') === 'Administrator') {
    $catid = Security::getInt('catid', 'GET', 0);
    $row = $db->fetchOne("SELECT * FROM ppb_boards WHERE id = ?", [$catid]);

    if ($row !== null) {
        $editboardcategory = Security::getInt('editboardcategory', 'GET', 0);

        if ($editboardcategory === 1) {
            // Validate CSRF token
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                CSRF::validateOrDie();
            }

            $title = Security::getString('title', 'POST');
            $header = Security::getString('header', 'POST');
            $footer = Security::getString('footer', 'POST');
            $bordercolor = Security::getString('bordercolor', 'POST');
            $tablebg1 = Security::getString('tablebg1', 'POST');
            $tablebg2 = Security::getString('tablebg2', 'POST');
            $tablebg3 = Security::getString('tablebg3', 'POST');
            $newthread = Security::getString('newthread', 'POST');
            $newpost = Security::getString('newpost', 'POST');

            if ($title === '' || $bordercolor === '' || $tablebg1 === '' || $tablebg2 === '' || $tablebg3 === '' || $newthread === '' || $newpost === '') {
                echo '
                    <tr><td bgcolor="' . Security::escape($admin_tbl3) . '">
                    <b>Error message</b>
                    </td></tr>
                    <tr><td bgcolor="' . Security::escape($admin_tbl2) . '"><br>
                    Please insert values for all fields!<br><br>
                    </td></tr>
                    <tr><td bgcolor="' . Security::escape($admin_tbl1) . '" align="center">
                    <a href="javascript:history.back()">Back to edit board form</a>
                    </td></tr>
                ';
            } else {
                $title = strip_tags($title);
                $title = trim($title);

                $db->execute(
                    "UPDATE ppb_boards SET title = ?, header = ?, footer = ?, bordercolor = ?, tablebg1 = ?, tablebg2 = ?, tablebg3 = ?, newthread = ?, newpost = ? WHERE id = ?",
                    [$title, $header, $footer, $bordercolor, $tablebg1, $tablebg2, $tablebg3, $newthread, $newpost, $catid]
                );
                CSRF::regenerate();
                echo '
                    <tr><td bgcolor="' . Security::escape($admin_tbl3) . '">
                    <b>Status message</b>
                    </td></tr>
                    <tr><td bgcolor="' . Security::escape($admin_tbl2) . '"><br>
                    You edited the boardcategory successfully!<br><br>
                    </td></tr>
                    <tr><td bgcolor="' . Security::escape($admin_tbl1) . '" align="center">
                    <a href="boards.php">Back to board administration</a>
                    </td></tr>
                ';
            }
        } else {
            echo '
              <form action="editboardcategory.php?editboardcategory=1&catid=' . (int)$row['id'] . '" method="post">
              ' . CSRF::getTokenField() . '
              <tr><td bgcolor="' . Security::escape($admin_tbl3) . '" colspan="2">
              <b>Edit boardcategory</b>
              </td></tr>
              <tr><td bgcolor="' . Security::escape($admin_tbl2) . '">
              <b>Categorytitle</b>
              </td><td bgcolor="' . Security::escape($admin_tbl2) . '" width="300">
              <input name="title" size="25" maxlength="100" value="' . Security::escape($row['title']) . '">
              </td></tr>
              <tr><td bgcolor="' . Security::escape($admin_tbl1) . '">
              <b>Header</b> <small>(The header file that will be included from the "inc" folder)</small>
              </td><td bgcolor="' . Security::escape($admin_tbl1) . '">
              <input name="header" size="25" maxlength="250" value="' . Security::escape($row['header']) . '">
              </td></tr>
              <tr><td bgcolor="' . Security::escape($admin_tbl2) . '">
              <b>Footer</b> <small>(The footer file that will be included from the "inc" folder)</small>
              </td><td bgcolor="' . Security::escape($admin_tbl2) . '">
              <input name="footer" size="25" maxlength="250" value="' . Security::escape($row['footer']) . '">
              </td></tr>
              <tr><td bgcolor="' . Security::escape($admin_tbl1) . '">
              <b>Border</b> <small>(Color for tableborder)</small>
              </td><td bgcolor="' . Security::escape($admin_tbl1) . '">
                <table border="0" cellpadding="0" cellspacing="2" width="100%">
                <tr><td>
                <input name="bordercolor" size="7" maxlength="7" value="' . Security::escape($row['bordercolor']) . '">
                </td><td bgcolor="' . Security::escape($row['bordercolor']) . '" width="50">
                &nbsp;
                </td></tr>
                </table>
              </td></tr>
              <tr><td bgcolor="' . Security::escape($admin_tbl2) . '" valign="top">
              <b>Tablebackground</b> <small>(Colors for tablebackgrounds)</small>
              </td><td bgcolor="' . Security::escape($admin_tbl2) . '">
                <table border="0" cellpadding="0" cellspacing="2" width="100%">
                <tr><td>
                <input name="tablebg1" size="7" maxlength="7" value="' . Security::escape($row['tablebg1']) . '"><br>
                </td><td bgcolor="' . Security::escape($row['tablebg1']) . '" width="50">
                &nbsp;
                </td></tr>
                <tr><td>
                <input name="tablebg2" size="7" maxlength="7" value="' . Security::escape($row['tablebg2']) . '"><br>
                </td><td bgcolor="' . Security::escape($row['tablebg2']) . '" width="50">
                &nbsp;
                </td></tr>
                <tr><td>
                <input name="tablebg3" size="7" maxlength="7" value="' . Security::escape($row['tablebg3']) . '"><br>
                </td><td bgcolor="' . Security::escape($row['tablebg3']) . '" width="50">
                &nbsp;
                </td></tr>
                </table>
              </td></tr>
              <tr><td bgcolor="' . Security::escape($admin_tbl1) . '">
              <b>New thread button</b> <small>(The 120 x 20 picture for the \'New thread\' button)</small>
              </td><td bgcolor="' . Security::escape($admin_tbl1) . '" width="300">
              <input name="newthread" size="25" maxlength="250" value="' . Security::escape($row['newthread']) . '">
              </td></tr>
              <tr><td bgcolor="' . Security::escape($admin_tbl2) . '">
              <b>New post button</b> <small>(The 120 x 20 picture for the \'New post\' button)</small>
              </td><td bgcolor="' . Security::escape($admin_tbl2) . '" width="300">
              <input name="newpost" size="25" maxlength="250" value="' . Security::escape($row['newpost']) . '">
              </td></tr>
              <tr><td colspan="2" align="center" bgcolor="' . Security::escape($admin_tbl3) . '">
              <input type="submit" value="Edit boardcategory">
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
            Please select a boardcategory!<br><br>
            </td></tr>
            <tr><td bgcolor="' . Security::escape($admin_tbl1) . '" align="center">
            <a href="boards.php">Back to board administration</a>
            </td></tr>
        ';
    }
} else {
    echo '
        <tr><td bgcolor="' . Security::escape($admin_tbl3) . '">
        <b>Error message</b>
        </td></tr>
        <tr><td bgcolor="' . Security::escape($admin_tbl2) . '"><br>
        Only administrators can edit boards!<br><br>
        </td></tr>
        <tr><td bgcolor="' . Security::escape($admin_tbl1) . '" align="center">
        <a href="../index.php">' . Security::escape($settings['boardtitle'] ?? '') . '</a>
        </td></tr>
    ';
}
?>

</table>

<?php include __DIR__ . '/footer.inc.php'; ?>
