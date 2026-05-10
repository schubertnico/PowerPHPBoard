<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Edit Post
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

use PowerPHPBoard\CSRF;
use PowerPHPBoard\Database;
use PowerPHPBoard\Security;
use PowerPHPBoard\Session;

require_once __DIR__ . '/config.inc.php';

Session::start();

$postid = Security::getInt('postid');
$catid = Security::getInt('catid');
$boardid = Security::getInt('boardid');
$login = Security::getInt('login', 'REQUEST');
$editpost = Security::getInt('editpost', 'REQUEST');

try {
    $db = Database::getInstance($mysql);
} catch (PDOException $e) {
    die('Database connection failed');
}

$settings = $db->fetchOne('SELECT * FROM ppb_config WHERE id = ?', [1]) ?? [];
$ppbuser = [];
$loggedin = 'NO';

if (Session::isLoggedIn()) {
    $userId = Session::getUserId();
    $ppbuser = $db->fetchOne('SELECT * FROM ppb_users WHERE id = ?', [$userId]);
    if ($ppbuser !== null) {
        $loggedin = 'YES';
        $login = 1;
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

// Determine state ahead of any output
$state = 'form'; // form|error|success
$errorMessage = '';
$errorBack = 'index.php';
$errorBackText = 'Home';
$successMessage = '';
$successLink = '';
$successLinkText = '';
$post = null;
$canedit = false;
$ismod = false;
$adminCanModerate = false;
$user = null;

if ($postid === 0) {
    $state = 'error';
    $errorMessage = $lang_choosepost ?? 'Please select a post';
} else {
    $post = $db->fetchOne('SELECT * FROM ppb_posts WHERE id = ?', [$postid]);
    if ($post === null) {
        $state = 'error';
        $errorMessage = $lang_nopostwithid ?? 'No post with this ID';
    } elseif ($login !== 1 || $loggedin !== 'YES') {
        $state = 'error';
        $errorMessage = $lang_loginfirst ?? 'You have to log in first';
        $errorBack = 'login.php';
        $errorBackText = $lang_login ?? 'Login';
    } else {
        $user = $ppbuser;

        $boardData = $db->fetchOne('SELECT mods FROM ppb_boards WHERE id = ?', [$post['boardid']]);
        if ($boardData !== null && !empty($boardData['mods'])) {
            $mods = explode(',', (string) $boardData['mods']);
            foreach ($mods as $modEmail) {
                $modEmail = trim($modEmail);
                if ($modEmail !== '' && $modEmail === $user['email']) {
                    $canedit = true;
                    $ismod = true;
                    break;
                }
            }
        }
        if ((int) $user['id'] === (int) $post['author'] || $user['status'] === 'Administrator') {
            $canedit = true;
        }
        $adminCanModerate = ($ismod || $user['status'] === 'Administrator');

        if (!$canedit) {
            $state = 'error';
            $errorMessage = $lang_notallowedtoeditpost ?? 'You are not allowed to edit this post';
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !CSRF::validateFromPost()) {
            $state = 'error';
            $errorMessage = 'Security token invalid. Please try again.';
            $errorBack = 'javascript:history.back()';
            $errorBackText = $lang_backtoeditpost ?? 'Back to edit';
        } elseif ($editpost === 1) {
            $title = Security::getString('title', 'POST');
            $text = Security::getString('text', 'POST');
            $icon = Security::getString('icon', 'POST');
            $deletepost = Security::getString('deletepost', 'POST');
            $closethread = Security::getString('closethread', 'POST');
            $openthread = Security::getString('openthread', 'POST');

            if ($text === '') {
                $state = 'error';
                $errorMessage = $lang_inserttext ?? 'Please enter text';
                $errorBack = 'javascript:history.back()';
                $errorBackText = $lang_backtoeditpost ?? 'Back to edit';
            } elseif ($post['type'] === 'Thread') {
                if ($title === '' && $deletepost !== 'YES' && $closethread !== 'YES' && $openthread !== 'YES') {
                    $state = 'error';
                    $errorMessage = $lang_inserttitle ?? 'Please enter a title';
                    $errorBack = 'javascript:history.back()';
                    $errorBackText = $lang_backtoeditpost ?? 'Back to edit';
                } elseif ($deletepost === 'YES' && $adminCanModerate) {
                    $db->query('DELETE FROM ppb_posts WHERE id = ?', [$postid]);
                    $db->query('DELETE FROM ppb_posts WHERE threadid = ?', [$postid]);
                    $state = 'success';
                    $successMessage = $lang_threaddeleted ?? 'Thread deleted';
                    $successLink = 'showboard.php?boardid=' . (int) $post['boardid'];
                    $successLinkText = $lang_showboard ?? 'Show board';
                } elseif ($closethread === 'YES' && $adminCanModerate) {
                    $db->query("UPDATE ppb_posts SET status = 'Closed' WHERE id = ?", [$postid]);
                    $state = 'success';
                    $successMessage = $lang_threadclosed ?? 'Thread closed';
                    $successLink = 'showboard.php?boardid=' . (int) $post['boardid'];
                    $successLinkText = $lang_showboard ?? 'Show board';
                } elseif ($openthread === 'YES' && $adminCanModerate) {
                    $db->query("UPDATE ppb_posts SET status = 'Open' WHERE id = ?", [$postid]);
                    $state = 'success';
                    $successMessage = $lang_threadopened ?? 'Thread opened';
                    $successLink = 'showboard.php?boardid=' . (int) $post['boardid'];
                    $successLinkText = $lang_showboard ?? 'Show board';
                } else {
                    $title = trim($title);
                    $text = trim($text);
                    $validIcons = ['icon1.gif', 'icon2.gif', 'icon3.gif', 'icon4.gif', 'icon5.gif', 'icon6.gif', 'icon7.gif',
                                   'icon8.gif', 'icon9.gif', 'icon10.gif', 'icon11.gif', 'icon12.gif', 'icon13.gif', 'icon14.gif', ''];
                    if (!in_array($icon, $validIcons, true)) {
                        $icon = '';
                    }
                    $db->query(
                        'UPDATE ppb_posts SET title = ?, text = ?, icon = ? WHERE id = ?',
                        [$title, $text, $icon, $postid]
                    );
                    $state = 'success';
                    $successMessage = $lang_threadedited ?? 'Thread edited';
                    $successLink = 'showthread.php?threadid=' . (int) $post['id'];
                    $successLinkText = $lang_showthread ?? 'Show thread';
                }
            } else {
                if ($deletepost === 'YES' && $adminCanModerate) {
                    $db->query('DELETE FROM ppb_posts WHERE id = ?', [$postid]);
                    $state = 'success';
                    $successMessage = $lang_postingdeleted ?? 'Post deleted';
                    $successLink = 'showthread.php?threadid=' . (int) $post['threadid'];
                    $successLinkText = $lang_showthread ?? 'Show thread';
                } else {
                    $text = trim($text);
                    $db->query('UPDATE ppb_posts SET text = ? WHERE id = ?', [$text, $postid]);
                    $state = 'success';
                    $successMessage = $lang_postingedited ?? 'Post edited';
                    $successLink = 'showthread.php?threadid=' . (int) $post['threadid'];
                    $successLinkText = $lang_showthread ?? 'Show thread';
                }
            }
        }
    }
}

include __DIR__ . '/header.inc.php';
?>

<?php if ($state === 'error'): ?>
  <?php default_error($errorMessage, $errorBack, $errorBackText); ?>

<?php elseif ($state === 'success'): ?>
  <div class="card shadow-sm border-success mb-4">
    <header class="card-header bg-success text-white">
      <h2 class="h6 mb-0">
        <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
        <?php echo $lang_statusmessage ?? 'Status'; ?>
      </h2>
    </header>
    <div class="card-body">
      <p class="mb-3"><?php echo Security::escape($successMessage); ?></p>
      <a href="<?php echo Security::escape($successLink); ?>" class="btn btn-primary">
        <i class="bi bi-arrow-left" aria-hidden="true"></i>
        <?php echo Security::escape($successLinkText); ?>
      </a>
    </div>
  </div>

<?php else:
    $iconValue = (string) ($post['icon'] ?? '');
?>
  <form action="editpost.php?postid=<?php echo (int) $post['id']; ?>&login=1&editpost=1&catid=<?php echo (int) $catid; ?>&boardid=<?php echo (int) $boardid; ?>"
        method="post" class="needs-validation" novalidate>
    <?php echo CSRF::getTokenField(); ?>

    <section class="card shadow-sm mb-3">
      <header class="card-header bg-secondary-subtle">
        <h1 class="h5 mb-0">
          <i class="bi bi-pencil-square" aria-hidden="true"></i>
          <?php echo $lang_editpost ?? 'Edit Post'; ?>
        </h1>
      </header>
      <div class="card-body">

        <?php if ($adminCanModerate): ?>
          <fieldset class="border border-warning rounded p-3 mb-3">
            <legend class="h6 float-none w-auto px-2 text-warning-emphasis">
              <i class="bi bi-shield-exclamation" aria-hidden="true"></i>
              Moderation
            </legend>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="deletepost" name="deletepost" value="YES">
              <label class="form-check-label fw-semibold text-danger" for="deletepost">
                <?php echo $post['type'] === 'Thread'
                    ? ($lang_deletethread ?? 'Delete thread')
                    : ($lang_deletepost ?? 'Delete post'); ?>
              </label>
              <div class="form-text">Diese Aktion kann nicht rückgängig gemacht werden.</div>
            </div>
            <?php if ($post['type'] === 'Thread'): ?>
              <?php if ($post['status'] === 'Open' || $post['status'] === ''): ?>
                <div class="form-check mt-2">
                  <input class="form-check-input" type="checkbox" id="closethread" name="closethread" value="YES">
                  <label class="form-check-label fw-semibold" for="closethread">
                    <?php echo $lang_closethread ?? 'Close thread'; ?>
                  </label>
                  <div class="form-text">Niemand kann mehr antworten.</div>
                </div>
              <?php elseif ($post['status'] === 'Closed'): ?>
                <div class="form-check mt-2">
                  <input class="form-check-input" type="checkbox" id="openthread" name="openthread" value="YES">
                  <label class="form-check-label fw-semibold" for="openthread">
                    <?php echo $lang_openthread ?? 'Open thread'; ?>
                  </label>
                </div>
              <?php endif; ?>
            <?php endif; ?>
          </fieldset>
        <?php endif; ?>

        <?php if ($post['type'] === 'Thread'): ?>
          <div class="mb-3">
            <label for="title" class="form-label fw-semibold">
              <?php echo $lang_title ?? 'Title'; ?>
              <span class="text-danger" aria-hidden="true">*</span>
            </label>
            <input id="title" name="title" type="text" class="form-control"
                   maxlength="150" required
                   value="<?php echo Security::escape((string) $post['title']); ?>">
            <div class="invalid-feedback">Bitte einen Titel angeben.</div>
          </div>

          <fieldset class="mb-3">
            <legend class="form-label fw-semibold mb-2 fs-6">
              <?php echo $lang_icon ?? 'Icon'; ?>
            </legend>
            <div class="d-flex flex-wrap gap-2 align-items-center">
              <?php for ($i = 1; $i <= 14; $i++):
                  $val = 'icon' . $i . '.gif';
              ?>
                <div class="form-check form-check-inline mb-0">
                  <input class="form-check-input" type="radio" name="icon"
                         id="icon<?php echo $i; ?>" value="<?php echo $val; ?>"
                         <?php echo $iconValue === $val ? 'checked' : ''; ?>>
                  <label class="form-check-label" for="icon<?php echo $i; ?>">
                    <img src="images/<?php echo $val; ?>" width="15" height="15" alt="Icon <?php echo $i; ?>">
                  </label>
                </div>
              <?php endfor; ?>
              <div class="form-check form-check-inline mb-0">
                <input class="form-check-input" type="radio" name="icon" id="iconNone" value=""
                       <?php echo $iconValue === '' ? 'checked' : ''; ?>>
                <label class="form-check-label" for="iconNone">
                  <?php echo $lang_noicon ?? 'No icon'; ?>
                </label>
              </div>
            </div>
          </fieldset>
        <?php endif; ?>

        <div class="mb-3">
          <label for="text" class="form-label fw-semibold">
            <?php echo $lang_text ?? 'Text'; ?>
            <span class="text-danger" aria-hidden="true">*</span>
          </label>
          <textarea id="text" name="text" class="form-control" rows="12" required><?php echo Security::escape((string) $post['text']); ?></textarea>
          <div class="form-text">
            <?php echo $lang_htmlcodeis ?? 'HTML ist'; ?>
            <strong><?php echo ppb_onoff_label($settings['htmlcode'] ?? 'OFF'); ?></strong>,
            <a href="bbcode.php?catid=<?php echo (int) $catid; ?>&boardid=<?php echo (int) $boardid; ?>" target="_blank" rel="noopener">
              <?php echo $lang_bbcodeis ?? 'BBCode ist'; ?>
              <strong><?php echo ppb_onoff_label($settings['bbcode'] ?? 'ON'); ?></strong>
            </a>,
            <a href="smilies.php?catid=<?php echo (int) $catid; ?>&boardid=<?php echo (int) $boardid; ?>" target="_blank" rel="noopener">
              <?php echo $lang_smiliesare ?? 'Smilies sind'; ?>
              <strong><?php echo ppb_onoff_label($settings['smilies'] ?? 'ON'); ?></strong>
            </a>.
          </div>
          <div class="invalid-feedback">Bitte einen Text eingeben.</div>
        </div>

      </div>
      <footer class="card-footer bg-light d-flex flex-wrap gap-2">
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-save" aria-hidden="true"></i>
          <?php echo $lang_send ?? 'Send'; ?>
        </button>
        <button type="reset" class="btn btn-outline-secondary">
          <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
          <?php echo $lang_reset ?? 'Reset'; ?>
        </button>
        <?php if (!empty($post['threadid']) || $post['type'] === 'Thread'): ?>
          <a class="btn btn-link"
             href="showthread.php?threadid=<?php echo $post['type'] === 'Thread' ? (int) $post['id'] : (int) $post['threadid']; ?>">
            <?php echo $lang_back ?? 'Back'; ?>
          </a>
        <?php endif; ?>
      </footer>
    </section>
  </form>
<?php endif; ?>

<?php include __DIR__ . '/footer.inc.php'; ?>
