<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - User Logout
 *
 * MIT License
 *
 * Copyright (c) 2024 PowerScripts
 */

use PowerPHPBoard\Security;
use PowerPHPBoard\Session;

// Load configuration first (includes autoloader)
require_once __DIR__ . '/config.inc.php';

// Get logout parameter
$logout = Security::getInt('logout');

// Process logout before header if requested
if ($logout === 1) {
    Session::start();
    Session::logout();
}

include __DIR__ . '/header.inc.php';
?>

<table border="0" cellpadding="2" cellspacing="1" width="100%">

<?php
if ($logout === 1) {
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
} else {
    echo '
      <tr><td bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '">
      <b>' . ($lang_logout ?? 'Logout') . '</b>
      </td></tr>
      <tr><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '"><br>
      ' . ($lang_reallylogout ?? 'Do you really want to logout?') . '<br><br>
      </td></tr>
      <tr><td bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '" align="center">
      <a href="logout.php?logout=1&catid=' . (int) $catid . '&boardid=' . (int) $boardid . '">' . ($lang_yeslogout ?? 'Yes, logout') . '</a> |
      <a href="index.php?catid=' . (int) $catid . '&boardid=' . (int) $boardid . '">' . ($lang_nologout ?? 'No, stay logged in') . '</a>
      </td></tr>
    ';
}
?>

</table>

<?php include __DIR__ . '/footer.inc.php'; ?>
