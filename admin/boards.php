<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Board Administration
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
<tr><td bgcolor="<?php echo Security::escape($admin_tbl3); ?>" colspan="4">
  <table border="0" cellpadding="0" cellspacing="0" width="100%">
  <tr><td>
  <b>Board administration</b>
  </td><td align="right">
  <a href="addboard.php">Add board</a> | <a href="addboardcategory.php">Add boardcategory</a> | <a href="boarddesign.php">All boards default design</a>
  </td></tr>
  </table>
</td></tr>
<tr><td bgcolor="<?php echo Security::escape($admin_tbl1); ?>" width="20">
&nbsp;
</td><td bgcolor="<?php echo Security::escape($admin_tbl2); ?>" width="*">
<b>Board</b>
</td><td bgcolor="<?php echo Security::escape($admin_tbl1); ?>" width="250">
<b>Moderated by</b>
</td><td bgcolor="<?php echo Security::escape($admin_tbl2); ?>" width="250">
<b>Adminfunctions</b>
</td></tr>

<?php
if ($catid > 0) {
    $categories = $db->fetchAll('SELECT * FROM ppb_boards WHERE type = ? AND id = ?', ['Boardcategory', $catid]);
} else {
    $categories = $db->fetchAll('SELECT * FROM ppb_boards WHERE type = ? ORDER BY id', ['Boardcategory']);
}

if (count($categories) > 0) {
    foreach ($categories as $row) {
        echo '
            <tr><td bgcolor="' . Security::escape($admin_tbl3) . '" colspan="4">
            <a href="boards.php?catid=' . (int) $row['id'] . '"><b>' . Security::escape($row['title']) . '</b></a> <a href="editboardcategory.php?catid=' . (int) $row['id'] . '">Edit boardcategory</a> | <a href="boarddesign.php?catid=' . (int) $row['id'] . '">All boards in this category to category design</a>
            </td></tr>
        ';

        $boards = $db->fetchAll('SELECT * FROM ppb_boards WHERE type = ? AND catid = ? ORDER BY title', ['Board', $row['id']]);

        if (count($boards) > 0) {
            foreach ($boards as $row2) {
                echo '
                    <tr><td bgcolor="' . Security::escape($admin_tbl1) . '" align="center">
                ';
                if ($row2['status'] === 'Closed') {
                    echo '<img src="../images/lockeddir.gif" width="13" height="16">';
                } elseif ($row2['status'] === 'Private') {
                    echo '<img src="../images/private.gif" width="13" height="16">';
                }
                echo '
                    </td><td bgcolor="' . Security::escape($admin_tbl2) . '">
                    <b><a href="../showboard.php?boardid=' . (int) $row2['id'] . '">' . Security::escape($row2['title']) . '</a></b><br>
                    <small>' . Security::escape($row2['description']) . '</small>
                    </td><td bgcolor="' . Security::escape($admin_tbl1) . '">
                    <small>
                ';
                if (!empty($row2['mods'])) {
                    $mods = explode(',', (string) $row2['mods']);
                    $modnum = count($mods);
                    for ($i = 0; $i < $modnum; $i++) {
                        $modEmail = trim($mods[$i]);
                        if ($modEmail !== '') {
                            $modUser = $db->fetchOne('SELECT * FROM ppb_users WHERE email = ?', [$modEmail]);
                            if ($modUser !== null) {
                                echo '<a href="../showprofile.php?userid=' . (int) $modUser['id'] . '">' . Security::escape($modUser['username']) . '</a> ';
                            }
                        }
                    }
                } else {
                    echo '-';
                }
                echo '
                    </small>
                    </td><td bgcolor="' . Security::escape($admin_tbl2) . '">
                    <a href="editboard.php?boardid=' . (int) $row2['id'] . '">Edit board</a>
                    </td></tr>
                ';
            }
        } else {
            echo '
                <tr><td bgcolor="' . Security::escape($admin_tbl2) . '" colspan="4" align="center">
                <b>No boards in this category.</b>
                </td></tr>
            ';
        }
    }
} else {
    echo '
        <tr><td colspan="4" bgcolor="' . Security::escape($admin_tbl3) . '" align="center">
        <b>No boardcategorys in database. Please create at least one.</b>
        </td></tr>
    ';
}
?>

</table>

<?php include __DIR__ . '/footer.inc.php'; ?>
