<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Send Mail to User
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

use PowerPHPBoard\Auth;
use PowerPHPBoard\BoardUrl;
use PowerPHPBoard\CSRF;
use PowerPHPBoard\Database;
use PowerPHPBoard\DatabaseRateLimitStorage;
use PowerPHPBoard\ErrorHandler;
use PowerPHPBoard\Mailer;
use PowerPHPBoard\RateLimiter;
use PowerPHPBoard\Security;
use PowerPHPBoard\Session;

require_once __DIR__ . '/config.inc.php';
require_once __DIR__ . '/includes/autoload.php';
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

// Angemeldeter Benutzer (deaktivierte Konten gelten als abgemeldet)
$ppbuser = Auth::currentUser($db) ?? [];
$loggedin = $ppbuser !== [] ? 'YES' : 'NO';

$catid = Security::getInt('catid');
$boardid = Security::getInt('boardid');
$userid = Security::getInt('userid');
$sendmail = Security::getString('sendmail');

$state = 'form';
$errorText = '';
$recipient = null;
$title = '';
$emailcontent = '';

// Schutz vor Spam über das Forum: höchstens 10 Mails pro Stunde und Benutzer
$mailLimiter = new RateLimiter(
    new DatabaseRateLimitStorage($db),
    maxAttempts: 10,
    windowSeconds: 3600,
    lockSeconds: 3600
);

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
            $errorText = $lang_csrfinvalid ?? 'The security token is invalid. Please reload the page and try again.';
        } else {
            $title = Security::getString('title', 'POST');
            $emailcontent = Security::getString('emailcontent', 'POST');

            $limitKey = 'user:' . (int) $ppbuser['id'];
            if ($title === '' || $emailcontent === '') {
                $errorText = $lang_insertvaluesforall ?? 'Please fill in all fields';
            } elseif (!$mailLimiter->check('sendmail', $limitKey)) {
                $errorText = $lang_toomanyattempts ?? 'Too many attempts. Please try again later.';
            } else {
                // Absender ist das Forum; Antworten gehen per Reply-To an den Benutzer
                $boardUrl = BoardUrl::base($settings);
                $boardName = (string) ($settings['boardtitle'] ?? 'PowerPHPBoard')
                    . ($boardUrl !== null ? ' (' . $boardUrl . ')' : '');
                $message = $emailcontent . "\n\n-- \n" . sprintf(
                    $lang_mailsentvia ?? '%1$s sent you this email via %2$s. Replies go directly to %1$s.',
                    (string) $ppbuser['username'],
                    $boardName
                );
                $mailLimiter->recordFailure('sendmail', $limitKey);
                $sent = Mailer::fromConfig($mail ?? [])->send(
                    (string) $recipient['email'],
                    Mailer::senderAddress($settings, $mail ?? []),
                    $title,
                    $message,
                    (string) $ppbuser['email']
                );
                if ($sent) {
                    CSRF::regenerate();
                    $state = 'sent';
                } else {
                    ErrorHandler::logConfigurationError('Benutzer-Mail konnte nicht versendet werden (SMTP-Einstellungen prüfen).');
                    $errorText = $lang_emailsendfailed ?? 'The email could not be sent. Please try again later.';
                }
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
          <?php echo Security::escape($lang_statusmessage ?? 'Status'); ?>
        </h2>
      </header>
      <div class="card-body">
        <p class="mb-3">
          <?php echo Security::escape($lang_emailsentsuccessfull ?? 'Email sent successfully'); ?>
        </p>
        <a href="showprofile.php?userid=<?php echo (int) $recipient['id']; ?>" class="btn btn-primary">
          <i class="bi bi-person" aria-hidden="true"></i>
          <?php echo Security::escape(sprintf($lang_profileof ?? 'Profile of %s', (string) $recipient['username'])); ?>
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
            <?php echo Security::escape($lang_sendmail ?? 'Send Email'); ?>
          </h1>
        </header>
        <div class="card-body">
          <dl class="row mb-3">
            <dt class="col-sm-3"><?php echo Security::escape($lang_from ?? 'From'); ?></dt>
            <dd class="col-sm-9">
              <a class="text-decoration-none" href="showprofile.php?userid=<?php echo (int) $ppbuser['id']; ?>">
                <?php echo Security::escape((string) $ppbuser['username']); ?>
              </a>
            </dd>
            <dt class="col-sm-3"><?php echo Security::escape($lang_to ?? 'To'); ?></dt>
            <dd class="col-sm-9">
              <a class="text-decoration-none" href="showprofile.php?userid=<?php echo (int) $recipient['id']; ?>">
                <?php echo Security::escape((string) $recipient['username']); ?>
              </a>
            </dd>
          </dl>
          <div class="mb-3">
            <label for="title" class="form-label fw-semibold">
              <?php echo Security::escape($lang_subject ?? 'Subject'); ?>
              <span class="text-danger" aria-hidden="true">*</span>
            </label>
            <input id="title" name="title" type="text" class="form-control"
                   maxlength="150" required
                   value="<?php echo Security::escape($title !== '' ? $title : ($lang_mailsubjectdefault ?? 'Message from the forum')); ?>">
            <div class="invalid-feedback"><?php echo Security::escape($lang_insertsubject ?? 'Please enter a subject.'); ?></div>
          </div>
          <div class="mb-3">
            <label for="emailcontent" class="form-label fw-semibold">
              <?php echo Security::escape($lang_text ?? 'Text'); ?>
              <span class="text-danger" aria-hidden="true">*</span>
            </label>
            <textarea id="emailcontent" name="emailcontent" class="form-control" rows="8" required><?php echo Security::escape($emailcontent); ?></textarea>
            <div class="invalid-feedback"><?php echo Security::escape($lang_inserttext ?? 'Please enter a text.'); ?></div>
          </div>
        </div>
        <footer class="card-footer bg-light">
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-send" aria-hidden="true"></i>
            <?php echo Security::escape($lang_send ?? 'Send'); ?>
          </button>
          <a class="btn btn-link" href="showprofile.php?userid=<?php echo (int) $recipient['id']; ?>">
            <?php echo Security::escape($lang_back ?? 'Back'); ?>
          </a>
        </footer>
      </section>
    </form>
  <?php endif; ?>

  </div>
</div>

<?php include __DIR__ . '/footer.inc.php'; ?>
