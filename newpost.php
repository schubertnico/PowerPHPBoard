<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - New Post Form
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

$threadid = Security::getInt('threadid');
$postid = Security::getInt('postid');
$current = Security::getInt('current');
$newpost = Security::getInt('newpost');

try {
    $db = Database::getInstance($mysql);
} catch (PDOException $e) {
    die('Database connection failed');
}

$thread = [];
$board = [];
$boardid = 0;

if ($threadid > 0) {
    $thread = $db->fetchOne(
        "SELECT * FROM ppb_posts WHERE id = ? AND type = 'Thread'",
        [$threadid]
    );
    if ($thread !== null) {
        $boardid = (int) $thread['boardid'];
    } else {
        $thread = [];
    }
}

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
$postCreated = false;
$newPostId = 0;

if (!empty($board['title']) && !empty($thread['title'])
    && ($board['status'] ?? '') !== 'Closed' && ($thread['status'] ?? '') !== 'Closed'
    && $_SERVER['REQUEST_METHOD'] === 'POST' && $newpost === 1) {
    if (!CSRF::validateFromPost()) {
        $formError = 'Security token invalid. Please try again.';
    } else {
        $boardpasswordCoded = base64_encode($boardpassword);
        if (($board['status'] ?? '') === 'Private' && $boardpasswordCoded !== $board['password']) {
            $formError = $lang_bpwdnotcorrect ?? 'Board password incorrect';
        } else {
            $text = Security::getString('text', 'POST');

            if ($text === '') {
                $formError = $lang_insertvaluesforall ?? 'Please fill in all fields';
            } elseif (!Validator::withinLength($text, Validator::POST_MAX)) {
                $formError = $lang_posttoolong ?? 'Post text is too long.';
            } elseif ($loggedin !== 'YES') {
                $formError = $lang_loginfirst ?? 'You have to log in first';
            } else {
                $text = trim($text);
                $now = time();
                $ip = $_SERVER['REMOTE_ADDR'] ?? '';

                $db->query(
                    "INSERT INTO ppb_posts (boardid, threadid, type, time, author, text, ip) VALUES (?, ?, 'Post', ?, ?, ?, ?)",
                    [$board['id'], $thread['id'], $now, $ppbuser['id'], $text, $ip]
                );
                $newPostId = (int) $db->lastInsertId();

                $db->query(
                    'UPDATE ppb_boards SET lastchange = ?, lastauthor = ? WHERE id = ?',
                    [$now, $ppbuser['id'], $board['id']]
                );
                $db->query(
                    'UPDATE ppb_posts SET lastreply = ?, lastauthor = ? WHERE id = ?',
                    [$now, $ppbuser['id'], $thread['id']]
                );

                CSRF::regenerate();
                $postCreated = true;
            }
        }
    }
}

include __DIR__ . '/header.inc.php';

$quoteText = '';
if ($postid > 0 && !$postCreated) {
    $quotePost = $db->fetchOne('SELECT text FROM ppb_posts WHERE id = ?', [$postid]);
    if ($quotePost !== null) {
        $quoteText = '[quote]' . $quotePost['text'] . "[/quote]\n";
    }
}
?>

<?php if (empty($board['title']) || empty($thread['title'])): ?>
  <?php
  default_error(
      $lang_choosethread ?? 'Please select a thread',
      'index.php',
      $lang_boardlist ?? 'Board list'
  );
    ?>
<?php elseif (($board['status'] ?? '') === 'Closed' || ($thread['status'] ?? '') === 'Closed'): ?>
  <?php
    default_error(
        $lang_threadclosedcannotpost ?? 'Thread is closed, cannot post',
        'showboard.php?boardid=' . (int) ($board['id'] ?? 0) . '&current=' . (int) $current,
        ($lang_backto ?? 'Back to') . ' "' . ($board['title'] ?? '') . '" ' . ($lang_board ?? 'board')
    );
        ?>
<?php elseif ($postCreated): ?>
  <div class="card shadow-sm border-success mb-4">
    <header class="card-header bg-success text-white">
      <h2 class="h6 mb-0">
        <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
        <?php echo $lang_statusmessage ?? 'Status'; ?>
      </h2>
    </header>
    <div class="card-body">
      <p class="mb-3">
        <?php echo $lang_newpostcreated ?? 'Post created successfully'; ?>
      </p>
      <a href="showthread.php?threadid=<?php echo (int) $thread['id']; ?>&current=<?php echo (int) $current; ?>#post<?php echo $newPostId; ?>"
         class="btn btn-primary">
        <i class="bi bi-arrow-left" aria-hidden="true"></i>
        <?php echo $lang_backto ?? 'Back to'; ?>
        "<?php echo Security::escape((string) $thread['title']); ?>"
        <?php echo $lang_thread ?? 'thread'; ?>
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

  <form action="newpost.php?threadid=<?php echo (int) $thread['id']; ?>&newpost=1&current=<?php echo (int) $current; ?>"
        method="post" class="needs-validation" novalidate>
    <?php echo CSRF::getTokenField(); ?>

    <section class="card shadow-sm mb-3">
      <header class="card-header bg-secondary-subtle">
        <h1 class="h5 mb-0">
          <i class="bi bi-reply" aria-hidden="true"></i>
          <?php echo $lang_newpost ?? 'New Post'; ?>
          <small class="text-body-secondary">&middot; <?php echo Security::escape((string) $thread['title']); ?></small>
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
          <label for="text" class="form-label fw-semibold">
            <?php echo $lang_text ?? 'Text'; ?>
            <span class="text-danger" aria-hidden="true">*</span>
          </label>
          <textarea id="text" name="text" class="form-control" rows="12" required
                    maxlength="<?php echo Validator::POST_MAX; ?>"><?php echo Security::escape($quoteText); ?></textarea>
          <div class="form-text">
            <?php echo $lang_htmlcodeis ?? 'HTML ist'; ?>
            <strong><?php echo ppb_onoff_label($settings['htmlcode'] ?? 'OFF'); ?></strong>,
            <a href="bbcode.php?catid=<?php echo (int) ($catid ?? 0); ?>&boardid=<?php echo (int) $boardid; ?>"
               target="_blank" rel="noopener">
              <?php echo $lang_bbcodeis ?? 'BBCode ist'; ?>
              <strong><?php echo ppb_onoff_label($settings['bbcode'] ?? 'ON'); ?></strong>
            </a>,
            <a href="smilies.php?catid=<?php echo (int) ($catid ?? 0); ?>&boardid=<?php echo (int) $boardid; ?>"
               target="_blank" rel="noopener">
              <?php echo $lang_smiliesare ?? 'Smilies sind'; ?>
              <strong><?php echo ppb_onoff_label($settings['smilies'] ?? 'ON'); ?></strong>
            </a>.
          </div>
          <div class="invalid-feedback">Bitte einen Text eingeben.</div>
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
           href="showthread.php?threadid=<?php echo (int) $thread['id']; ?>&current=<?php echo (int) $current; ?>">
          <?php echo $lang_back ?? 'Back'; ?>
        </a>
      </footer>
    </section>
  </form>

<?php endif; ?>

<?php include __DIR__ . '/footer.inc.php'; ?>
