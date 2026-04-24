<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - User Logout
 *
 * MIT License
 *
 * Copyright (c) 2024 PowerScripts
 */

use PowerPHPBoard\CSRF;
use PowerPHPBoard\Security;
use PowerPHPBoard\Session;

// Load configuration first (includes autoloader)
require_once __DIR__ . '/config.inc.php';

Session::start();

$logout = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $logout = Security::getInt('logout', 'POST');
}

$catid = Security::getInt('catid');
$boardid = Security::getInt('boardid');

// BUG-008: Perform logout only on POST with valid CSRF token
$csrfOk = false;
if ($logout === 1) {
    $csrfOk = CSRF::validateFromPost();
    if ($csrfOk) {
        Session::logout();
    }
}

include __DIR__ . '/header.inc.php';
?>

<table border="0" cellpadding="2" cellspacing="1" width="100%">

<?php
if ($logout === 1 && $csrfOk) {
    echo '
      <tr><td bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '">
      <b>' . ($lang_statusmessage ?? 'Status') . '</b>
      </td></tr>
      <tr><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '"><br>
      ' . ($lang_logoutok ?? 'Logout successful!') . '<br><br>
      </td></tr>
      <tr><td bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '" align="center">
      <a href="index.php">Home</a>
      </td></tr>
    ';
} elseif ($logout === 1 && !$csrfOk) {
    echo '
      <tr><td bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '">
      <b>' . ($lang_errormessage ?? 'Error') . '</b>
      </td></tr>
      <tr><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '"><br>
      Security token invalid. Please try again.<br><br>
      </td></tr>
      <tr><td bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '" align="center">
      <a href="logout.php">' . ($lang_back ?? 'Back') . '</a>
      </td></tr>
    ';
} else {
    // Show confirmation form (POST-only logout)
    echo '
      <tr><td bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '">
      <b>' . ($lang_logout ?? 'Logout') . '</b>
      </td></tr>
      <tr><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '"><br>
      ' . ($lang_reallylogout ?? 'Do you really want to logout?') . '<br><br>
      </td></tr>
      <tr><td bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '" align="center">
      <form action="logout.php" method="post" style="display:inline">
      ' . CSRF::getTokenField() . '
      <input type="hidden" name="logout" value="1">
      <input type="hidden" name="catid" value="' . (int) $catid . '">
      <input type="hidden" name="boardid" value="' . (int) $boardid . '">
      <button type="submit">' . ($lang_yeslogout ?? 'Yes, logout') . '</button>
      </form>
      &nbsp;|&nbsp;
      <a href="index.php?catid=' . (int) $catid . '&boardid=' . (int) $boardid . '">' . ($lang_nologout ?? 'No, stay logged in') . '</a>
      </td></tr>
    ';
}
?>

</table>

<?php include __DIR__ . '/footer.inc.php'; ?>
