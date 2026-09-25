<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Show IP Address (Admin/Mod Only)
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

use PowerPHPBoard\Auth;
use PowerPHPBoard\Database;
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
$threadid = Security::getInt('threadid');
$postid = Security::getInt('postid');

// Rechte am Board des Beitrags prüfen, nicht am frei wählbaren
// boardid-Parameter (sonst sähe ein Moderator IPs aus fremden Boards)
$post = $postid > 0 ? $db->fetchOne('SELECT * FROM ppb_posts WHERE id = ?', [$postid]) : null;
$postBoard = [];
if ($post !== null) {
    $postBoard = $db->fetchOne('SELECT * FROM ppb_boards WHERE id = ?', [(int) $post['boardid']]) ?? [];
}
$currentUser = $ppbuser !== [] ? $ppbuser : null;
$showip = $post !== null ? Auth::canModerate($currentUser, $postBoard) : Auth::isAdmin($currentUser);

include __DIR__ . '/header.inc.php';
?>

<div class="row justify-content-center">
  <div class="col-md-8 col-lg-6">

  <?php if ($threadid === 0 || $postid === 0): ?>
    <?php default_error($lang_choosepost ?? 'Please choose a post', 'index.php', $lang_home ?? 'Home'); ?>
  <?php elseif (!$showip): ?>
    <?php default_error($lang_onlyadminscanviewip ?? 'Only administrators and moderators can view IP addresses', 'index.php', $lang_home ?? 'Home'); ?>
  <?php else: ?>
    <section class="card shadow-sm">
      <header class="card-header bg-secondary-subtle d-flex align-items-center gap-2">
        <i class="bi bi-geo-alt-fill" aria-hidden="true"></i>
        <h1 class="h6 mb-0">
          <?php echo Security::escape($lang_ipaddressforpost ?? 'IP Address for post'); ?> #<?php echo (int) $postid; ?>
        </h1>
      </header>
      <div class="card-body">
        <?php if ($post === null): ?>
          <div class="alert alert-warning mb-0" role="alert">
            <?php echo Security::escape($lang_nopostwithid ?? 'No post with this ID found'); ?>
          </div>
        <?php elseif ((int) $post['threadid'] === $threadid || (int) $post['id'] === $threadid): ?>
          <p class="mb-1 small text-body-secondary">
            <?php echo Security::escape($lang_ipaddressis ?? 'IP Address is:'); ?>
          </p>
          <code class="fs-5"><?php echo Security::escape($post['ip'] ?? 'Unknown'); ?></code>
        <?php else: ?>
          <div class="alert alert-warning mb-0" role="alert">
            <?php echo Security::escape($lang_postingdoesntbelongtothread ?? 'This post does not belong to this thread'); ?>
          </div>
        <?php endif; ?>
      </div>
      <footer class="card-footer bg-light">
        <a class="btn btn-outline-secondary btn-sm" href="javascript:history.back()">
          <i class="bi bi-arrow-left" aria-hidden="true"></i>
          <?php echo Security::escape($lang_back ?? 'Back'); ?>
        </a>
      </footer>
    </section>
  <?php endif; ?>

  </div>
</div>

<?php include __DIR__ . '/footer.inc.php'; ?>
