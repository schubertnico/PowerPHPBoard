<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Admin Header
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

require_once __DIR__ . '/../config.inc.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Session.php';
require_once __DIR__ . '/../includes/Security.php';
require_once __DIR__ . '/../includes/CSRF.php';

// Start session
Session::start();

// Initialize variables
$settings = [];
$ppbuser = [];
$catid = Security::getInt('catid', 'GET', 0);
$threadid = Security::getInt('threadid', 'GET', 0);
$boardid = Security::getInt('boardid', 'GET', 0);
$postid = Security::getInt('postid', 'GET', 0);

// Initialize database connection
$db = Database::getInstance($mysql);

// Load board settings
$settingsRow = $db->fetchOne("SELECT * FROM ppb_config WHERE id = ? LIMIT 1", [1]);

if ($settingsRow !== null) {
    $settings['id'] = $settingsRow['id'];
    $settings['boardtitle'] = $settingsRow['boardtitle'];
    $settings['boardurl'] = $settingsRow['boardurl'];
    $settings['adminemail'] = $settingsRow['adminemail'];
    $settings['header'] = $settingsRow['header'];
    $settings['footer'] = $settingsRow['footer'];
    $settings['bordercolor'] = $settingsRow['bordercolor'];
    $settings['tablebg1'] = $settingsRow['tablebg1'];
    $settings['tablebg2'] = $settingsRow['tablebg2'];
    $settings['tablebg3'] = $settingsRow['tablebg3'];
    $settings['htmlcode'] = $settingsRow['htmlcode'];
    $settings['bbcode'] = $settingsRow['bbcode'];
    $settings['smilies'] = $settingsRow['smilies'];
    $settings['newthread'] = $settingsRow['newthread'];
    $settings['newpost'] = $settingsRow['newpost'];
}

// Check user authentication via session
$loggedin = 'NO';
$userId = Session::getUserId();

if ($userId !== null) {
    $userRow = $db->fetchOne(
        "SELECT * FROM ppb_users WHERE id = ?",
        [$userId]
    );

    if ($userRow !== null) {
        $loggedin = 'YES';
        $ppbuser['id'] = $userRow['id'];
        $ppbuser['username'] = $userRow['username'];
        $ppbuser['email'] = $userRow['email'];
        $ppbuser['password'] = $userRow['password'];
        $ppbuser['homepage'] = $userRow['homepage'];
        $ppbuser['icq'] = $userRow['icq'];
        $ppbuser['biography'] = $userRow['biography'];
        $ppbuser['signature'] = $userRow['signature'];
        $ppbuser['hideemail'] = $userRow['hideemail'];
        $ppbuser['logincookie'] = $userRow['logincookie'];
        $ppbuser['status'] = $userRow['status'];
        $ppbuser['registered'] = $userRow['registered'];
    }
}

// Admin table colors
$admin_tbl1 = '#F0F0F0';
$admin_tbl2 = '#E0E0E0';
$admin_tbl3 = '#9F9F9F';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title>PowerPHPBoard Admin Area</title>
<meta name="author" content="Stefan 'BFG' Kraemer">
<meta charset="UTF-8">
</head>
<link rel="stylesheet" href="ppb.css" type="text/css">
<body text="#000000" bgcolor="#FFFFFF" link="#000080" alink="#000080" vlink="#000080">

<center>
<table border="0" width="95%" cellpadding="0" cellspacing="0">
<tr><td width="50%" valign="top">
&#149; <a href="general.php">General administration</a><br>
&#149; <a href="boards.php">Board administration</a><br>
&#149; <a href="user.php">User administration</a><br>
<br>
</td><td width="50%" align="center">
  <small><a href="../index.php">Home</a> |
  <?php
    if ($loggedin === 'NO') {
        echo '<a href="../login.php">Login</a> | ';
    }
    echo '<a href="../profile.php">Profile</a> | ';
    if ($loggedin === 'NO') {
        echo '<a href="../register.php">Register</a> | ';
    }
    if ($loggedin === 'YES') {
        echo '<a href="../logout.php">Logout</a> | ';
    }
    echo '<a href="../faq.php">FAQ</a><br>';
    if ($loggedin === 'YES') {
        echo '<br>Logged in as <b>' . Security::escape($ppbuser['username'] ?? '') . '</b><br>';
    }
  ?>
  <br>
</small>
</td></tr>
<tr><td colspan="2">
  <table border="0" cellpadding="0" cellspacing="0" width="100%" bgcolor="#000080">
  <tr><td width="100%">
