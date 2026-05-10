<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Board View (Thread List)
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

use PowerPHPBoard\CSRF;
use PowerPHPBoard\Database;
use PowerPHPBoard\Security;
use PowerPHPBoard\Session;

require_once __DIR__ . '/config.inc.php';

Session::start();

$boardid = Security::getInt('boardid');

try {
    $db = Database::getInstance($mysql);
} catch (PDOException $e) {
    die('Database connection failed');
}

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

$boardpassword = Security::getString('boardpassword', 'POST');
$hasAccess = false;

if (!empty($board['id'])) {
    if (Session::isLoggedIn()) {
        $userId = Session::getUserId();
        $visit = $db->fetchOne(
            "SELECT password FROM ppb_visits WHERE userid = ? AND vid = ? AND type = 'Board'",
            [$userId, $board['id']]
        );
        if ($visit !== null && $visit['password'] === $board['password']) {
            $boardpassword = base64_decode((string) $visit['password']);
            $hasAccess = true;
        }
    }

    $boardpasswordCoded = base64_encode($boardpassword);
    if ($board['status'] === 'Private' && $boardpasswordCoded === $board['password']) {
        $hasAccess = true;

        if (Session::isLoggedIn()) {
            $userId = Session::getUserId();
            $existingVisit = $db->fetchOne(
                "SELECT id, password FROM ppb_visits WHERE userid = ? AND vid = ? AND type = 'Board'",
                [$userId, $board['id']]
            );

            if ($existingVisit !== null) {
                if (empty($existingVisit['password']) || $existingVisit['password'] !== $board['password']) {
                    $db->query(
                        'UPDATE ppb_visits SET password = ? WHERE id = ?',
                        [$boardpasswordCoded, $existingVisit['id']]
                    );
                }
            } else {
                $now = time();
                $db->query(
                    "INSERT INTO ppb_visits (userid, vid, time, type, password) VALUES (?, ?, ?, 'Board', ?)",
                    [$userId, $board['id'], $now, $boardpasswordCoded]
                );
            }
        }
    } elseif ($board['status'] !== 'Private') {
        $hasAccess = true;
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

include __DIR__ . '/header.inc.php';
?>

<?php if ($board['status'] === 'Private' && !$hasAccess): ?>
  <section class="card shadow-sm mb-4 border-warning">
    <header class="card-header bg-warning-subtle">
      <h2 class="h6 mb-0">
        <i class="bi bi-shield-lock-fill" aria-hidden="true"></i>
        <?php echo $lang_thisboardrequirespwd ?? 'This board requires a password'; ?>
      </h2>
    </header>
    <div class="card-body">
      <form action="showboard.php?boardid=<?php echo (int) $board['id']; ?>"
            method="post" class="needs-validation row g-2" novalidate>
        <?php echo CSRF::getTokenField(); ?>
        <div class="col-sm-8">
          <label for="boardpassword" class="form-label fw-semibold">
            <?php echo $lang_password ?? 'Password'; ?>
          </label>
          <input id="boardpassword" name="boardpassword" type="password"
                 class="form-control" maxlength="25" required autocomplete="off">
          <div class="invalid-feedback">Bitte ein Passwort eingeben.</div>
        </div>
        <div class="col-sm-4 d-flex align-items-end">
          <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-unlock" aria-hidden="true"></i> Zugang anfordern
          </button>
        </div>
        <div class="col-12">
          <div class="form-text mt-0">Bitte gib das Forum-Passwort ein, um diesen Bereich zu betreten.</div>
        </div>
      </form>
    </div>
  </section>
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
        <?php echo $lang_thread ?? 'Thread'; ?>
      </h2>
      <span class="badge text-bg-secondary"><?php echo count($threads); ?></span>
    </header>

    <?php if (count($threads) === 0): ?>
      <div class="card-body text-center text-body-secondary">
        <?php echo $lang_nothreadsinboard ?? 'No threads in this board'; ?>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th scope="col" class="text-center" style="width:40px;">
                <span class="visually-hidden">Status</span>
              </th>
              <th scope="col"><?php echo $lang_thread ?? 'Thread'; ?></th>
              <th scope="col" class="d-none d-md-table-cell" style="width:140px;">
                <?php echo $lang_author ?? 'Author'; ?>
              </th>
              <th scope="col" class="text-end d-none d-md-table-cell" style="width:80px;">
                <?php echo $lang_replys ?? 'Replies'; ?>
              </th>
              <th scope="col" class="text-end d-none d-md-table-cell" style="width:80px;">
                <?php echo $lang_views ?? 'Views'; ?>
              </th>
              <th scope="col" class="d-none d-lg-table-cell" style="width:200px;">
                <?php echo $lang_lastreply ?? 'Last Reply'; ?>
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
                            $currentPosts = (int) floor($postCount / 25) * 25;
                            echo '<a class="text-decoration-none me-1" href="showthread.php?threadid='
                                . (int) $row['id'] . '&current=' . $currentPosts
                                . '#post' . (int) $firstUnread['id'] . '" title="'
                                . ($lang_jumptofirstunread ?? 'Jump to first unread')
                                . '"><i class="bi bi-arrow-right-circle-fill text-primary" aria-hidden="true"></i></a>';
                        }
                    }
                }
                ?>
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
                    <?php echo $lang_author ?? 'Author'; ?>:
                    <a class="text-decoration-none" href="showprofile.php?userid=<?php echo (int) $author['id']; ?>&catid=<?php echo (int) $catid; ?>&boardid=<?php echo (int) $boardid; ?>">
                      <?php echo Security::escape((string) $author['username']); ?>
                    </a>
                    &middot;
                  <?php endif; ?>
                  <?php echo $lang_replys ?? 'Replies'; ?>: <?php echo $replyCount; ?>
                  &middot;
                  <?php echo $lang_views ?? 'Views'; ?>: <?php echo (int) $row['views']; ?>
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
                <?php if ($row['lastreply'] == 0): ?>
                  <span class="text-body-secondary"><?php echo $lang_noreplys ?? 'No replies'; ?></span>
                <?php else:
                    $lastAuthor = $db->fetchOne('SELECT username FROM ppb_users WHERE id = ?', [$row['lastauthor']]);
                    if ($lastAuthor !== null):
                        $lastPost = $db->fetchOne(
                            'SELECT id FROM ppb_posts WHERE (threadid = ? OR id = ?) AND time = ? AND author = ?',
                            [$row['id'], $row['id'], $row['lastreply'], $row['lastauthor']]
                        );
                        $jumpLink = '#';
                        if ($lastPost !== null) {
                            $currentPosts = (int) floor($postCount / 25) * 25;
                            $jumpLink = 'showthread.php?threadid=' . (int) $row['id']
                                . '&current=' . $currentPosts . '#post' . (int) $lastPost['id'];
                        }
                ?>
                  <a class="text-decoration-none" href="<?php echo Security::escape($jumpLink); ?>"
                     title="<?php echo $lang_jumptolastpost ?? 'Jump to last post'; ?>">
                    <i class="bi bi-arrow-right-circle" aria-hidden="true"></i>
                  </a>
                  <?php echo Security::escape(date('d.m.Y - H:i', (int) $row['lastreply'])); ?><br>
                  <span class="text-body-secondary">von</span>
                  <?php echo Security::escape((string) $lastAuthor['username']); ?>
                <?php endif; endif; ?>
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

<aside class="text-body-secondary small d-flex flex-wrap gap-3 mb-4" aria-label="Legende">
  <span><i class="bi bi-chat-square-text-fill text-primary" aria-hidden="true"></i>
    <?php echo $lang_newreplys ?? 'New replies'; ?></span>
  <span><i class="bi bi-chat-square-text text-secondary" aria-hidden="true"></i>
    <?php echo $lang_nonewreplys ?? 'No new replies'; ?></span>
  <span><i class="bi bi-fire text-warning" aria-hidden="true"></i>
    <?php echo $lang_morethan15posts ?? 'More than 15 posts'; ?></span>
  <span><i class="bi bi-lock-fill text-secondary" aria-hidden="true"></i>
    <?php echo $lang_lockedthread ?? 'Locked thread'; ?></span>
</aside>

<?php include __DIR__ . '/footer.inc.php'; ?>
