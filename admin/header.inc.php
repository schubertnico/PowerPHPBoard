<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Admin Header
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

use PowerPHPBoard\Auth;
use PowerPHPBoard\CSRF;
use PowerPHPBoard\Database;
use PowerPHPBoard\Security;
use PowerPHPBoard\Session;

require_once __DIR__ . '/../config.inc.php';
require_once __DIR__ . '/../includes/autoload.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Session.php';
require_once __DIR__ . '/../includes/Security.php';
require_once __DIR__ . '/../includes/CSRF.php';

Session::start();

$settings = [];
$ppbuser = [];
$catid = Security::getInt('catid', 'GET', 0);
$threadid = Security::getInt('threadid', 'GET', 0);
$boardid = Security::getInt('boardid', 'GET', 0);
$postid = Security::getInt('postid', 'GET', 0);

$db = Database::getInstance($mysql);

$settingsRow = $db->fetchOne('SELECT * FROM ppb_config WHERE id = ? LIMIT 1', [1]);
if ($settingsRow !== null) {
    $settings = $settingsRow;
}

// Sprachdatei und Hilfsfunktionen wie im Frontend
$langFile = match ($settings['language'] ?? 'English') {
    'Deutsch-Sie' => 'deutsch-sie.inc.php',
    'Deutsch-Du' => 'deutsch-du.inc.php',
    default => 'english.inc.php',
};
require_once __DIR__ . '/../' . $langFile;
require_once __DIR__ . '/../functions.inc.php';

// Angemeldeter Benutzer (deaktivierte Konten gelten als abgemeldet)
$ppbuser = Auth::currentUser($db) ?? [];
$loggedin = $ppbuser !== [] ? 'YES' : 'NO';

// Admin guard: nur Administratoren dürfen den Adminbereich sehen
$isAdmin = Auth::isAdmin($ppbuser !== [] ? $ppbuser : null);

// Ein ungültiges CSRF-Token wird hier freundlich gemeldet, bevor eine Seite
// etwas ändert (die Seiten prüfen zusätzlich selbst mit validateOrDie()).
$csrfFailed = $isAdmin && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !CSRF::validateFromPost();

$adminTitle = $lang_adm_title ?? 'Administration';
?>
<!DOCTYPE html>
<html lang="<?php echo Security::escape($lang_htmllang ?? 'en'); ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo Security::escape($adminTitle . ' – ' . ($settings['boardtitle'] ?? 'PowerPHPBoard')); ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="../ppb.css">
</head>
<body class="bg-body-tertiary d-flex flex-column min-vh-100">
<nav class="navbar navbar-expand-lg navbar-dark bg-danger" aria-label="<?php echo Security::escape($lang_adm_nav ?? 'Administration navigation'); ?>">
  <div class="container-xl">
    <a class="navbar-brand fw-semibold" href="index.php">
      <i class="bi bi-shield-lock-fill" aria-hidden="true"></i>
      <?php echo Security::escape($adminTitle); ?>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
            data-bs-target="#adminNav" aria-controls="adminNav" aria-expanded="false"
            aria-label="<?php echo Security::escape($lang_togglenav ?? 'Toggle navigation'); ?>">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="adminNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link" href="index.php"><i class="bi bi-grid" aria-hidden="true"></i> <?php echo Security::escape($lang_adm_overview ?? 'Overview'); ?></a></li>
        <li class="nav-item"><a class="nav-link" href="general.php"><i class="bi bi-sliders" aria-hidden="true"></i> <?php echo Security::escape($lang_adm_general ?? 'General'); ?></a></li>
        <li class="nav-item"><a class="nav-link" href="boards.php"><i class="bi bi-folder2-open" aria-hidden="true"></i> <?php echo Security::escape($lang_adm_boards ?? 'Boards'); ?></a></li>
        <li class="nav-item"><a class="nav-link" href="user.php"><i class="bi bi-people" aria-hidden="true"></i> <?php echo Security::escape($lang_adm_users ?? 'Users'); ?></a></li>
      </ul>
      <ul class="navbar-nav align-items-lg-center">
        <li class="nav-item"><a class="nav-link" href="../index.php"><i class="bi bi-house-door" aria-hidden="true"></i> <?php echo Security::escape($lang_adm_forum ?? 'Forum'); ?></a></li>
        <?php if ($loggedin === 'YES'): ?>
          <li class="nav-item nav-link mb-0">
            <i class="bi bi-person-check" aria-hidden="true"></i>
            <strong><?php echo Security::escape((string) ($ppbuser['username'] ?? '')); ?></strong>
          </li>
          <li class="nav-item"><a class="nav-link" href="../logout.php"><i class="bi bi-box-arrow-right" aria-hidden="true"></i> <?php echo Security::escape($lang_logout ?? 'Logout'); ?></a></li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link" href="../login.php"><i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> <?php echo Security::escape($lang_login ?? 'Login'); ?></a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<main class="container-xl py-4 flex-grow-1" role="main">

<?php if (!$isAdmin || $csrfFailed): ?>
  <div class="alert alert-danger d-flex align-items-center gap-2" role="alert">
    <i class="bi bi-shield-exclamation fs-4" aria-hidden="true"></i>
    <div>
      <?php if (!$isAdmin): ?>
        <strong><?php echo Security::escape($lang_adm_noaccess ?? 'No access.'); ?></strong>
        <?php echo Security::escape($lang_adm_noaccesstext ?? 'This area is only available to administrators.'); ?>
        <a class="alert-link" href="../login.php"><?php echo Security::escape($lang_login ?? 'Login'); ?></a>
      <?php else: ?>
        <?php echo Security::escape($lang_csrfinvalid ?? 'The security token is invalid. Please reload the page and try again.'); ?>
        <a class="alert-link" href="index.php"><?php echo Security::escape($lang_adm_backtooverview ?? 'Back to the overview'); ?></a>
      <?php endif; ?>
    </div>
  </div>
  </main>
  <footer class="bg-dark text-light py-3 mt-auto">
    <div class="container-xl text-center">
      <small>
        PowerPHPBoard &copy; 2001-2026
        <a class="link-light" href="https://www.powerscripts.org" target="_blank" rel="noopener">PowerScripts</a>
      </small>
    </div>
  </footer>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
  </body></html>
<?php
    exit;
endif;
?>
