<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - User Logout
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

use PowerPHPBoard\CSRF;
use PowerPHPBoard\Security;
use PowerPHPBoard\Session;

require_once __DIR__ . '/config.inc.php';

Session::start();

$logout = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $logout = Security::getInt('logout', 'POST');
}

$catid = Security::getInt('catid');
$boardid = Security::getInt('boardid');

$csrfOk = false;
if ($logout === 1) {
    $csrfOk = CSRF::validateFromPost();
    if ($csrfOk) {
        Session::logout();
    }
}

include __DIR__ . '/header.inc.php';
?>

<div class="row justify-content-center">
  <div class="col-md-8 col-lg-6">

  <?php if ($logout === 1 && $csrfOk): ?>
    <div class="card shadow-sm border-success">
      <header class="card-header bg-success text-white">
        <h2 class="h6 mb-0">
          <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
          <?php echo $lang_statusmessage ?? 'Status'; ?>
        </h2>
      </header>
      <div class="card-body">
        <p class="mb-3"><?php echo $lang_logoutok ?? 'Logout successful!'; ?></p>
        <a href="index.php" class="btn btn-primary">
          <i class="bi bi-house-door" aria-hidden="true"></i> Home
        </a>
      </div>
    </div>
  <?php elseif ($logout === 1 && !$csrfOk): ?>
    <div class="alert alert-danger" role="alert">
      <strong><?php echo $lang_errormessage ?? 'Error'; ?>:</strong>
      Security token invalid. Please try again.
    </div>
    <a href="logout.php" class="btn btn-outline-secondary">
      <i class="bi bi-arrow-left" aria-hidden="true"></i>
      <?php echo $lang_back ?? 'Back'; ?>
    </a>
  <?php else: ?>
    <section class="card shadow-sm">
      <header class="card-header bg-secondary-subtle">
        <h1 class="h5 mb-0">
          <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
          <?php echo $lang_logout ?? 'Logout'; ?>
        </h1>
      </header>
      <div class="card-body">
        <p class="mb-3"><?php echo $lang_reallylogout ?? 'Do you really want to logout?'; ?></p>
        <div class="d-flex flex-wrap gap-2">
          <form action="logout.php" method="post" class="d-inline">
            <?php echo CSRF::getTokenField(); ?>
            <input type="hidden" name="logout" value="1">
            <input type="hidden" name="catid" value="<?php echo (int) $catid; ?>">
            <input type="hidden" name="boardid" value="<?php echo (int) $boardid; ?>">
            <button type="submit" class="btn btn-danger">
              <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
              <?php echo $lang_yeslogout ?? 'Yes, logout'; ?>
            </button>
          </form>
          <a href="index.php?catid=<?php echo (int) $catid; ?>&boardid=<?php echo (int) $boardid; ?>"
             class="btn btn-outline-secondary">
            <?php echo $lang_nologout ?? 'No, stay logged in'; ?>
          </a>
        </div>
      </div>
    </section>
  <?php endif; ?>

  </div>
</div>

<?php include __DIR__ . '/footer.inc.php'; ?>
