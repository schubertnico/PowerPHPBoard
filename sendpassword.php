<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Send Password Reset Link
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

use PowerPHPBoard\CSRF;
use PowerPHPBoard\Database;
use PowerPHPBoard\DatabaseRateLimitStorage;
use PowerPHPBoard\Mailer;
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
require_once __DIR__ . '/functions.inc.php';

$catid = Security::getInt('catid');
$boardid = Security::getInt('boardid');
$send = Security::getInt('send', 'REQUEST');

$rateLimiter = new RateLimiter(
    new DatabaseRateLimitStorage($db),
    maxAttempts: 5,
    windowSeconds: 3600,
    lockSeconds: 3600
);
$rlIdent = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

$status = '';
$errorText = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $send === 1) {
    if (!CSRF::validateFromPost()) {
        $errorText = 'Security token invalid. Please try again.';
    } elseif (!$rateLimiter->check('pwreset', $rlIdent)) {
        $errorText = $lang_toomanyattempts ?? 'Too many attempts. Please try again later.';
    } else {
        $email = Security::getString('email', 'POST');
        $rateLimiter->recordFailure('pwreset', $rlIdent);

        $user = null;
        if ($email !== '' && Security::isValidEmail($email)) {
            $user = $db->fetchOne('SELECT * FROM ppb_users WHERE email = ?', [$email]);
        }

        if ($user !== null) {
            $rawToken = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $rawToken);
            $now = time();
            $expires = $now + 3600;

            $db->query(
                'UPDATE ppb_password_resets SET used_at = ? WHERE userid = ? AND used_at = 0',
                [$now, $user['id']]
            );
            $db->query(
                'INSERT INTO ppb_password_resets (userid, token_hash, expires_at, created_at) VALUES (?, ?, ?, ?)',
                [$user['id'], $tokenHash, $expires, $now]
            );

            $baseUrl = (string) ($settings['boardurl'] ?? '');
            if ($baseUrl === '') {
                $scheme = (($_SERVER['HTTPS'] ?? '') === 'on') ? 'https' : 'http';
                $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
                $baseUrl = $scheme . '://' . $host;
            }
            $resetUrl = rtrim($baseUrl, '/') . '/resetpassword.php?token=' . $rawToken;

            $subject = ($settings['boardtitle'] ?? 'PowerPHPBoard') . ' - ' . ($lang_passwordreminder ?? 'Password Reset');
            $message = ($lang_hello ?? 'Hello') . ' ' . $user['username'] . ",\n\n"
                . ($lang_pwdresetclicklink ?? 'Click this link within one hour to reset your password:') . "\n\n"
                . $resetUrl . "\n\n"
                . ($lang_ifyoudidntrequestmail ?? 'If you did not request this, you can ignore this email.') . "\n";

            $fromAddress = (string) ($settings['adminemail'] ?? '');
            if ($fromAddress === '' || !Security::isValidEmail($fromAddress)) {
                $fromAddress = (string) ($mail['from'] ?? 'noreply@powerphpboard.local');
            }
            $mailer = new Mailer(
                (string) ($mail['host'] ?? 'mailpit'),
                (int) ($mail['port'] ?? 1025)
            );
            $mailer->send($email, $fromAddress, $subject, $message);
        }

        CSRF::regenerate();
        // Einheitliche Antwort unabhängig von der Nutzer-Existenz (BUG-017)
        $status = $lang_pwdresetlinksent ?? 'If the email is registered, a reset link has been sent.';
    }
}

include __DIR__ . '/header.inc.php';
?>

<div class="row justify-content-center">
  <div class="col-md-8 col-lg-6">

  <?php if ($status !== ''): ?>
    <div class="card shadow-sm border-success">
      <header class="card-header bg-success text-white">
        <h2 class="h6 mb-0">
          <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
          <?php echo $lang_statusmessage ?? 'Status'; ?>
        </h2>
      </header>
      <div class="card-body">
        <p class="mb-3"><?php echo Security::escape($status); ?></p>
        <a href="index.php" class="btn btn-primary">
          <i class="bi bi-house-door" aria-hidden="true"></i> Home
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
          <i class="bi bi-envelope-paper" aria-hidden="true"></i>
          <?php echo $lang_sendpwd ?? 'Send Password'; ?>
        </h1>
      </header>
      <div class="card-body">
        <form action="sendpassword.php?send=1" method="post" class="needs-validation" novalidate>
          <?php echo CSRF::getTokenField(); ?>
          <input type="hidden" name="send" value="1">
          <div class="mb-3">
            <label for="email" class="form-label fw-semibold">
              <?php echo $lang_email ?? 'Email'; ?>
            </label>
            <input id="email" name="email" type="email" class="form-control"
                   maxlength="100" required autocomplete="email"
                   aria-describedby="emailHelp">
            <div id="emailHelp" class="form-text">
              Wir senden einen einmaligen Reset-Link an diese E-Mail-Adresse, falls sie registriert ist.
            </div>
            <div class="invalid-feedback">Bitte eine gültige E-Mail-Adresse eingeben.</div>
          </div>
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-send" aria-hidden="true"></i>
            <?php echo $lang_send ?? 'Send'; ?>
          </button>
          <a class="btn btn-link" href="login.php?catid=<?php echo (int) $catid; ?>&boardid=<?php echo (int) $boardid; ?>">
            <?php echo $lang_backtologin ?? 'Back to login'; ?>
          </a>
        </form>
      </div>
    </section>
  <?php endif; ?>

  </div>
</div>

<?php include __DIR__ . '/footer.inc.php'; ?>
