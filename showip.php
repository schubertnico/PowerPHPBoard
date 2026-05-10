<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Show IP Address (Admin/Mod Only)
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

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
$threadid = Security::getInt('threadid');
$postid = Security::getInt('postid');

$board = [];
if ($boardid > 0) {
    $board = $db->fetchOne('SELECT * FROM ppb_boards WHERE id = ?', [$boardid]) ?? [];
}

include __DIR__ . '/header.inc.php';
?>

<div class="row justify-content-center">
  <div class="col-md-8 col-lg-6">

  <?php if ($threadid === 0 || $postid === 0): ?>
    <?php default_error($lang_choosepost ?? 'Please choose a post', 'index.php', 'Home'); ?>
  <?php else:
      $showip = false;
      if (($ppbuser['status'] ?? '') === 'Administrator') {
          $showip = true;
      } else {
          $mods = explode(',', (string) ($board['mods'] ?? ''));
          foreach ($mods as $modEmail) {
              if (($ppbuser['email'] ?? '') === trim($modEmail)) {
                  $showip = true;
                  break;
              }
          }
      }

      if (!$showip):
          ?>
    <?php default_error($lang_onlyadminscanviewip ?? 'Only administrators and moderators can view IP addresses', 'index.php', 'Home'); ?>
  <?php else:
      $post = $db->fetchOne('SELECT * FROM ppb_posts WHERE id = ?', [$postid]);
      ?>
    <section class="card shadow-sm">
      <header class="card-header bg-secondary-subtle d-flex align-items-center gap-2">
        <i class="bi bi-geo-alt-fill" aria-hidden="true"></i>
        <h1 class="h6 mb-0">
          <?php echo $lang_ipaddressforpost ?? 'IP Address for post'; ?> #<?php echo (int) $postid; ?>
        </h1>
      </header>
      <div class="card-body">
        <?php if ($post === null): ?>
          <div class="alert alert-warning mb-0" role="alert">
            <?php echo $lang_nopostwithid ?? 'No post with this ID found'; ?>
          </div>
        <?php elseif ((int) $post['threadid'] === $threadid || (int) $post['id'] === $threadid): ?>
          <p class="mb-1 small text-body-secondary">
            <?php echo $lang_ipaddressis ?? 'IP Address is:'; ?>
          </p>
          <code class="fs-5"><?php echo Security::escape($post['ip'] ?? 'Unknown'); ?></code>
        <?php else: ?>
          <div class="alert alert-warning mb-0" role="alert">
            <?php echo $lang_postingdoesntbelongtothread ?? 'This post does not belong to this thread'; ?>
          </div>
        <?php endif; ?>
      </div>
      <footer class="card-footer bg-light">
        <a class="btn btn-outline-secondary btn-sm" href="javascript:history.back()">
          <i class="bi bi-arrow-left" aria-hidden="true"></i>
          <?php echo $lang_back ?? 'Back'; ?>
        </a>
      </footer>
    </section>
  <?php endif; endif; ?>

  </div>
</div>

<?php include __DIR__ . '/footer.inc.php'; ?>
