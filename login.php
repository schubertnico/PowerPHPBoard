<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - User Login
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

use PowerPHPBoard\CSRF;
use PowerPHPBoard\Database;
use PowerPHPBoard\DatabaseRateLimitStorage;
use PowerPHPBoard\ErrorHandler;
use PowerPHPBoard\RateLimiter;
use PowerPHPBoard\Security;
use PowerPHPBoard\Session;

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

$catid = Security::getInt('catid');
$boardid = Security::getInt('boardid');
$login = Security::getInt('login', 'POST');
$loginerror = '';
$loginSuccess = false;

$rateLimiter = new RateLimiter(
    new DatabaseRateLimitStorage($db),
    maxAttempts: 10,
    windowSeconds: 900,
    lockSeconds: 900
);
$rateLimitIdentifier = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $login === 1) {
    if (!CSRF::validateFromPost()) {
        $loginerror = 'Security token invalid. Please try again.';
    } elseif (!$rateLimiter->check('login', $rateLimitIdentifier)) {
        $loginerror = $lang_toomanyattempts ?? 'Too many attempts. Please try again later.';
    } else {
        $email = Security::getString('email', 'POST');
        $password = Security::getString('password', 'POST');

        if ($email === '' || $email === '0') {
            $loginerror = $lang_insertemail ?? 'Please enter your email address';
        } elseif ($password === '' || $password === '0') {
            $loginerror = $lang_insertpwd ?? 'Please enter your password';
        } else {
            $user = $db->fetchOne('SELECT * FROM ppb_users WHERE email = ?', [$email]);

            if ($user === null) {
                $loginerror = $lang_loginfailed ?? 'Invalid email or password.';
                ErrorHandler::logFailedLogin($email, 'user_not_found');
                $rateLimiter->recordFailure('login', $rateLimitIdentifier);
            } else {
                if (Security::verifyPassword($password, $user['password'])) {
                    if ($user['logincookie'] === 'YES' || $user['logincookie'] === 'NO') {
                        if (Security::needsRehash($user['password'])) {
                            $newHash = Security::hashPassword($password);
                            $db->query('UPDATE ppb_users SET password = ? WHERE id = ?', [$newHash, $user['id']]);
                        }

                        Session::login((int) $user['id']);
                        ErrorHandler::logSuccessfulLogin((int) $user['id'], $email);
                        CSRF::regenerate();
                        $rateLimiter->recordSuccess('login', $rateLimitIdentifier);
                        $loginSuccess = true;
                    } else {
                        $loginerror = $lang_loginfailed ?? 'Invalid email or password.';
                        $rateLimiter->recordFailure('login', $rateLimitIdentifier);
                    }
                } else {
                    $loginerror = $lang_loginfailed ?? 'Invalid email or password.';
                    ErrorHandler::logFailedLogin($email, 'invalid_password');
                    $rateLimiter->recordFailure('login', $rateLimitIdentifier);
                }
            }
        }
    }
}

include __DIR__ . '/header.inc.php';
?>

<div class="row justify-content-center">
  <div class="col-md-8 col-lg-6">

  <?php if ($loginSuccess): ?>
    <div class="card shadow-sm border-success">
      <header class="card-header bg-success text-white">
        <h2 class="h6 mb-0">
          <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
          <?php echo $lang_statusmessage ?? 'Status'; ?>
        </h2>
      </header>
      <div class="card-body">
        <p class="mb-3"><?php echo $lang_loginok ?? 'Login successful!'; ?></p>
        <a href="index.php" class="btn btn-primary">
          <i class="bi bi-house-door" aria-hidden="true"></i> Home
        </a>
      </div>
    </div>
  <?php else: ?>
    <?php if ($loginerror !== ''): ?>
      <div class="alert alert-danger" role="alert">
        <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
        <?php echo Security::escape($loginerror); ?>
      </div>
    <?php endif; ?>

    <section class="card shadow-sm">
      <header class="card-header bg-secondary-subtle">
        <h1 class="h5 mb-0">
          <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>
          <?php echo $lang_login ?? 'Login'; ?>
        </h1>
      </header>
      <div class="card-body">
        <form action="login.php" method="post" class="needs-validation" novalidate>
          <?php echo CSRF::getTokenField(); ?>
          <input type="hidden" name="catid" value="<?php echo (int) $catid; ?>">
          <input type="hidden" name="boardid" value="<?php echo (int) $boardid; ?>">
          <input type="hidden" name="login" value="1">

          <div class="mb-3">
            <label for="email" class="form-label fw-semibold">
              <?php echo $lang_email ?? 'Email'; ?>
            </label>
            <input id="email" name="email" type="email" class="form-control"
                   maxlength="100" required autocomplete="email"
                   aria-describedby="emailHelp">
            <div id="emailHelp" class="form-text">
              Bitte gib die E-Mail-Adresse ein, mit der du registriert bist.
            </div>
            <div class="invalid-feedback">
              <?php echo $lang_insertemail ?? 'Please enter your email address'; ?>
            </div>
          </div>

          <div class="mb-3">
            <label for="password" class="form-label fw-semibold">
              <?php echo $lang_password ?? 'Password'; ?>
            </label>
            <input id="password" name="password" type="password" class="form-control"
                   maxlength="255" required autocomplete="current-password">
            <div class="invalid-feedback">
              <?php echo $lang_insertpwd ?? 'Please enter your password'; ?>
            </div>
          </div>

          <div class="d-flex flex-wrap gap-2 align-items-center">
            <button type="submit" class="btn btn-primary">
              <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>
              <?php echo $lang_send ?? 'Submit'; ?>
            </button>
            <a class="btn btn-link" href="register.php?catid=<?php echo (int) $catid; ?>&boardid=<?php echo (int) $boardid; ?>">
              <?php echo $lang_wanttoregister ?? 'Register'; ?>
            </a>
            <a class="btn btn-link" href="sendpassword.php?catid=<?php echo (int) $catid; ?>&boardid=<?php echo (int) $boardid; ?>">
              <?php echo $lang_pwdforgotten ?? 'Forgot password?'; ?>
            </a>
          </div>

          <p class="form-text mt-3 mb-0">
            <i class="bi bi-info-circle" aria-hidden="true"></i>
            <?php echo $lang_cookeisenabled ?? 'Cookies müssen aktiviert sein'; ?>
          </p>
        </form>
      </div>
    </section>
  <?php endif; ?>

  </div>
</div>

<?php include __DIR__ . '/footer.inc.php'; ?>
