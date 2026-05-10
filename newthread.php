<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - New Thread Form
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

$boardid = Security::getInt('boardid');
$newthread = Security::getInt('newthread');

try {
    $db = Database::getInstance($mysql);
} catch (PDOException $e) {
    die('Database connection failed');
}

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

$boardpassword = Security::getString('boardpassword', 'POST');
$boardpassworddb = '';

if (Session::isLoggedIn() && ($board['status'] ?? '') === 'Private') {
    $userId = Session::getUserId();
    $visit = $db->fetchOne(
        "SELECT password FROM ppb_visits WHERE userid = ? AND vid = ? AND type = 'Board'",
        [$userId, $board['id'] ?? 0]
    );
    if ($visit !== null) {
        $boardpassworddb = base64_decode((string) $visit['password']);
    }
}

$settings = $db->fetchOne('SELECT * FROM ppb_config WHERE id = ?', [1]) ?? [];
$ppbuser = [];
$loggedin = 'NO';

if (Session::isLoggedIn()) {
    $userId = Session::getUserId();
    $ppbuser = $db->fetchOne('SELECT * FROM ppb_users WHERE id = ?', [$userId]);
    if ($ppbuser !== null) {
        $loggedin = 'YES';
    } else {
        $ppbuser = [];
        Session::logout();
    }
}

$langFile = match ($settings['language'] ?? 'English') {
    'Deutsch-Sie' => 'deutsch-sie.inc.php',
    'Deutsch-Du' => 'deutsch-du.inc.php',
    default => 'english.inc.php',
};
require_once __DIR__ . '/' . $langFile;
require_once __DIR__ . '/functions.inc.php';

$formError = '';
$threadCreated = false;

if (!empty($board['title']) && ($board['status'] ?? '') !== 'Closed'
    && $_SERVER['REQUEST_METHOD'] === 'POST' && $newthread === 1) {
    if (!CSRF::validateFromPost()) {
        $formError = 'Security token invalid. Please try again.';
    } else {
        $boardpasswordCoded = base64_encode($boardpassword);
        if (($board['status'] ?? '') === 'Private' && $boardpasswordCoded !== $board['password']) {
            $formError = $lang_bpwdnotcorrect ?? 'Board password incorrect';
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
<?php elseif (($board['status'] ?? '') === 'Closed'): ?>
  <?php
  default_error(
      $lang_boardclosedcannotopenthread ?? 'Board is closed, cannot create thread',
      'showboard.php?boardid=' . (int) ($board['id'] ?? 0),
      ($lang_backto ?? 'Back to') . ' "' . ($board['title'] ?? '') . '" ' . ($lang_board ?? 'board')
  );
  ?>
<?php elseif ($threadCreated): ?>
  <div class="card shadow-sm border-success mb-4">
    <header class="card-header bg-success text-white">
      <h2 class="h6 mb-0">
        <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
        <?php echo $lang_statusmessage ?? 'Status'; ?>
      </h2>
    </header>
    <div class="card-body">
      <p class="mb-3">
        <?php echo $lang_openedthreadsuccessfull ?? 'Thread created successfully'; ?>
      </p>
      <a href="showboard.php?boardid=<?php echo (int) $boardid; ?>" class="btn btn-primary">
        <i class="bi bi-arrow-left" aria-hidden="true"></i>
        <?php echo $lang_backto ?? 'Back to'; ?>
        "<?php echo Security::escape((string) $board['title']); ?>"
        <?php echo $lang_board ?? 'board'; ?>
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

  <form action="newthread.php?boardid=<?php echo (int) $boardid; ?>&newthread=1"
        method="post" class="needs-validation" novalidate>
    <?php echo CSRF::getTokenField(); ?>

    <section class="card shadow-sm mb-3">
      <header class="card-header bg-secondary-subtle">
        <h1 class="h5 mb-0">
          <i class="bi bi-plus-circle" aria-hidden="true"></i>
          <?php echo $lang_newthread ?? 'New Thread'; ?>
          <small class="text-body-secondary">&middot; <?php echo Security::escape((string) $board['title']); ?></small>
        </h1>
      </header>
      <div class="card-body">

        <?php if ($loggedin !== 'YES'): ?>
          <div class="alert alert-warning small d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-info-circle" aria-hidden="true"></i>
            <div>
              <?php echo $lang_loginfirst ?? 'You have to log in first.'; ?>
              <a class="alert-link" href="login.php">
                <?php echo $lang_login ?? 'Login'; ?>
              </a>
              oder
              <a class="alert-link" href="register.php">
                <?php echo $lang_wanttoregister ?? 'Register'; ?>
              </a>
            </div>
          </div>
        <?php endif; ?>

        <?php if (($board['status'] ?? '') === 'Private'): ?>
          <div class="mb-3">
            <label for="boardpassword" class="form-label fw-semibold">
              <?php echo $lang_boardpassword ?? 'Board Password'; ?>
            </label>
            <input id="boardpassword" name="boardpassword" type="password"
                   class="form-control" maxlength="25"
                   value="<?php echo Security::escape($boardpassworddb); ?>">
            <div class="form-text">Wird benötigt, weil dieser Bereich privat ist.</div>
          </div>
        <?php endif; ?>

        <div class="mb-3">
          <label for="title" class="form-label fw-semibold">
            <?php echo $lang_title ?? 'Title'; ?>
            <span class="text-danger" aria-hidden="true">*</span>
          </label>
          <input id="title" name="title" type="text" class="form-control"
                 maxlength="150" required
                 aria-describedby="titleHelp">
          <div id="titleHelp" class="form-text">Eine aussagekräftige Überschrift, max. 150 Zeichen.</div>
          <div class="invalid-feedback">Bitte einen Titel angeben.</div>
        </div>

        <fieldset class="mb-3">
          <legend class="form-label fw-semibold mb-2 fs-6">
            <?php echo $lang_icon ?? 'Icon'; ?>
          </legend>
          <div class="d-flex flex-wrap gap-2 align-items-center">
            <?php for ($i = 1; $i <= 14; $i++): ?>
              <div class="form-check form-check-inline mb-0">
                <input class="form-check-input" type="radio" name="icon"
                       id="icon<?php echo $i; ?>" value="icon<?php echo $i; ?>.gif">
                <label class="form-check-label" for="icon<?php echo $i; ?>">
                  <img src="images/icon<?php echo $i; ?>.gif" width="15" height="15" alt="Icon <?php echo $i; ?>">
                </label>
              </div>
            <?php endfor; ?>
            <div class="form-check form-check-inline mb-0">
              <input class="form-check-input" type="radio" name="icon" id="iconNone" value="" checked>
              <label class="form-check-label" for="iconNone">
                <?php echo $lang_noicon ?? 'No icon'; ?>
              </label>
            </div>
          </div>
        </fieldset>

        <div class="mb-3">
          <label for="text" class="form-label fw-semibold">
            <?php echo $lang_text ?? 'Text'; ?>
            <span class="text-danger" aria-hidden="true">*</span>
          </label>
          <textarea id="text" name="text" class="form-control" rows="12" required
                    maxlength="<?php echo Validator::POST_MAX; ?>"></textarea>
          <div class="form-text">
            <?php echo $lang_htmlcodeis ?? 'HTML ist'; ?>
            <strong><?php echo ppb_onoff_label($settings['htmlcode'] ?? 'OFF'); ?></strong>,
            <a href="bbcode.php?catid=<?php echo (int) ($catid ?? 0); ?>&boardid=<?php echo (int) $boardid; ?>" target="_blank" rel="noopener">
              <?php echo $lang_bbcodeis ?? 'BBCode ist'; ?>
              <strong><?php echo ppb_onoff_label($settings['bbcode'] ?? 'ON'); ?></strong>
            </a>,
            <a href="smilies.php?catid=<?php echo (int) ($catid ?? 0); ?>&boardid=<?php echo (int) $boardid; ?>" target="_blank" rel="noopener">
              <?php echo $lang_smiliesare ?? 'Smilies sind'; ?>
              <strong><?php echo ppb_onoff_label($settings['smilies'] ?? 'ON'); ?></strong>
            </a>.
          </div>
          <div class="invalid-feedback">Bitte einen Beitragstext eingeben.</div>
        </div>
      </div>
      <footer class="card-footer bg-light d-flex flex-wrap gap-2">
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-send" aria-hidden="true"></i>
          <?php echo $lang_send ?? 'Send'; ?>
        </button>
        <button type="reset" class="btn btn-outline-secondary">
          <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
          <?php echo $lang_reset ?? 'Reset'; ?>
        </button>
        <a class="btn btn-link"
           href="showboard.php?boardid=<?php echo (int) $boardid; ?>">
          <?php echo $lang_back ?? 'Back'; ?>
        </a>
      </footer>
    </section>
  </form>
<?php endif; ?>

<?php include __DIR__ . '/footer.inc.php'; ?>
