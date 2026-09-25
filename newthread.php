<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - New Thread Form
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

use PowerPHPBoard\Auth;
use PowerPHPBoard\BoardAccess;
use PowerPHPBoard\CSRF;
use PowerPHPBoard\Database;
use PowerPHPBoard\Security;
use PowerPHPBoard\Session;
use PowerPHPBoard\Validator;

require_once __DIR__ . '/config.inc.php';
require_once __DIR__ . '/includes/autoload.php';
require_once __DIR__ . '/functions.inc.php';

Session::start();

$boardid = Security::getInt('boardid');
$newthread = Security::getInt('newthread');

try {
    $db = Database::getInstance($mysql);
} catch (PDOException $e) {
    die('Database connection failed');
}

// Angemeldeter Benutzer (deaktivierte Konten gelten als abgemeldet)
$ppbuser = Auth::currentUser($db) ?? [];
$loggedin = $ppbuser !== [] ? 'YES' : 'NO';

$board = [];
if ($boardid > 0) {
    $board = $db->fetchOne(
        "SELECT * FROM ppb_boards WHERE id = ? AND type = 'Board'",
        [$boardid]
    );
    if ($board === null) {
        $board = [];
    }
}

// Zugang zu privaten Boards: ohne Freischaltung weder Formular noch
// Zitat, noch Speichern (siehe BoardAccess)
$accessState = ppb_board_access($board, $ppbuser, $db);
$hasAccess = $accessState === BoardAccess::GRANTED;

$settings = $db->fetchOne('SELECT * FROM ppb_config WHERE id = ?', [1]) ?? [];

$langFile = match ($settings['language'] ?? 'English') {
    'Deutsch-Sie' => 'deutsch-sie.inc.php',
    'Deutsch-Du' => 'deutsch-du.inc.php',
    default => 'english.inc.php',
};
require_once __DIR__ . '/' . $langFile;

$formError = '';
$threadCreated = false;

if (!empty($board['title']) && $hasAccess && ($board['status'] ?? '') !== 'Closed'
    && $_SERVER['REQUEST_METHOD'] === 'POST' && $newthread === 1) {
    if (!CSRF::validateFromPost()) {
        $formError = $lang_csrfinvalid ?? 'The security token is invalid. Please reload the page and try again.';
    } else {
        $title = Security::getString('title', 'POST');
        $text = Security::getString('text', 'POST');
        $icon = Security::getString('icon', 'POST');

        if ($title === '' || $text === '') {
            $formError = $lang_insertvaluesforall ?? 'Please fill in all fields';
        } elseif (!Validator::withinLength($text, Validator::POST_MAX)) {
            $formError = $lang_posttoolong ?? 'Post text is too long.';
        } elseif ($loggedin !== 'YES') {
            $formError = $lang_loginfirst ?? 'You have to log in first';
        } else {
            $title = trim($title);
            $text = trim($text);
            $now = time();
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';

            $validIcons = ['icon1.gif', 'icon2.gif', 'icon3.gif', 'icon4.gif', 'icon5.gif', 'icon6.gif', 'icon7.gif',
                           'icon8.gif', 'icon9.gif', 'icon10.gif', 'icon11.gif', 'icon12.gif', 'icon13.gif', 'icon14.gif', ''];
            if (!in_array($icon, $validIcons, true)) {
                $icon = '';
            }

            $db->query(
                "INSERT INTO ppb_posts (boardid, type, time, author, title, text, icon, views, ip, lastreply, lastauthor)
                     VALUES (?, 'Thread', ?, ?, ?, ?, ?, 0, ?, ?, ?)",
                [$board['id'], $now, $ppbuser['id'], $title, $text, $icon, $ip, $now, $ppbuser['id']]
            );

            $db->query(
                'UPDATE ppb_boards SET lastchange = ?, lastauthor = ? WHERE id = ?',
                [$now, $ppbuser['id'], $board['id']]
            );

            CSRF::regenerate();
            $threadCreated = true;
        }
    }
}

include __DIR__ . '/header.inc.php';
?>

<?php if (empty($board['title'])): ?>
  <?php
  default_error(
      $lang_chooseboard ?? 'Please select a board',
      'index.php',
      $lang_boardlist ?? 'Board list'
  );
    ?>
<?php elseif (!$hasAccess): ?>
  <?php echo ppb_board_password_form(
      'newthread.php?boardid=' . (int) $board['id'],
      $accessState,
      $lang_thisboardrequirespwd ?? 'This board requires a password'
  ); ?>
<?php elseif (($board['status'] ?? '') === 'Closed'): ?>
  <?php
  default_error(
      $lang_boardclosedcannotopenthread ?? 'Board is closed, cannot create thread',
      'showboard.php?boardid=' . (int) ($board['id'] ?? 0),
      sprintf($lang_backtoboard ?? 'Back to the board "%s"', (string) ($board['title'] ?? ''))
  );
      ?>
<?php elseif ($threadCreated): ?>
  <div class="card shadow-sm border-success mb-4">
    <header class="card-header bg-success text-white">
      <h2 class="h6 mb-0">
        <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
        <?php echo Security::escape($lang_statusmessage ?? 'Status'); ?>
      </h2>
    </header>
    <div class="card-body">
      <p class="mb-3">
        <?php echo Security::escape($lang_openedthreadsuccessfull ?? 'Thread created successfully'); ?>
      </p>
      <a href="showboard.php?boardid=<?php echo (int) $boardid; ?>" class="btn btn-primary">
        <i class="bi bi-arrow-left" aria-hidden="true"></i>
        <?php echo Security::escape(sprintf($lang_backtoboard ?? 'Back to the board "%s"', (string) ($board['title'] ?? ''))); ?>
      </a>
    </div>
  </div>
<?php else: ?>
  <?php if ($formError !== ''): ?>
    <div class="alert alert-danger" role="alert">
      <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
      <?php echo Security::escape($formError); ?>
    </div>
  <?php endif; ?>

  <?php $formIcon = $formError !== '' ? Security::getString('icon', 'POST') : ''; ?>
  <form action="newthread.php?boardid=<?php echo (int) $boardid; ?>&newthread=1"
        method="post" class="needs-validation" novalidate>
    <?php echo CSRF::getTokenField(); ?>

    <section class="card shadow-sm mb-3">
      <header class="card-header bg-secondary-subtle">
        <h1 class="h5 mb-0">
          <i class="bi bi-plus-circle" aria-hidden="true"></i>
          <?php echo Security::escape($lang_newthread ?? 'New Thread'); ?>
          <small class="text-body-secondary">&middot; <?php echo Security::escape((string) $board['title']); ?></small>
        </h1>
      </header>
      <div class="card-body">

        <?php if ($loggedin !== 'YES'): ?>
          <div class="alert alert-warning small d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-info-circle" aria-hidden="true"></i>
            <div>
              <?php echo Security::escape($lang_loginfirst ?? 'You have to log in first.'); ?>
              <a class="alert-link" href="login.php">
                <?php echo Security::escape($lang_login ?? 'Login'); ?>
              </a>
              <?php echo Security::escape($lang_or ?? 'or'); ?>
              <a class="alert-link" href="register.php">
                <?php echo Security::escape($lang_wanttoregister ?? 'Register'); ?>
              </a>
            </div>
          </div>
        <?php endif; ?>

        <div class="mb-3">
          <label for="title" class="form-label fw-semibold">
            <?php echo Security::escape($lang_title ?? 'Title'); ?>
            <span class="text-danger" aria-hidden="true">*</span>
          </label>
          <input id="title" name="title" type="text" class="form-control"
                 maxlength="150" required
                 value="<?php echo Security::escape($formError !== '' ? Security::getString('title', 'POST') : ''); ?>"
                 aria-describedby="titleHelp">
          <div id="titleHelp" class="form-text"><?php echo Security::escape($lang_titlehelp ?? 'A meaningful title, up to 150 characters.'); ?></div>
          <div class="invalid-feedback"><?php echo Security::escape($lang_inserttitle ?? 'Please enter a title.'); ?></div>
        </div>

        <fieldset class="mb-3">
          <legend class="form-label fw-semibold mb-2 fs-6">
            <?php echo Security::escape($lang_icon ?? 'Icon'); ?>
          </legend>
          <div class="d-flex flex-wrap gap-2 align-items-center">
            <?php for ($i = 1; $i <= 14; $i++): ?>
              <div class="form-check form-check-inline mb-0">
                <input class="form-check-input" type="radio" name="icon"
                       id="icon<?php echo $i; ?>" value="icon<?php echo $i; ?>.gif"
                       <?php echo $formIcon === 'icon' . $i . '.gif' ? 'checked' : ''; ?>>
                <label class="form-check-label" for="icon<?php echo $i; ?>">
                  <img src="images/icon<?php echo $i; ?>.gif" width="15" height="15" alt="Icon <?php echo $i; ?>">
                </label>
              </div>
            <?php endfor; ?>
            <div class="form-check form-check-inline mb-0">
              <input class="form-check-input" type="radio" name="icon" id="iconNone" value=""
                     <?php echo $formIcon === '' ? 'checked' : ''; ?>>
              <label class="form-check-label" for="iconNone">
                <?php echo Security::escape($lang_noicon ?? 'No icon'); ?>
              </label>
            </div>
          </div>
        </fieldset>

        <div class="mb-3">
          <label for="text" class="form-label fw-semibold">
            <?php echo Security::escape($lang_text ?? 'Text'); ?>
            <span class="text-danger" aria-hidden="true">*</span>
          </label>
          <textarea id="text" name="text" class="form-control" rows="12" required
                    maxlength="<?php echo Validator::POST_MAX; ?>"><?php echo Security::escape($formError !== '' ? Security::getString('text', 'POST') : ''); ?></textarea>
          <div class="form-text">
            <?php echo Security::escape($lang_htmlcodeis ?? 'HTML ist'); ?>
            <strong><?php echo Security::escape(ppb_onoff_label($settings['htmlcode'] ?? 'OFF')); ?></strong>,
            <a href="bbcode.php?catid=<?php echo (int) ($catid ?? 0); ?>&boardid=<?php echo (int) $boardid; ?>" target="_blank" rel="noopener">
              <?php echo Security::escape($lang_bbcodeis ?? 'BBCode ist'); ?>
              <strong><?php echo Security::escape(ppb_onoff_label($settings['bbcode'] ?? 'ON')); ?></strong></a>,
            <a href="smilies.php?catid=<?php echo (int) ($catid ?? 0); ?>&boardid=<?php echo (int) $boardid; ?>" target="_blank" rel="noopener">
              <?php echo Security::escape($lang_smiliesare ?? 'Smilies sind'); ?>
              <strong><?php echo Security::escape(ppb_onoff_label($settings['smilies'] ?? 'ON')); ?></strong></a>.
          </div>
          <div class="invalid-feedback"><?php echo Security::escape($lang_inserttext ?? 'Please enter a text.'); ?></div>
        </div>
      </div>
      <footer class="card-footer bg-light d-flex flex-wrap gap-2">
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-send" aria-hidden="true"></i>
          <?php echo Security::escape($lang_send ?? 'Send'); ?>
        </button>
        <button type="reset" class="btn btn-outline-secondary">
          <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
          <?php echo Security::escape($lang_reset ?? 'Reset'); ?>
        </button>
        <a class="btn btn-link"
           href="showboard.php?boardid=<?php echo (int) $boardid; ?>">
          <?php echo Security::escape($lang_back ?? 'Back'); ?>
        </a>
      </footer>
    </section>
  </form>
<?php endif; ?>

<?php include __DIR__ . '/footer.inc.php'; ?>
