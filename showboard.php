<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Board View (Thread List)
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

use PowerPHPBoard\Auth;
use PowerPHPBoard\BoardAccess;
use PowerPHPBoard\Database;
use PowerPHPBoard\Security;
use PowerPHPBoard\Session;
use PowerPHPBoard\ThreadPages;

require_once __DIR__ . '/config.inc.php';
require_once __DIR__ . '/includes/autoload.php';
require_once __DIR__ . '/functions.inc.php';

Session::start();

$boardid = Security::getInt('boardid');

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
        "SELECT id, status, password FROM ppb_boards WHERE id = ? AND type = 'Board'",
        [$boardid]
    );
    if ($board === null) {
        $board = [];
    }
}

// Zugang zu privaten Boards: Passwort nur per Formular, gespeichert wird
// ein Zugangsnachweis statt des Passworts (siehe BoardAccess)
$accessState = ppb_board_access($board, $ppbuser, $db);
$hasAccess = $accessState === BoardAccess::GRANTED;

$settings = $db->fetchOne('SELECT * FROM ppb_config WHERE id = ?', [1]) ?? [];

$langFile = match ($settings['language'] ?? 'English') {
    'Deutsch-Sie' => 'deutsch-sie.inc.php',
    'Deutsch-Du' => 'deutsch-du.inc.php',
    default => 'english.inc.php',
};
require_once __DIR__ . '/' . $langFile;

include __DIR__ . '/header.inc.php';
?>

<?php if ($board === []): ?>
  <?php default_error($lang_chooseboard ?? 'Please select a board', 'index.php', $lang_boardlist ?? 'Board list'); ?>
<?php elseif (!$hasAccess): ?>
  <?php echo ppb_board_password_form(
      'showboard.php?boardid=' . (int) $board['id'],
      $accessState,
      $lang_thisboardrequirespwd ?? 'This board requires a password'
  ); ?>
<?php else: ?>
  <?php
  $threads = $db->fetchAll(
      "SELECT * FROM ppb_posts WHERE type = 'Thread' AND boardid = ? ORDER BY lastreply DESC",
      [$boardid]
  );
      ?>

  <section class="card shadow-sm mb-4">
    <header class="card-header bg-secondary-subtle d-flex flex-wrap align-items-center justify-content-between gap-2">
      <h2 class="h6 mb-0">
        <i class="bi bi-card-list" aria-hidden="true"></i>
        <?php echo Security::escape($lang_thread ?? 'Thread'); ?>
      </h2>
      <span class="badge text-bg-secondary"><?php echo count($threads); ?></span>
    </header>

    <?php if (count($threads) === 0): ?>
      <div class="card-body text-center text-body-secondary">
        <?php echo Security::escape($lang_nothreadsinboard ?? 'No threads in this board'); ?>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th scope="col" class="text-center" style="width:40px;">
                <span class="visually-hidden"><?php echo Security::escape($lang_status ?? 'Status'); ?></span>
              </th>
              <th scope="col"><?php echo Security::escape($lang_thread ?? 'Thread'); ?></th>
              <th scope="col" class="d-none d-md-table-cell" style="width:140px;">
                <?php echo Security::escape($lang_author ?? 'Author'); ?>
              </th>
              <th scope="col" class="text-end d-none d-md-table-cell" style="width:80px;">
                <?php echo Security::escape($lang_replys ?? 'Replies'); ?>
              </th>
              <th scope="col" class="text-end d-none d-md-table-cell" style="width:80px;">
                <?php echo Security::escape($lang_views ?? 'Views'); ?>
              </th>
              <th scope="col" class="d-none d-lg-table-cell" style="width:200px;">
                <?php echo Security::escape($lang_lastreply ?? 'Last Reply'); ?>
              </th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($threads as $row):
              $postCount = (int) ($db->fetchOne(
                  'SELECT COUNT(*) as count FROM ppb_posts WHERE threadid = ? OR id = ?',
                  [$row['id'], $row['id']]
              )['count'] ?? 0);

              $statusIcon = '<i class="bi bi-chat-square-text fs-5 text-secondary" aria-hidden="true"></i>';
              $statusLabel = $lang_nonewreplys ?? 'No new replies';
              $isHot = $postCount > 15;

              if (($row['status'] ?? '') === 'Closed' || ($board['status'] ?? '') === 'Closed') {
                  $statusIcon = '<i class="bi bi-lock-fill fs-5 text-secondary" aria-hidden="true"></i>';
                  $statusLabel = $lang_lockedthread ?? 'Locked thread';
              } elseif ($loggedin === 'YES') {
                  $visit = $db->fetchOne(
                      "SELECT time FROM ppb_visits WHERE userid = ? AND vid = ? AND type = 'Thread'",
                      [$ppbuser['id'], $row['id']]
                  );
                  if ($visit !== null && $visit['time'] < $row['lastreply']) {
                      if ($isHot) {
                          $statusIcon = '<i class="bi bi-fire fs-5 text-danger" aria-hidden="true"></i>';
                          $statusLabel = $lang_newreplys ?? 'New replies';
                      } else {
                          $statusIcon = '<i class="bi bi-chat-square-text-fill fs-5 text-primary" aria-hidden="true"></i>';
                          $statusLabel = $lang_newreplys ?? 'New replies';
                      }
                  } elseif ($isHot) {
                      $statusIcon = '<i class="bi bi-fire fs-5 text-warning" aria-hidden="true"></i>';
                      $statusLabel = $lang_morethan15posts ?? 'More than 15 posts';
                  }
              } elseif ($isHot) {
                  $statusIcon = '<i class="bi bi-fire fs-5 text-warning" aria-hidden="true"></i>';
                  $statusLabel = $lang_morethan15posts ?? 'More than 15 posts';
              }

              $author = $db->fetchOne('SELECT id, username FROM ppb_users WHERE id = ?', [$row['author']]);
              $replyCount = (int) ($db->fetchOne(
                  'SELECT COUNT(*) as count FROM ppb_posts WHERE threadid = ?',
                  [$row['id']]
              )['count'] ?? 0);
              ?>
            <tr>
              <td class="text-center" title="<?php echo Security::escape($statusLabel); ?>">
                <span class="visually-hidden"><?php echo Security::escape($statusLabel); ?></span>
                <?php echo $statusIcon; ?>
              </td>
              <td>
                <?php
                    if ($loggedin === 'YES') {
                        $visit = $db->fetchOne(
                            "SELECT time FROM ppb_visits WHERE userid = ? AND vid = ? AND type = 'Thread'",
                            [$ppbuser['id'], $row['id']]
                        );
                        if ($visit !== null) {
                            $firstUnread = $db->fetchOne(
                                'SELECT id FROM ppb_posts WHERE (id = ? OR threadid = ?) AND `time` > ? ORDER BY `time` LIMIT 1',
                                [$row['id'], $row['id'], $visit['time']]
                            );
                            if ($firstUnread !== null) {
                                echo '<a class="text-decoration-none me-1" href="'
                                    . Security::escape(ThreadPages::postLink($db, (int) $row['id'], (int) $firstUnread['id']))
                                    . '" title="'
                                    . Security::escape($lang_jumptofirstunread ?? 'Jump to first unread post')
                                    . '"><i class="bi bi-arrow-right-circle-fill text-primary" aria-hidden="true"></i></a>';
                            }
                        }
                    }
              ?>
                <?php echo ppb_thread_icon((string) ($row['icon'] ?? '')); ?>
                <a class="link-dark fw-semibold text-decoration-none"
                   href="showthread.php?threadid=<?php echo (int) $row['id']; ?>">
                  <?php echo Security::escape((string) $row['title']); ?>
                </a>
                <?php $pages = getpages((int) $row['id'], $db); ?>
                <?php if ($pages !== ''): ?>
                  <div class="mt-1"><?php echo $pages; ?></div>
                <?php endif; ?>
                <div class="small text-body-secondary d-md-none mt-1">
                  <?php if ($author !== null): ?>
                    <?php echo Security::escape($lang_author ?? 'Author'); ?>:
                    <a class="text-decoration-none" href="showprofile.php?userid=<?php echo (int) $author['id']; ?>&catid=<?php echo (int) $catid; ?>&boardid=<?php echo (int) $boardid; ?>">
                      <?php echo Security::escape((string) $author['username']); ?>
                    </a>
                    &middot;
                  <?php endif; ?>
                  <?php echo Security::escape($lang_replys ?? 'Replies'); ?>: <?php echo $replyCount; ?>
                  &middot;
                  <?php echo Security::escape($lang_views ?? 'Views'); ?>: <?php echo (int) $row['views']; ?>
                </div>
              </td>
              <td class="d-none d-md-table-cell">
                <?php if ($author !== null): ?>
                  <a class="text-decoration-none" href="showprofile.php?userid=<?php echo (int) $author['id']; ?>&catid=<?php echo (int) $catid; ?>&boardid=<?php echo (int) $boardid; ?>">
                    <?php echo Security::escape((string) $author['username']); ?>
                  </a>
                <?php else: ?>
                  <span class="text-body-secondary">&ndash;</span>
                <?php endif; ?>
              </td>
              <td class="text-end d-none d-md-table-cell"><?php echo $replyCount; ?></td>
              <td class="text-end d-none d-md-table-cell"><?php echo (int) $row['views']; ?></td>
              <td class="d-none d-lg-table-cell small">
                <?php echo ppb_last_reply_cell($db, $row, $replyCount); ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <?php
  if ($loggedin === 'YES' && !empty($board['id'])) {
      $now = time();
      $existingVisit = $db->fetchOne(
          "SELECT id FROM ppb_visits WHERE userid = ? AND vid = ? AND type = 'Board'",
          [$ppbuser['id'], $boardid]
      );
      if ($existingVisit !== null) {
          $db->query('UPDATE ppb_visits SET time = ? WHERE id = ?', [$now, $existingVisit['id']]);
      } else {
          $db->query(
              "INSERT INTO ppb_visits (userid, vid, time, type) VALUES (?, ?, ?, 'Board')",
              [$ppbuser['id'], $board['id'], $now]
          );
      }
  }
?>

<?php endif; ?>

<aside class="text-body-secondary small d-flex flex-wrap gap-3 mb-4" aria-label="<?php echo Security::escape($lang_legend ?? 'Legend'); ?>">
  <span><i class="bi bi-chat-square-text-fill text-primary" aria-hidden="true"></i>
    <?php echo Security::escape($lang_newreplys ?? 'New replies'); ?></span>
  <span><i class="bi bi-chat-square-text text-secondary" aria-hidden="true"></i>
    <?php echo Security::escape($lang_nonewreplys ?? 'No new replies'); ?></span>
  <span><i class="bi bi-fire text-warning" aria-hidden="true"></i>
    <?php echo Security::escape($lang_morethan15posts ?? 'More than 15 posts'); ?></span>
  <span><i class="bi bi-lock-fill text-secondary" aria-hidden="true"></i>
    <?php echo Security::escape($lang_lockedthread ?? 'Locked thread'); ?></span>
</aside>

<?php include __DIR__ . '/footer.inc.php'; ?>
