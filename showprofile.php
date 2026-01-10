<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Show User Profile
 *
 * MIT License
 *
 * Copyright (c) 2024 PowerScripts
 */

use PowerPHPBoard\Security;

include __DIR__ . '/header.inc.php';
?>

<table border="0" cellpadding="2" cellspacing="1" width="100%">

<?php
$userid = Security::getInt('userid');

if ($userid === 0) {
    echo '
    <tr><td bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '">
    <b>' . ($lang_errormessage ?? 'Error') . '</b>
    </td></tr>
    <tr><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '"><br>
    ' . ($lang_chooseuser ?? 'Please choose a user') . '<br><br>
    </td></tr>
    <tr><td bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '" align="center">
    <a href="index.php">' . ($lang_boardlist ?? 'Board list') . '</a>
    </td></tr>
    ';
} else {
    $user = $db->fetchOne("SELECT * FROM ppb_users WHERE id = ?", [$userid]);

    if ($user === null) {
        echo '
        <tr><td bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '">
        <b>' . ($lang_errormessage ?? 'Error') . '</b>
        </td></tr>
        <tr><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '"><br>
        ' . ($lang_nouserwithid ?? 'No user with this ID') . '<br><br>
        </td></tr>
        <tr><td bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '" align="center">
        <a href="index.php">' . ($lang_boardlist ?? 'Board list') . '</a>
        </td></tr>
        ';
    } else {
        echo '
        <tr><td colspan="2" bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '">
        <b>' . ($lang_showuserprof ?? 'User Profile') . '</b>
        </td></tr>
        <tr><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '" width="150">
        <b>' . ($lang_username ?? 'Username') . '</b>
        </td><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '">
        ' . Security::escape($user['username']) . '
        </td></tr>
        <tr><td bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '">
        <b>' . ($lang_email ?? 'Email') . '</b>
        </td><td bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '">
        ';

        if ($user['hideemail'] === 'NO') {
            echo '<a href="mailto:' . Security::escape($user['email']) . '">' . Security::escape($user['email']) . '</a>';
        } else {
            echo '<a href="sendmail.php?userid=' . (int)$user['id'] . '&catid=' . (int)$catid . '&boardid=' . (int)$boardid . '">' . ($lang_sendmail ?? 'Send mail') . '</a>';
        }

        echo '
        </td></tr>
        <tr><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '">
        <b>' . ($lang_icq ?? 'ICQ') . '</b>
        </td><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '">
        ';

        if (!empty($user['icq'])) {
            echo '<a href="mailto:' . Security::escape($user['icq']) . '@pager.icq.com">' . Security::escape($user['icq']) . '</a>';
        } else {
            echo 'N/A';
        }

        echo '
        </td></tr>
        <tr><td bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '">
        <b>' . ($lang_homepage ?? 'Homepage') . '</b>
        </td><td bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '">
        ';

        if ($user['homepage'] === 'http://' || empty($user['homepage'])) {
            echo 'N/A';
        } else {
            echo '<a href="' . Security::escape($user['homepage']) . '" target="_new">' . Security::escape($user['homepage']) . '</a>';
        }

        echo '
        </td></tr>
        <tr><td bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '">
        <b>' . ($lang_biography ?? 'Biography') . '</b>
        </td><td bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '">
        ';

        if (!empty($user['biography'])) {
            $biography = nl2br(Security::escape($user['biography']));
            echo $biography;
        } else {
            echo 'N/A';
        }

        echo '
        </td></tr>
        <tr><td bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '">
        <b>' . ($lang_rank ?? 'Rank') . '</b>
        </td><td bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '">
        ';

        if ($user['status'] === 'Deactivated' || $user['status'] === 'Administrator') {
            echo Security::escape($user['status']);
        } else {
            $rank = getrank((int)$user['id'], $db);
            echo Security::escape($rank);
        }

        echo '
        </td></tr>
        <tr><td colspan="2" align="center" bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '">
        <a href="javascript:history.back()">' . ($lang_back ?? 'Back') . '</a>
        </td></tr>
        ';
    }
}
?>

</table>

<?php include __DIR__ . '/footer.inc.php'; ?>
