<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Main Index / Board List
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

use PowerPHPBoard\Security;

include __DIR__ . '/header.inc.php';

if ($catid > 0) {
    $categories = $db->fetchAll(
        "SELECT * FROM ppb_boards WHERE type = 'Boardcategory' AND id = ? ORDER BY id",
        [$catid]
    );
} else {
    $categories = $db->fetchAll("SELECT * FROM ppb_boards WHERE type = 'Boardcategory' ORDER BY id");
}
?>

<?php if (count($categories) > 0): ?>
  <?php foreach ($categories as $category): ?>
    <section class="card shadow-sm mb-4">
      <header class="card-header bg-secondary-subtle">
        <h2 class="h5 mb-0">
          <a class="link-dark fw-semibold text-decoration-none"
             href="index.php?catid=<?php echo (int) $category['id']; ?>">
            <?php echo Security::escape((string) $category['title']); ?>
          </a>
        </h2>
      </header>
      <?php
      $boards = $db->fetchAll(
          "SELECT * FROM ppb_boards WHERE type = 'Board' AND catid = ? ORDER BY title",
          [$category['id']]
      );
      ?>

      <?php if (count($boards) > 0): ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th scope="col" class="text-center" style="width:48px;">
                  <span class="visually-hidden">Status</span>
                </th>
                <th scope="col"><?php echo $lang_board ?? 'Board'; ?></th>
                <th scope="col" class="text-end" style="width:80px;">
                  <?php echo $lang_postings ?? 'Posts'; ?>
                </th>
                <th scope="col" class="text-end" style="width:80px;">
                  <?php echo $lang_threads ?? 'Threads'; ?>
                </th>
                <th scope="col" class="d-none d-md-table-cell" style="width:220px;">
                  <?php echo $lang_lastpost ?? 'Last Post'; ?>
                </th>
                <th scope="col" class="d-none d-lg-table-cell" style="width:160px;">
                  <?php echo $lang_moderatedby ?? 'Moderators'; ?>
                </th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($boards as $boardRow): ?>
              <?php
              $statusIcon = '<i class="bi bi-chat-dots text-secondary fs-5" aria-hidden="true"></i>';
              $statusLabel = $lang_nonewpostings ?? 'No new posts';
              if ($boardRow['status'] === 'Closed') {
                  $statusIcon = '<i class="bi bi-lock-fill text-secondary fs-5" aria-hidden="true"></i>';
                  $statusLabel = $lang_closedboard ?? 'Closed';
              } elseif ($boardRow['status'] === 'Private') {
                  $statusIcon = '<i class="bi bi-shield-lock-fill text-warning fs-5" aria-hidden="true"></i>';
                  $statusLabel = $lang_privateboard ?? 'Private';
              } elseif ($loggedin === 'YES') {
                  $visit = $db->fetchOne(
                      "SELECT time FROM ppb_visits WHERE userid = ? AND vid = ? AND type = 'Board'",
                      [$ppbuser['id'], $boardRow['id']]
                  );
                  if ($visit !== null && $visit['time'] < $boardRow['lastchange']) {
                      $statusIcon = '<i class="bi bi-chat-dots-fill text-primary fs-5" aria-hidden="true"></i>';
                      $statusLabel = $lang_newpostings ?? 'New posts';
                  }
              }

              $postCount = (int) ($db->fetchOne(
                  'SELECT COUNT(*) as count FROM ppb_posts WHERE boardid = ?',
                  [$boardRow['id']]
              )['count'] ?? 0);
              $threadCount = (int) ($db->fetchOne(
                  "SELECT COUNT(*) as count FROM ppb_posts WHERE boardid = ? AND type = 'Thread'",
                  [$boardRow['id']]
              )['count'] ?? 0);
              ?>
              <tr>
                <td class="text-center" title="<?php echo Security::escape($statusLabel); ?>">
                  <span class="visually-hidden"><?php echo Security::escape($statusLabel); ?></span>
                  <?php echo $statusIcon; ?>
                </td>
                <td>
                  <a class="fw-semibold link-dark text-decoration-none"
                     href="showboard.php?boardid=<?php echo (int) $boardRow['id']; ?>">
                    <?php echo Security::escape((string) $boardRow['title']); ?>
                  </a>
                  <?php if (!empty($boardRow['description'])): ?>
                    <div class="small text-body-secondary"><?php echo Security::escape((string) $boardRow['description']); ?></div>
                  <?php endif; ?>
                  <?php if ((int) $boardRow['lastchange'] > 0): ?>
                    <div class="small text-body-secondary d-md-none">
                      <?php echo $lang_lastpost ?? 'Last Post'; ?>:
                      <?php echo Security::escape(date('d.m.Y - H:i', (int) $boardRow['lastchange'])); ?>
                    </div>
                  <?php endif; ?>
                </td>
                <td class="text-end"><?php echo $postCount; ?></td>
                <td class="text-end"><?php echo $threadCount; ?></td>
                <td class="d-none d-md-table-cell small">
                  <?php if ((int) $boardRow['lastchange'] > 0):
                      $dateAndTime = date('d.m.Y - H:i', (int) $boardRow['lastchange']);
                      $lastAuthor = $db->fetchOne(
                          'SELECT username FROM ppb_users WHERE id = ?',
                          [$boardRow['lastauthor']]
                      );
                      if ($lastAuthor !== null):
                          $lastPost = $db->fetchOne(
                              'SELECT id, threadid FROM ppb_posts WHERE boardid = ? AND time = ? AND author = ?',
                              [$boardRow['id'], $boardRow['lastchange'], $boardRow['lastauthor']]
                          );
                          $lastPostLink = '#';
                          if ($lastPost !== null) {
                              $lastPostThreadId = ($lastPost['threadid'] == 0) ? $lastPost['id'] : $lastPost['threadid'];
                              $postInThread = $db->fetchOne(
                                  'SELECT COUNT(*) as count FROM ppb_posts WHERE threadid = ?',
                                  [$lastPostThreadId]
                              );
                              $currentPostings = (int) floor(((int) ($postInThread['count'] ?? 0)) / 25) * 25;
                              $lastPostLink = 'showthread.php?threadid=' . (int) $lastPostThreadId
                                  . '&current=' . $currentPostings . '#post' . (int) $lastPost['id'];
                          }
                  ?>
                    <a href="<?php echo Security::escape($lastPostLink); ?>"
                       class="text-decoration-none"
                       title="<?php echo $lang_jumptolastpost ?? 'Jump to last post'; ?>">
                      <i class="bi bi-arrow-right-circle" aria-hidden="true"></i>
                    </a>
                    <?php echo Security::escape($dateAndTime); ?><br>
                    <span class="text-body-secondary">von</span>
                    <?php echo Security::escape((string) $lastAuthor['username']); ?>
                  <?php else: ?>
                    <span class="text-body-secondary"><?php echo $lang_nopostings ?? 'No posts'; ?></span>
                  <?php endif; ?>
                  <?php else: ?>
                    <span class="text-body-secondary"><?php echo $lang_nopostings ?? 'No posts'; ?></span>
                  <?php endif; ?>
                </td>
                <td class="d-none d-lg-table-cell small">
                  <?php
                  if (!empty($boardRow['mods'])) {
                      $mods = explode(',', (string) $boardRow['mods']);
                      $first = true;
                      foreach ($mods as $modEmail) {
                          $modEmail = trim($modEmail);
                          if ($modEmail === '') {
                              continue;
                          }
                          $mod = $db->fetchOne(
                              'SELECT id, username FROM ppb_users WHERE email = ?',
                              [$modEmail]
                          );
                          if ($mod !== null) {
                              if (!$first) {
                                  echo ', ';
                              }
                              echo '<a class="text-decoration-none" href="showprofile.php?userid='
                                  . (int) $mod['id'] . '&catid=' . (int) $catid
                                  . '&boardid=' . (int) $boardid . '">'
                                  . Security::escape((string) $mod['username']) . '</a>';
                              $first = false;
                          }
                      }
                  } else {
                      echo '<span class="text-body-secondary">&ndash;</span>';
                  }
                  ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="card-body text-center text-body-secondary">
          <?php echo $lang_noboardsincat ?? 'No boards in this category'; ?>
        </div>
      <?php endif; ?>
    </section>
  <?php endforeach; ?>
<?php else: ?>
  <?php echo ppb_alert(
      $lang_nocatsindb ?? 'No categories found',
      'warning',
      'Hinweis'
  ); ?>
<?php endif; ?>

<?php
if ($loggedin === 'YES') {
    $now = time();
    $db->query('UPDATE ppb_users SET lastvisit = ? WHERE id = ?', [$now, $ppbuser['id']]);
}

$now = time();
$userOnlineTime = $now - 30;
$onlineUsers = $db->fetchAll(
    'SELECT id, username FROM ppb_users WHERE lastvisit > ? ORDER BY username',
    [$userOnlineTime]
);
?>

<section class="card shadow-sm mb-4" aria-label="Aktuell online">
  <header class="card-header bg-secondary-subtle d-flex align-items-center gap-2">
    <i class="bi bi-people-fill" aria-hidden="true"></i>
    <h2 class="h6 mb-0">Benutzer online</h2>
    <span class="badge text-bg-primary ms-auto"><?php echo count($onlineUsers); ?></span>
  </header>
  <div class="card-body">
    <?php if (count($onlineUsers) > 0): ?>
      <p class="mb-0 small">
      <?php
      $links = [];
      foreach ($onlineUsers as $onlineUser) {
          $links[] = '<a class="text-decoration-none" href="showprofile.php?userid='
              . (int) $onlineUser['id'] . '&catid=' . (int) $catid
              . '&boardid=' . (int) $boardid . '">'
              . Security::escape((string) $onlineUser['username']) . '</a>';
      }
      echo implode(', ', $links);
      ?>
      </p>
    <?php else: ?>
      <p class="mb-0 text-body-secondary small">
        <?php echo $lang_noregisteredonline ?? 'No registered users online'; ?>
      </p>
    <?php endif; ?>
  </div>
</section>

<aside class="text-body-secondary small d-flex flex-wrap gap-3 mb-4" aria-label="Legende">
  <span><i class="bi bi-chat-dots-fill text-primary" aria-hidden="true"></i>
    <?php echo $lang_newpostings ?? 'New posts'; ?></span>
  <span><i class="bi bi-chat-dots text-secondary" aria-hidden="true"></i>
    <?php echo $lang_nonewpostings ?? 'No new posts'; ?></span>
  <span><i class="bi bi-lock-fill text-secondary" aria-hidden="true"></i>
    <?php echo $lang_closedboard ?? 'Closed'; ?></span>
  <span><i class="bi bi-shield-lock-fill text-warning" aria-hidden="true"></i>
    <?php echo $lang_privateboard ?? 'Private'; ?></span>
</aside>

<?php include __DIR__ . '/footer.inc.php'; ?>
