<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Main Header Include
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
use PowerPHPBoard\Session;
use PowerPHPBoard\Security;
use PowerPHPBoard\CSRF;

// Load configuration and core classes
require_once __DIR__ . '/config.inc.php';

// Start secure session
Session::start();

// Initialize variables
$settings = [];
$ppbuser = [];
$bcat = [];
$board = [];
$thread = [];
$loggedin = 'NO';

// Get URL parameters safely
$catid = Security::getInt('catid');
$threadid = Security::getInt('threadid');
$boardid = Security::getInt('boardid');
$postid = Security::getInt('postid');
$current = Security::getInt('current');

// Connect to database
try {
    $db = Database::getInstance($mysql);
} catch (PDOException $e) {
    echo '<center><b>Couldn\'t connect to database server!</b></center>';
    exit;
}

// Load global settings
$settings = $db->fetchOne("SELECT * FROM ppb_config WHERE id = ?", [1]);
if ($settings === null) {
    $settings = [];
}

// Load language file
$langFile = match ($settings['language'] ?? 'English') {
    'Deutsch-Sie' => 'deutsch-sie.inc.php',
    'Deutsch-Du' => 'deutsch-du.inc.php',
    default => 'english.inc.php',
};
require_once __DIR__ . '/' . $langFile;

// Load category settings if specified
if ($catid > 0) {
    $catSettings = $db->fetchOne(
        "SELECT header, footer, bordercolor, tablebg1, tablebg2, tablebg3, newthread, newpost
         FROM ppb_boards WHERE id = ? AND type = 'Boardcategory'",
        [$catid]
    );
    if ($catSettings !== null) {
        $settings = array_merge($settings, $catSettings);
    }
}

// Load thread if specified
if ($threadid > 0) {
    $thread = $db->fetchOne(
        "SELECT * FROM ppb_posts WHERE id = ? AND type = 'Thread'",
        [$threadid]
    );
    if ($thread !== null) {
        $boardid = (int)$thread['boardid'];
    } else {
        $thread = [];
    }
}

// Load board if specified
if ($boardid > 0) {
    $board = $db->fetchOne(
        "SELECT * FROM ppb_boards WHERE id = ? AND type = 'Board'",
        [$boardid]
    );
    if ($board !== null) {
        $catid = (int)$board['catid'];
        // Override design settings from board
        foreach (['header', 'footer', 'bordercolor', 'tablebg1', 'tablebg2', 'tablebg3', 'newthread', 'newpost'] as $key) {
            if (!empty($board[$key])) {
                $settings[$key] = $board[$key];
            }
        }
    } else {
        $board = [];
    }
}

// Load board category title
if ($catid > 0) {
    $bcat = $db->fetchOne(
        "SELECT id, title FROM ppb_boards WHERE id = ? AND type = 'Boardcategory'",
        [$catid]
    );
    if ($bcat === null) {
        $bcat = [];
    }
}

// Check user authentication via session
if (Session::isLoggedIn()) {
    $userId = Session::getUserId();
    $ppbuser = $db->fetchOne("SELECT * FROM ppb_users WHERE id = ?", [$userId]);
    if ($ppbuser !== null) {
        $loggedin = 'YES';
    } else {
        $ppbuser = [];
        Session::logout();
    }
}

// Include functions
require_once __DIR__ . '/functions.inc.php';

// Include header template
$headerFile = $settings['header'] ?? '';
if ($headerFile !== '' && file_exists(__DIR__ . '/inc/' . $headerFile)) {
    include __DIR__ . '/inc/' . $headerFile;
} else {
    include __DIR__ . '/inc/header.ppb';
}
?>

<center>
<table border="0" width="95%" cellpadding="0" cellspacing="0">

<tr><td width="50%">
  <table border="0" cellpadding="0" cellspacing="0" width="100%">
  <tr><td width="20" height="15" valign="middle">
  <img src="images/dir1.gif" width="17" height="15" border="0" alt="">
  </td><td width="*" valign="middle">
  <a href="index.php"><?php echo Security::escape($settings['boardtitle'] ?? 'PowerPHPBoard'); ?></a>
  </td></tr>
  </table>
<?php
if (!empty($bcat['title'])) {
    echo '
      <table border="0" cellpadding="0" cellspacing="0" width="100%">
      <tr><td width="37" height="15" valign="middle">
      <img src="images/dir2.gif" width="34" height="15" border="0" alt="">
      </td><td width="*" valign="middle">
      <a href="index.php?catid=' . $catid . '">' . Security::escape($bcat['title']) . '</a>
      </td></tr>
      </table>
    ';
}
if (!empty($board['title'])) {
    echo '
      <table border="0" cellpadding="0" cellspacing="0" width="100%">
      <tr><td width="54" height="15" valign="middle">
      <img src="images/dir3.gif" width="51" height="15" border="0" alt="">
      </td><td width="*" valign="middle">
      <a href="showboard.php?boardid=' . (int)$board['id'] . '">' . Security::escape($board['title']) . '</a>
      </td></tr>
      </table>
    ';
}
if (!empty($thread['title'])) {
    echo '
      <table border="0" cellpadding="0" cellspacing="0" width="100%">
      <tr><td width="70" height="15" valign="middle">
      <img src="images/dir4.gif" width="67" height="15" border="0" alt="">
      </td><td width="*" valign="middle">
      <a href="showthread.php?threadid=' . (int)$thread['id'] . '">' . Security::escape($thread['title']) . '</a>
      </td></tr>
      </table>
    ';
}
?>
<br>
<?php
if (!empty($board['title'])) {
    echo '<b class="big">' . Security::escape($board['title']) . '</b><br>
      <small>( ' . ($lang_moderatedby ?? 'Moderated by');

    if (!empty($board['mods'])) {
        // Use explode() instead of deprecated split()
        $mods = explode(',', (string) $board['mods']);
        $first = true;
        foreach ($mods as $modEmail) {
            $modEmail = trim($modEmail);
            if ($modEmail === '') {
                continue;
            }
            $mod = $db->fetchOne("SELECT id, username FROM ppb_users WHERE email = ?", [$modEmail]);
            if ($mod !== null) {
                if (!$first) {
                    echo ', ';
                }
                echo '<a href="showprofile.php?userid=' . (int)$mod['id'] . '&catid=' . $catid . '&boardid=' . $boardid . '">' . Security::escape($mod['username']) . '</a>';
                $first = false;
            }
        }
    }
    echo ')</small>';
}
?>

</td><td width="50%" align="center">
<?php
if (!empty($board['title'])) {
    if (($board['status'] ?? '') !== 'Closed') {
        echo '<a href="newthread.php?boardid=' . (int)$board['id'] . '"><img src="' . Security::escape($settings['newthread'] ?? 'images/newthread.gif') . '" border="0" width="120" height="20" alt="New Thread"></a>';
        if (!empty($thread['title'])) {
            if (($thread['status'] ?? '') !== 'Closed') {
                echo '&nbsp;&nbsp;<a href="newpost.php?threadid=' . (int)$thread['id'] . '&current=' . $current . '"><img src="' . Security::escape($settings['newpost'] ?? 'images/newpost.gif') . '" border="0" width="120" height="20" alt="New Post"></a>';
            } else {
                echo '- [ ' . ($lang_threadclosed ?? 'Thread closed') . ' ] -';
            }
        }
    } else {
        echo '- [ ' . ($lang_boardclosed ?? 'Board closed') . ' ] -';
    }
    echo '<br>';
}
?>
<br>
<small><a href="index.php">Home</a> |
<?php
if ($loggedin === 'NO') {
    echo '<a href="login.php?catid=' . $catid . '&boardid=' . $boardid . '">' . ($lang_login ?? 'Login') . '</a> | ';
}
echo '<a href="profile.php?catid=' . $catid . '&boardid=' . $boardid . '">' . ($lang_profile ?? 'Profile') . '</a> | ';
if ($loggedin === 'NO') {
    echo '<a href="register.php?catid=' . $catid . '&boardid=' . $boardid . '">' . ($lang_register ?? 'Register') . '</a> | ';
}
if ($loggedin === 'YES') {
    echo '<a href="logout.php?catid=' . $catid . '&boardid=' . $boardid . '">' . ($lang_logout ?? 'Logout') . '</a> | ';
}
echo '<a href="statistics.php?catid=' . $catid . '&boardid=' . $boardid . '">' . ($lang_statistics ?? 'Statistics') . '</a><br>';
if ($loggedin === 'YES') {
    echo '<br>' . ($lang_loggedinas ?? 'Logged in as') . ' <b>' . Security::escape($ppbuser['username'] ?? '') . '</b><br>';
}
?>
</small>
</td></tr>
<tr><td colspan="2" align="center">
<br>
<table border="0" cellpadding="0" cellspacing="0" width="100%" bgcolor="<?php echo Security::escape($settings['bordercolor'] ?? '#000000'); ?>">
<tr><td width="100%">
