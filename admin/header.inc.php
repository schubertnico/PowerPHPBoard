<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Admin Header
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

use PowerPHPBoard\Database;
use PowerPHPBoard\Security;
use PowerPHPBoard\Session;

require_once __DIR__ . '/../config.inc.php';
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

$loggedin = 'NO';
$userId = Session::getUserId();

if ($userId !== null) {
    $userRow = $db->fetchOne('SELECT * FROM ppb_users WHERE id = ?', [$userId]);
    if ($userRow !== null) {
        $loggedin = 'YES';
        $ppbuser = $userRow;
    }
}

// Admin guard: nur Administratoren duerfen den Adminbereich sehen
$isAdmin = ($loggedin === 'YES' && ($ppbuser['status'] ?? '') === 'Administrator');
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Adminbereich &middot; PowerPHPBoard</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="../ppb.css">
</head>
<body class="bg-body-tertiary d-flex flex-column min-vh-100">
<nav class="navbar navbar-expand-lg navbar-dark bg-danger" aria-label="Adminbereich-Navigation">
  <div class="container-xl">
    <a class="navbar-brand fw-semibold" href="index.php">
      <i class="bi bi-shield-lock-fill" aria-hidden="true"></i>
      Adminbereich
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
            data-bs-target="#adminNav" aria-controls="adminNav" aria-expanded="false"
            aria-label="Navigation umschalten">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="adminNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link" href="index.php"><i class="bi bi-grid" aria-hidden="true"></i> Übersicht</a></li>
        <li class="nav-item"><a class="nav-link" href="general.php"><i class="bi bi-sliders" aria-hidden="true"></i> Allgemein</a></li>
        <li class="nav-item"><a class="nav-link" href="boards.php"><i class="bi bi-folder2-open" aria-hidden="true"></i> Boards</a></li>
        <li class="nav-item"><a class="nav-link" href="user.php"><i class="bi bi-people" aria-hidden="true"></i> Nutzer</a></li>
      </ul>
      <ul class="navbar-nav align-items-lg-center">
        <li class="nav-item"><a class="nav-link" href="../index.php"><i class="bi bi-house-door" aria-hidden="true"></i> Forum</a></li>
        <?php if ($loggedin === 'YES'): ?>
          <li class="nav-item nav-link mb-0">
            <i class="bi bi-person-check" aria-hidden="true"></i>
            <strong><?php echo Security::escape((string) ($ppbuser['username'] ?? '')); ?></strong>
          </li>
          <li class="nav-item"><a class="nav-link" href="../logout.php"><i class="bi bi-box-arrow-right" aria-hidden="true"></i> Logout</a></li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link" href="../login.php"><i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> Login</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<main class="container-xl py-4 flex-grow-1" role="main">

<?php if (!$isAdmin): ?>
  <div class="alert alert-danger d-flex align-items-center gap-2" role="alert">
    <i class="bi bi-shield-exclamation fs-4" aria-hidden="true"></i>
    <div>
      <strong>Kein Zugriff.</strong>
      Diese Seite ist nur für Administratoren erreichbar. Bitte
      <a class="alert-link" href="../login.php">einloggen</a>.
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
