<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Password Reset Via Token
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

use PowerPHPBoard\CSRF;
use PowerPHPBoard\Database;
use PowerPHPBoard\Security;
use PowerPHPBoard\Session;
use PowerPHPBoard\Validator;

require_once __DIR__ . '/config.inc.php';
Session::start();

try {
    $db = Database::getInstance($mysql);
} catch (PDOException $e) {
    die('Database connection failed');
}

$settings = $db->fetchOne('SELECT * FROM ppb_config WHERE id = ?', [1]) ?? [];
$langFile = match ($settings['language'] ?? 'English') {
    'Deutsch-Sie' => 'deutsch-sie.inc.php',
    'Deutsch-Du' => 'deutsch-du.inc.php',
    default => 'english.inc.php',
};
require_once __DIR__ . '/' . $langFile;
require_once __DIR__ . '/functions.inc.php';

$token = Security::getString('token', 'REQUEST');
$now = time();

$tokenValid = false;
$reset = null;
if ($token !== '' && strlen($token) <= 128) {
    $tokenHash = hash('sha256', $token);
    $reset = $db->fetchOne(
        'SELECT r.id, r.userid, r.expires_at, r.used_at FROM ppb_password_resets r
         JOIN ppb_users u ON u.id = r.userid WHERE r.token_hash = ?',
        [$tokenHash]
    );
    if ($reset !== null && (int) $reset['used_at'] === 0 && (int) $reset['expires_at'] >= $now) {
        $tokenValid = true;
    }
}

$errorText = '';
$done = false;

if ($tokenValid && $reset !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateFromPost()) {
        $errorText = 'Security token invalid. Please try again.';
    } else {
        $p1 = Security::getString('password1', 'POST');
        $p2 = Security::getString('password2', 'POST');
        if ($p1 !== $p2) {
            $errorText = $lang_pwdsdifferent ?? 'Passwords do not match';
        } elseif (!Validator::isStrongPassword($p1)) {
            $errorText = $lang_pwdtooshort ?? 'Password must be at least 8 characters';
        } else {
            $hash = Security::hashPassword($p1);
            $db->query('UPDATE ppb_users SET password = ? WHERE id = ?', [$hash, (int) $reset['userid']]);
            $db->query('UPDATE ppb_password_resets SET used_at = ? WHERE id = ?', [$now, (int) $reset['id']]);
            $done = true;
            CSRF::regenerate();
        }
    }
}

include __DIR__ . '/header.inc.php';
?>

<div class="row justify-content-center">
  <div class="col-md-8 col-lg-6">

  <?php if (!$tokenValid): ?>
    <?php
    default_error(
        $lang_pwdresettokeninvalid ?? 'Invalid or expired reset link.',
        'index.php',
        'Home'
    );
    ?>
  <?php elseif ($done): ?>
    <div class="card shadow-sm border-success">
      <header class="card-header bg-success text-white">
        <h2 class="h6 mb-0">
          <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
          <?php echo $lang_statusmessage ?? 'Status'; ?>
        </h2>
      </header>
      <div class="card-body">
        <p class="mb-3">
          <?php echo $lang_pwdresetsuccess ?? 'Password has been reset. You can now log in.'; ?>
        </p>
        <a href="login.php" class="btn btn-primary">
          <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>
          <?php echo $lang_login ?? 'Login'; ?>
        </a>
      </div>
    </div>
  <?php else: ?>
    <?php if ($errorText !== ''): ?>
      <div class="alert alert-danger" role="alert">
        <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
        <?php echo Security::escape($errorText); ?>
      </div>
    <?php endif; ?>
    <section class="card shadow-sm">
      <header class="card-header bg-secondary-subtle">
        <h1 class="h5 mb-0">
          <i class="bi bi-key" aria-hidden="true"></i>
          <?php echo $lang_newpassword ?? 'New Password'; ?>
        </h1>
      </header>
      <div class="card-body">
        <form action="resetpassword.php?token=<?php echo Security::escape($token); ?>"
              method="post" class="needs-validation" novalidate>
          <?php echo CSRF::getTokenField(); ?>
          <div class="mb-3">
            <label for="password1" class="form-label fw-semibold">
              <?php echo $lang_newpassword ?? 'New password'; ?>
            </label>
            <input id="password1" name="password1" type="password" class="form-control"
                   minlength="8" required autocomplete="new-password" aria-describedby="pwd1Help">
            <div id="pwd1Help" class="form-text">Mindestens 8 Zeichen.</div>
            <div class="invalid-feedback">Mindestens 8 Zeichen erforderlich.</div>
          </div>
          <div class="mb-3">
            <label for="password2" class="form-label fw-semibold">
              <?php echo $lang_confirmation ?? 'Confirmation'; ?>
            </label>
            <input id="password2" name="password2" type="password" class="form-control"
                   minlength="8" required autocomplete="new-password">
            <div class="invalid-feedback">Bitte zur Bestätigung wiederholen.</div>
          </div>
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-shield-check" aria-hidden="true"></i>
            <?php echo $lang_send ?? 'Send'; ?>
          </button>
        </form>
      </div>
    </section>
  <?php endif; ?>

  </div>
</div>

<?php include __DIR__ . '/footer.inc.php'; ?>
