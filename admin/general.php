<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - General Administration
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
    $row = $db->fetchOne("SELECT * FROM ppb_config WHERE id = ?", [1]);

    if ($row !== null) {
        $editgeneral = Security::getInt('editgeneral', 'GET', 0);

        if ($editgeneral === 1) {
            // Validate CSRF token
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                CSRF::validateOrDie();
            }

            $boardtitle = Security::getString('boardtitle', 'POST');
            $boardurl = Security::getString('boardurl', 'POST');
            $adminemail = Security::getString('adminemail', 'POST');
            $header = Security::getString('header', 'POST');
            $footer = Security::getString('footer', 'POST');
            $bordercolor = Security::getString('bordercolor', 'POST');
            $tablebg1 = Security::getString('tablebg1', 'POST');
            $tablebg2 = Security::getString('tablebg2', 'POST');
            $tablebg3 = Security::getString('tablebg3', 'POST');
            $htmlcode = Security::getString('htmlcode', 'POST');
            $bbcode = Security::getString('bbcode', 'POST');
            $smilies = Security::getString('smilies', 'POST');
            $newthread = Security::getString('newthread', 'POST');
            $newpost = Security::getString('newpost', 'POST');
            $language = Security::getString('language', 'POST');

            if ($boardtitle === '' || $boardurl === '' || $adminemail === '' || $bordercolor === '' || $tablebg1 === '' || $tablebg2 === '' || $tablebg3 === '' || $newthread === '' || $newpost === '') {
                echo '
                    <tr><td bgcolor="' . Security::escape($admin_tbl3) . '">
                    <b>Error message</b>
                    </td></tr>
                    <tr><td bgcolor="' . Security::escape($admin_tbl2) . '"><br>
                    Please insert values for all fields!<br><br>
                    </td></tr>
                    <tr><td bgcolor="' . Security::escape($admin_tbl1) . '" align="center">
                    <a href="javascript:history.back()">Back to general administration form</a>
                    </td></tr>
                ';
            } else {
                $db->execute(
                    "UPDATE ppb_config SET boardtitle = ?, boardurl = ?, adminemail = ?, header = ?, footer = ?, bordercolor = ?, tablebg1 = ?, tablebg2 = ?, tablebg3 = ?, htmlcode = ?, bbcode = ?, smilies = ?, newthread = ?, newpost = ?, language = ? WHERE id = ?",
                    [$boardtitle, $boardurl, $adminemail, $header, $footer, $bordercolor, $tablebg1, $tablebg2, $tablebg3, $htmlcode, $bbcode, $smilies, $newthread, $newpost, $language, 1]
                );
                CSRF::regenerate();
                echo '
                    <tr><td bgcolor="' . Security::escape($admin_tbl3) . '">
                    <b>Status message</b>
                    </td></tr>
                    <tr><td bgcolor="' . Security::escape($admin_tbl2) . '"><br>
                    You edited the settings successfully!<br><br>
                    </td></tr>
                    <tr><td bgcolor="' . Security::escape($admin_tbl1) . '" align="center">
                    <a href="general.php">Back to general administration form</a>
                    </td></tr>
                ';
            }
        } else {
            $htmlcode_on = ($row['htmlcode'] === 'ON') ? 'checked' : '';
            $htmlcode_off = ($row['htmlcode'] !== 'ON') ? 'checked' : '';
            $bbcode_on = ($row['bbcode'] === 'ON') ? 'checked' : '';
            $bbcode_off = ($row['bbcode'] !== 'ON') ? 'checked' : '';
            $smilies_on = ($row['smilies'] === 'ON') ? 'checked' : '';
            $smilies_off = ($row['smilies'] !== 'ON') ? 'checked' : '';

            echo '
              <form action="general.php?editgeneral=1" method="post">
              ' . CSRF::getTokenField() . '
              <tr><td bgcolor="' . Security::escape($admin_tbl3) . '" colspan="2">
              <b>General information</b>
              </td></tr>
              <tr><td bgcolor="' . Security::escape($admin_tbl2) . '">
              <b>Boardtitle</b> <small>(The title of this board)</small>
              </td><td bgcolor="' . Security::escape($admin_tbl2) . '" width="300">
              <input name="boardtitle" size="25" maxlength="200" value="' . Security::escape($row['boardtitle']) . '">
              </td></tr>
              <tr><td bgcolor="' . Security::escape($admin_tbl1) . '">
              <b>Boardurl</b> <small>(The URL to this board)</small>
              </td><td bgcolor="' . Security::escape($admin_tbl1) . '">
              <input name="boardurl" size="25" maxlength="250" value="' . Security::escape($row['boardurl']) . '">
              </td></tr>
              <tr><td bgcolor="' . Security::escape($admin_tbl2) . '">
              <b>Adminemail</b> <small>(The eMail adress of the board administrator)</small>
              </td><td bgcolor="' . Security::escape($admin_tbl2) . '">
              <input name="adminemail" size="25" maxlength="100" value="' . Security::escape($row['adminemail']) . '">
              </td></tr>
              <tr><td bgcolor="' . Security::escape($admin_tbl1) . '">
              <b>Language</b> <small>(Choose your language here)</small>
              </td><td bgcolor="' . Security::escape($admin_tbl1) . '">
              <select name="language" size="1">
                <option value="' . Security::escape($row['language']) . '">' . Security::escape($row['language']) . '
                <option>-----
                <option value="English">English
                <option value="Deutsch-Sie">Deutsch-Sie
                <option value="Deutsch-Du">Deutsch-Du
              </select>
              </td></tr>
              <tr><td bgcolor="' . Security::escape($admin_tbl3) . '" colspan="2">
              <b>Default design</b>
              </td></tr>
              <tr><td bgcolor="' . Security::escape($admin_tbl1) . '">
              <b>Header</b> <small>(The default header file that will be included from the "inc" folder)</small>
              </td><td bgcolor="' . Security::escape($admin_tbl1) . '">
              <input name="header" size="25" maxlength="250" value="' . Security::escape($row['header']) . '">
              </td></tr>
              <tr><td bgcolor="' . Security::escape($admin_tbl2) . '">
              <b>Footer</b> <small>(The default footer file that will be included from the "inc" folder)</small>
              </td><td bgcolor="' . Security::escape($admin_tbl2) . '">
              <input name="footer" size="25" maxlength="250" value="' . Security::escape($row['footer']) . '">
              </td></tr>
              <tr><td bgcolor="' . Security::escape($admin_tbl1) . '">
              <b>Border</b> <small>(Default color for tableborder)</small>
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
              <b>Tablebackground</b> <small>(Default colors for tablebackgrounds)</small>
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
              <tr><td bgcolor="' . Security::escape($admin_tbl3) . '" colspan="2">
              <b>Feature settings</b>
              </td></tr>
              <tr><td bgcolor="' . Security::escape($admin_tbl2) . '">
              <b>HTML code</b> <small>(Choose if users can use HTML in postings and signatures)</small>
              </td><td bgcolor="' . Security::escape($admin_tbl2) . '" width="300">
              <input type="radio" name="htmlcode" value="ON" ' . $htmlcode_on . '> on <input type="radio" name="htmlcode" value="OFF" ' . $htmlcode_off . '> off
              </td></tr>
              <tr><td bgcolor="' . Security::escape($admin_tbl1) . '">
              <b>vB code</b> <small>(Choose if users can use BBcode in postings and signatures)</small>
              </td><td bgcolor="' . Security::escape($admin_tbl1) . '" width="300">
              <input type="radio" name="bbcode" value="ON" ' . $bbcode_on . '> on <input type="radio" name="bbcode" value="OFF" ' . $bbcode_off . '> off
              </td></tr>
              <tr><td bgcolor="' . Security::escape($admin_tbl2) . '">
              <b>Smilies</b> <small>(Choose if users can use Smilies in postings and signatures)</small>
              </td><td bgcolor="' . Security::escape($admin_tbl2) . '" width="300">
              <input type="radio" name="smilies" value="ON" ' . $smilies_on . '> on <input type="radio" name="smilies" value="OFF" ' . $smilies_off . '> off
              </td></tr>
              <tr><td colspan="2" align="center" bgcolor="' . Security::escape($admin_tbl3) . '">
              <input type="submit" value="Edit settings"> <input type="reset" value="Reset settings">
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
        Only administrators can edit the boardsettings!<br><br>
        </td></tr>
        <tr><td bgcolor="' . Security::escape($admin_tbl1) . '" align="center">
        <a href="../index.php">' . Security::escape($settings['boardtitle'] ?? '') . '</a>
        </td></tr>
    ';
}
?>

</table>

<?php include __DIR__ . '/footer.inc.php'; ?>
