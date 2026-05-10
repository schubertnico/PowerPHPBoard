<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Send Mail to User
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

use PowerPHPBoard\CSRF;
use PowerPHPBoard\Database;
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

$ppbuser = [];
$loggedin = 'NO';
$userId = Session::getUserId();
if ($userId !== null) {
    $userRow = $db->fetchOne('SELECT * FROM ppb_users WHERE id = ?', [$userId]);
    if ($userRow !== null) {
        $loggedin = 'YES';
        $ppbuser = $userRow;
    }
}

$catid = Security::getInt('catid');
$boardid = Security::getInt('boardid');
$userid = Security::getInt('userid');
$sendmail = Security::getString('sendmail');

$state = 'form';
$errorText = '';
$recipient = null;

if ($userid === 0) {
    $state = 'select';
} elseif ($loggedin !== 'YES') {
    $state = 'login';
} else {
    $recipient = $db->fetchOne('SELECT * FROM ppb_users WHERE id = ?', [$userid]);
    if ($recipient === null) {
        $state = 'nouser';
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $sendmail === 'YES') {
        if (!CSRF::validateFromPost()) {
            $errorText = 'Security token invalid. Please try again.';
        } else {
            $title = Security::getString('title', 'POST');
            $emailcontent = Security::getString('emailcontent', 'POST');

            if ($title === '' || $emailcontent === '') {
                $errorText = $lang_insertvaluesforall ?? 'Please fill in all fields';
            } else {
                $message = $emailcontent . "\n\n\n" .
                    ($lang_thisemailwassentthrough ?? 'This email was sent through') . ' ' .
                    ($settings['boardurl'] ?? '') . "\n" .
                    'PowerPHPBoard (C) 2001-2026 PowerScripts';
                $headers = 'From: ' . $ppbuser['username'] . ' <' . $ppbuser['email'] . '>';
                mail((string) $recipient['email'], $title, $message, $headers);
                CSRF::regenerate();
                $state = 'sent';
            }
        }
    }
}

include __DIR__ . '/header.inc.php';
?>

<div class="row justify-content-center">
  <div class="col-lg-9">

  <?php if ($state === 'select'): ?>
    <?php default_error($lang_chooseuser ?? 'Please choose a user', 'index.php', $lang_boardlist ?? 'Board list'); ?>
  <?php elseif ($state === 'nouser'): ?>
    <?php default_error($lang_chooseexistinguser ?? 'User does not exist', 'index.php', $lang_boardlist ?? 'Board list'); ?>
  <?php elseif ($state === 'login'): ?>
    <?php default_error($lang_loginfirst ?? 'Please log in first', 'login.php', $lang_login ?? 'Login'); ?>
  <?php elseif ($state === 'sent' && $recipient !== null): ?>
    <div class="card shadow-sm border-success">
      <header class="card-header bg-success text-white">
        <h2 class="h6 mb-0">
          <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
          <?php echo $lang_statusmessage ?? 'Status'; ?>
        </h2>
      </header>
      <div class="card-body">
        <p class="mb-3">
          <?php echo $lang_emailsentsuccessfull ?? 'Email sent successfully'; ?>
        </p>
        <a href="showprofile.php?userid=<?php echo (int) $recipient['id']; ?>" class="btn btn-primary">
          <i class="bi bi-person" aria-hidden="true"></i>
          <?php echo Security::escape((string) $recipient['username']); ?>'s
          <?php echo $lang_profile ?? 'Profile'; ?>
        </a>
      </div>
    </div>
  <?php elseif ($recipient !== null): ?>
    <?php if ($errorText !== ''): ?>
      <div class="alert alert-danger" role="alert">
        <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
        <?php echo Security::escape($errorText); ?>
      </div>
    <?php endif; ?>
    <form action="sendmail.php?sendmail=YES&userid=<?php echo (int) $userid; ?>&catid=<?php echo (int) $catid; ?>&boardid=<?php echo (int) $boardid; ?>"
          method="post" class="needs-validation" novalidate>
      <?php echo CSRF::getTokenField(); ?>
      <section class="card shadow-sm">
        <header class="card-header bg-secondary-subtle">
          <h1 class="h5 mb-0">
            <i class="bi bi-envelope" aria-hidden="true"></i>
            <?php echo $lang_sendmail ?? 'Send Email'; ?>
          </h1>
        </header>
        <div class="card-body">
          <dl class="row mb-3">
            <dt class="col-sm-3"><?php echo $lang_from ?? 'From'; ?></dt>
            <dd class="col-sm-9">
              <a class="text-decoration-none" href="showprofile.php?userid=<?php echo (int) $ppbuser['id']; ?>">
                <?php echo Security::escape((string) $ppbuser['username']); ?>
              </a>
            </dd>
            <dt class="col-sm-3"><?php echo $lang_to ?? 'To'; ?></dt>
            <dd class="col-sm-9">
              <a class="text-decoration-none" href="showprofile.php?userid=<?php echo (int) $recipient['id']; ?>">
                <?php echo Security::escape((string) $recipient['username']); ?>
              </a>
            </dd>
          </dl>
          <div class="mb-3">
            <label for="title" class="form-label fw-semibold">
              <?php echo $lang_title ?? 'Title'; ?>
              <span class="text-danger" aria-hidden="true">*</span>
            </label>
            <input id="title" name="title" type="text" class="form-control"
                   maxlength="150" required
                   value="eMail through PowerPHPBoard">
            <div class="invalid-feedback">Bitte einen Betreff angeben.</div>
          </div>
          <div class="mb-3">
            <label for="emailcontent" class="form-label fw-semibold">
              <?php echo $lang_text ?? 'Text'; ?>
              <span class="text-danger" aria-hidden="true">*</span>
            </label>
            <textarea id="emailcontent" name="emailcontent" class="form-control" rows="8" required></textarea>
            <div class="invalid-feedback">Bitte einen Inhalt eingeben.</div>
          </div>
        </div>
        <footer class="card-footer bg-light">
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-send" aria-hidden="true"></i>
            <?php echo $lang_send ?? 'Send'; ?>
          </button>
          <a class="btn btn-link" href="showprofile.php?userid=<?php echo (int) $recipient['id']; ?>">
            <?php echo $lang_back ?? 'Back'; ?>
          </a>
        </footer>
      </section>
    </form>
  <?php endif; ?>

  </div>
</div>

<?php include __DIR__ . '/footer.inc.php'; ?>
