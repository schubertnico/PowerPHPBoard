<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Add Board Category
 *
 * MIT License
 *
 * Copyright (c) 2026 PowerScripts
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
use PowerPHPBoard\Security;

include __DIR__ . '/header.inc.php';
?>

<table border="0" cellpadding="2" cellspacing="1" width="100%">

<?php
if (($ppbuser['status'] ?? '') === 'Administrator') {
    $addboardcategory = Security::getInt('addboardcategory', 'GET', 0);

    if ($addboardcategory === 1 && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validate CSRF token
        CSRF::validateOrDie();

        $title = Security::getString('title', 'POST');

        if ($title === '') {
            echo '
                <tr><td bgcolor="' . Security::escape($admin_tbl3) . '">
                <b>Error message</b>
                </td></tr>
                <tr><td bgcolor="' . Security::escape($admin_tbl2) . '"><br>
                Please insert a category title!<br><br>
                </td></tr>
                <tr><td bgcolor="' . Security::escape($admin_tbl1) . '" align="center">
                <a href="javascript:history.back()">Back to add category form</a>
                </td></tr>
            ';
        } else {
            $title = trim(strip_tags($title));

            // Get values with defaults from global settings
            $header = Security::getString('header', 'POST') ?: ($settings['header'] ?? '');
            $footer = Security::getString('footer', 'POST') ?: ($settings['footer'] ?? '');
            $bordercolor = Security::getString('bordercolor', 'POST') ?: ($settings['bordercolor'] ?? '#000000');
            $tablebg1 = Security::getString('tablebg1', 'POST') ?: ($settings['tablebg1'] ?? '#FFFFFF');
            $tablebg2 = Security::getString('tablebg2', 'POST') ?: ($settings['tablebg2'] ?? '#F0F0F0');
            $tablebg3 = Security::getString('tablebg3', 'POST') ?: ($settings['tablebg3'] ?? '#E0E0E0');
            $newthread = Security::getString('newthread', 'POST') ?: ($settings['newthread'] ?? '');
            $newpost = Security::getString('newpost', 'POST') ?: ($settings['newpost'] ?? '');

            $db->execute(
                "INSERT INTO ppb_boards (title, type, header, footer, bordercolor, tablebg1, tablebg2, tablebg3, newthread, newpost) VALUES (?, 'Boardcategory', ?, ?, ?, ?, ?, ?, ?, ?)",
                [$title, $header, $footer, $bordercolor, $tablebg1, $tablebg2, $tablebg3, $newthread, $newpost]
            );
            CSRF::regenerate();
            echo '
                <tr><td bgcolor="' . Security::escape($admin_tbl3) . '">
                <b>Status message</b>
                </td></tr>
                <tr><td bgcolor="' . Security::escape($admin_tbl2) . '"><br>
                You created the new boardcategory successfully!<br><br>
                </td></tr>
                <tr><td bgcolor="' . Security::escape($admin_tbl1) . '" align="center">
                <a href="boards.php">Back to board administration</a>
                </td></tr>
            ';
        }
    } else {
        echo '
          <form action="addboardcategory.php?addboardcategory=1" method="post">
          ' . CSRF::getTokenField() . '
          <tr><td bgcolor="' . Security::escape($admin_tbl3) . '" colspan="2">
          <b>Add boardcategory</b>
          </td></tr>
          <tr><td bgcolor="' . Security::escape($admin_tbl2) . '">
          <b>Categorytitle</b>
          </td><td bgcolor="' . Security::escape($admin_tbl2) . '" width="300">
          <input name="title" size="25" maxlength="100">
          </td></tr>
          <tr><td bgcolor="' . Security::escape($admin_tbl1) . '">
          <b>Header</b> <small>(The header file that will be included from the "inc" folder)</small>
          </td><td bgcolor="' . Security::escape($admin_tbl1) . '">
          <input name="header" size="25" maxlength="250" value="' . Security::escape($settings['header'] ?? '') . '">
          </td></tr>
          <tr><td bgcolor="' . Security::escape($admin_tbl2) . '">
          <b>Footer</b> <small>(The footer file that will be included from the "inc" folder)</small>
          </td><td bgcolor="' . Security::escape($admin_tbl2) . '">
          <input name="footer" size="25" maxlength="250" value="' . Security::escape($settings['footer'] ?? '') . '">
          </td></tr>
          <tr><td bgcolor="' . Security::escape($admin_tbl1) . '">
          <b>Border</b> <small>(Color for tableborder)</small>
          </td><td bgcolor="' . Security::escape($admin_tbl1) . '">
            <table border="0" cellpadding="0" cellspacing="2" width="100%">
            <tr><td>
            <input name="bordercolor" size="7" maxlength="7" value="' . Security::escape($settings['bordercolor'] ?? '') . '">
            </td><td bgcolor="' . Security::escape($settings['bordercolor'] ?? '') . '" width="50">
            &nbsp;
            </td></tr>
            </table>
          </td></tr>
          <tr><td bgcolor="' . Security::escape($admin_tbl2) . '" valign="top">
          <b>Tablebackground</b> <small>(Colors for tablebackgrounds)</small>
          </td><td bgcolor="' . Security::escape($admin_tbl2) . '">
            <table border="0" cellpadding="0" cellspacing="2" width="100%">
            <tr><td>
            <input name="tablebg1" size="7" maxlength="7" value="' . Security::escape($settings['tablebg1'] ?? '') . '"><br>
            </td><td bgcolor="' . Security::escape($settings['tablebg1'] ?? '') . '" width="50">
            &nbsp;
            </td></tr>
            <tr><td>
            <input name="tablebg2" size="7" maxlength="7" value="' . Security::escape($settings['tablebg2'] ?? '') . '"><br>
            </td><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '') . '" width="50">
            &nbsp;
            </td></tr>
            <tr><td>
            <input name="tablebg3" size="7" maxlength="7" value="' . Security::escape($settings['tablebg3'] ?? '') . '"><br>
            </td><td bgcolor="' . Security::escape($settings['tablebg3'] ?? '') . '" width="50">
            &nbsp;
            </td></tr>
            </table>
          </td></tr>
          <tr><td bgcolor="' . Security::escape($admin_tbl1) . '">
          <b>New thread button</b> <small>(The 120 x 20 picture for the \'New thread\' button)</small>
          </td><td bgcolor="' . Security::escape($admin_tbl1) . '" width="300">
          <input name="newthread" size="25" maxlength="250" value="' . Security::escape($settings['newthread'] ?? '') . '">
          </td></tr>
          <tr><td bgcolor="' . Security::escape($admin_tbl2) . '">
          <b>New post button</b> <small>(The 120 x 20 picture for the \'New post\' button)</small>
          </td><td bgcolor="' . Security::escape($admin_tbl2) . '" width="300">
          <input name="newpost" size="25" maxlength="250" value="' . Security::escape($settings['newpost'] ?? '') . '">
          </td></tr>
          <tr><td colspan="2" align="center" bgcolor="' . Security::escape($admin_tbl3) . '">
          <input type="submit" value="Add boardcategory">
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
