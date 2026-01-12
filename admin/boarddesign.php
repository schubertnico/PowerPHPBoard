<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Board Design Administration
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

use PowerPHPBoard\Security;

include __DIR__ . '/header.inc.php';
?>

<table border="0" cellpadding="2" cellspacing="1" width="100%">
<?php
$boarddesign = Security::getInt('boarddesign', 'GET', 0);

if ($boarddesign === 1) {
    if ($catid > 0) {
        $category = $db->fetchOne(
            'SELECT * FROM ppb_boards WHERE id = ? AND type = ?',
            [$catid, 'Boardcategory']
        );

        if ($category !== null) {
            $db->execute(
                'UPDATE ppb_boards SET header = ?, footer = ?, bordercolor = ?, tablebg1 = ?, tablebg2 = ?, tablebg3 = ?, newthread = ?, newpost = ? WHERE catid = ?',
                [
                    $category['header'],
                    $category['footer'],
                    $category['bordercolor'],
                    $category['tablebg1'],
                    $category['tablebg2'],
                    $category['tablebg3'],
                    $category['newthread'],
                    $category['newpost'],
                    $catid,
                ]
            );

            echo '
            <tr><td bgcolor="' . Security::escape($admin_tbl3) . '">
            <b>Status message</b>
            </td></tr>
            <tr><td bgcolor="' . Security::escape($admin_tbl2) . '">
            <br>
            The design of all boards in this category was set to the category design!<br>
            <br>
            </td></tr>
            <tr><td bgcolor="' . Security::escape($admin_tbl1) . '" align="center">
            <a href="boards.php">Back to boardadministration</a>
            </td></tr>
            ';
        }
    } else {
        $db->execute(
            'UPDATE ppb_boards SET header = ?, footer = ?, bordercolor = ?, tablebg1 = ?, tablebg2 = ?, tablebg3 = ?, newthread = ?, newpost = ?',
            [
                $settings['header'] ?? '',
                $settings['footer'] ?? '',
                $settings['bordercolor'] ?? '#000000',
                $settings['tablebg1'] ?? '#ffffff',
                $settings['tablebg2'] ?? '#eeeeee',
                $settings['tablebg3'] ?? '#cccccc',
                $settings['newthread'] ?? 'images/newthread.gif',
                $settings['newpost'] ?? 'images/newpost.gif',
            ]
        );

        echo '
        <tr><td bgcolor="' . Security::escape($admin_tbl3) . '">
        <b>Status message</b>
        </td></tr>
        <tr><td bgcolor="' . Security::escape($admin_tbl2) . '">
        <br>
        The design of all boards and boardcategorys was set to the default design!<br>
        <br>
        </td></tr>
        <tr><td bgcolor="' . Security::escape($admin_tbl1) . '" align="center">
        <a href="boards.php">Back to boardadministration</a>
        </td></tr>
        ';
    }
} else {
    if ($catid > 0) {
        $category = $db->fetchOne(
            'SELECT * FROM ppb_boards WHERE id = ? AND type = ?',
            [$catid, 'Boardcategory']
        );

        if ($category !== null) {
            echo '
            <tr><td bgcolor="' . Security::escape($admin_tbl3) . '">
            <b>Set all boards to category design</b>
            </td></tr>
            <tr><td bgcolor="' . Security::escape($admin_tbl2) . '">
            <br>
            Do you really want to set all boards in this category to the category design settings?<br>
            <br>
            </td></tr>
            <tr><td bgcolor="' . Security::escape($admin_tbl1) . '" align="center">
            <a href="boarddesign.php?boarddesign=1&catid=' . (int) $catid . '">Yes, set all to category design!</a> | <a href="boards.php">No, don\'t set all to category design</a>
            </td></tr>
            ';
        }
    } else {
        echo '
        <tr><td bgcolor="' . Security::escape($admin_tbl3) . '">
        <b>Set all boards to default design</b>
        </td></tr>
        <tr><td bgcolor="' . Security::escape($admin_tbl2) . '">
        <br>
        Do you really want to set all boards and boardcategorys to the default design settings?<br>
        <br>
        </td></tr>
        <tr><td bgcolor="' . Security::escape($admin_tbl1) . '" align="center">
        <a href="boarddesign.php?boarddesign=1">Yes, set all to default design!</a> | <a href="boards.php">No, don\'t set all to default design</a>
        </td></tr>
        ';
    }
}
?>

</center>
</td></tr>
</table>

<?php include __DIR__ . '/footer.inc.php'; ?>
