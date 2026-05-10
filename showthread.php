<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Thread View (Post List)
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

use PowerPHPBoard\CSRF;
use PowerPHPBoard\Database;
use PowerPHPBoard\Security;
use PowerPHPBoard\Session;
use PowerPHPBoard\TextFormatter;

require_once __DIR__ . '/config.inc.php';

Session::start();

$threadid = Security::getInt('threadid');
$current = Security::getInt('current');

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
        "SELECT id, status, password, title FROM ppb_boards WHERE id = ? AND type = 'Board'",
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
require_once __DIR__ . '/functions.inc.php';

$current2 = $current + 25;
$current3 = $current - 25;

include __DIR__ . '/header.inc.php';

/**
 * Renders the thread pagination block (top + bottom of the thread).
 */
$renderPagination = static function () use ($thread, $db, $current, $current2, $current3, $lang_pages, $lang_prevpage, $lang_nextpage): string {
    if (empty($thread['id'])) {
        return '';
    }
    $postCountResult = $db->fetchOne(
        'SELECT COUNT(*) as count FROM ppb_posts WHERE threadid = ? OR id = ?',
        [$thread['id'], $thread['id']]
    );
    $cnum = (int) ($postCountResult['count'] ?? 0);
    $pages = getpages((int) $thread['id'], $db, $current);

    $prevHtml = '';
    $nextHtml = '';
    if ($cnum > $current2) {
        if ($current >= 25) {
            $prevHtml = '<a class="btn btn-outline-secondary btn-sm" href="showthread.php?threadid='
                . (int) $thread['id'] . '&current=' . $current3 . '">'
                . '<i class="bi bi-chevron-left" aria-hidden="true"></i> '
                . htmlspecialchars($lang_prevpage ?? 'Previous', ENT_QUOTES, 'UTF-8') . '</a>';
        }
        $nextHtml = '<a class="btn btn-outline-secondary btn-sm" href="showthread.php?threadid='
            . (int) $thread['id'] . '&current=' . $current2 . '">'
            . htmlspecialchars($lang_nextpage ?? 'Next', ENT_QUOTES, 'UTF-8')
            . ' <i class="bi bi-chevron-right" aria-hidden="true"></i></a>';
    } elseif ($cnum <= $current2 && $current > 1) {
        $prevHtml = '<a class="btn btn-outline-secondary btn-sm" href="showthread.php?threadid='
            . (int) $thread['id'] . '&current=' . $current3 . '">'
            . '<i class="bi bi-chevron-left" aria-hidden="true"></i> '
            . htmlspecialchars($lang_prevpage ?? 'Previous', ENT_QUOTES, 'UTF-8') . '</a>';
    }

    $out = '<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 my-3">';
    $out .= '<div class="d-flex align-items-center gap-2 flex-wrap">';
    if ($pages !== '') {
        $out .= '<span class="small text-body-secondary">' . htmlspecialchars($lang_pages ?? 'Pages', ENT_QUOTES, 'UTF-8') . ':</span>';
        $out .= $pages;
    }
    $out .= '</div>';
    $out .= '<div class="btn-group" role="group" aria-label="Seitennavigation">';
    $out .= $prevHtml . $nextHtml;
    $out .= '</div></div>';
    return $out;
};
?>

<?php if (($board['status'] ?? '') === 'Private' && !$hasAccess): ?>
  <section class="card shadow-sm mb-4 border-warning">
    <header class="card-header bg-warning-subtle">
      <h2 class="h6 mb-0">
        <i class="bi bi-shield-lock-fill" aria-hidden="true"></i>
        <?php echo $lang_threadrequirespwd ?? 'This thread requires a board password'; ?>
      </h2>
    </header>
    <div class="card-body">
      <form action="showthread.php?threadid=<?php echo (int) $thread['id']; ?>"
            method="post" class="needs-validation row g-2" novalidate>
        <?php echo CSRF::getTokenField(); ?>
        <div class="col-sm-8">
          <label for="boardpassword" class="form-label fw-semibold">
            <?php echo $lang_password ?? 'Password'; ?>
          </label>
          <input id="boardpassword" name="boardpassword" type="password"
                 class="form-control" maxlength="25" required autocomplete="off">
        </div>
        <div class="col-sm-4 d-flex align-items-end">
          <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-unlock" aria-hidden="true"></i> Zugang anfordern
          </button>
        </div>
        <div class="col-12">
          <div class="form-text mt-0">Bitte gib das Forum-Passwort ein, um diesen Thread zu lesen.</div>
        </div>
      </form>
    </div>
  </section>
<?php else: ?>

  <h2 class="h5 text-body-secondary mb-3">
    <i class="bi bi-card-text" aria-hidden="true"></i>
    <?php echo Security::escape($thread['title'] ?? ''); ?>
  </h2>

  <?php echo $renderPagination(); ?>

  <?php
  $posts = [];
  if (!empty($thread['id'])) {
      $posts = $db->fetchAll(
          'SELECT * FROM ppb_posts WHERE threadid = ? OR id = ? ORDER BY id LIMIT ?, 25',
          [$thread['id'], $thread['id'], $current]
      );
  }
  ?>

  <?php if (empty($thread['id'])): ?>
    <?php echo ppb_alert($lang_nothreadwithid ?? 'No thread with this ID', 'warning'); ?>
  <?php elseif (count($posts) === 0): ?>
    <?php echo ppb_alert($lang_nopostsinthread ?? 'No posts in this thread', 'info'); ?>
  <?php else: ?>
    <?php foreach ($posts as $row):
        $author = $db->fetchOne('SELECT * FROM ppb_users WHERE id = ?', [$row['author']]);
        $authorName = $author !== null
            ? (string) $author['username']
            : ($lang_anonymous ?? 'Anonymous');
        $rank = '';
        $rankClass = 'text-body-secondary';
        if ($author !== null) {
            if ($author['status'] === 'Deactivated') {
                $rank = $lang_deactivated ?? 'Deactivated';
                $rankClass = 'text-warning-emphasis';
            } elseif ($author['status'] === 'Normal user') {
                $rank = getrank((int) $author['id'], $db);
            } elseif ($author['status'] === 'Administrator') {
                $rank = 'Administrator';
                $rankClass = 'text-danger-emphasis fw-semibold';
            }
        }
        $postDate = date('d.m.Y - H:i', (int) $row['time']);
        $authorPostCount = 0;
        if ($author !== null) {
            $authorPostCount = (int) ($db->fetchOne(
                'SELECT COUNT(*) as count FROM ppb_posts WHERE author = ?',
                [$author['id']]
            )['count'] ?? 0);
        }

        $rawText = (string) $row['text'];
        $renderedText = TextFormatter::formatPost(
            $rawText,
            $settings['bbcode'] ?? 'ON',
            $settings['smilies'] ?? 'ON',
            $settings['htmlcode'] ?? 'OFF'
        );
    ?>
      <article class="card shadow-sm mb-3" id="post<?php echo (int) $row['id']; ?>">
        <div class="row g-0">
          <aside class="col-md-3 col-lg-2 bg-body-tertiary border-end p-3">
            <div class="d-flex align-items-center gap-2 mb-2">
              <i class="bi bi-person-circle fs-3 text-secondary" aria-hidden="true"></i>
              <div class="fw-semibold">
                <?php if ($author !== null): ?>
                  <a class="link-dark text-decoration-none" href="showprofile.php?userid=<?php echo (int) $author['id']; ?>&catid=<?php echo (int) ($catid ?? 0); ?>&boardid=<?php echo (int) $boardid; ?>">
                    <?php echo Security::escape($authorName); ?>
                  </a>
                <?php else: ?>
                  <?php echo Security::escape($authorName); ?>
                <?php endif; ?>
              </div>
            </div>
            <?php if ($rank !== ''): ?>
              <div class="small <?php echo $rankClass; ?> mb-2">
                <?php echo Security::escape($rank); ?>
              </div>
            <?php endif; ?>
            <?php if ($author !== null): ?>
              <ul class="list-unstyled small text-body-secondary mb-0">
                <li>
                  <span class="fw-semibold"><?php echo $lang_registeredsince ?? 'Registriert:'; ?></span>
                  <?php echo Security::escape(date('d.m.Y', (int) $author['registered'])); ?>
                </li>
                <li>
                  <span class="fw-semibold">Beiträge:</span>
                  <?php echo $authorPostCount; ?>
                </li>
              </ul>
            <?php endif; ?>
          </aside>

          <div class="col-md-9 col-lg-10">
            <header class="card-header bg-light d-flex flex-wrap align-items-center justify-content-between gap-2 py-2">
              <div class="small text-body-secondary">
                <i class="bi bi-clock" aria-hidden="true"></i>
                <?php echo $lang_postedon ?? 'Posted on'; ?>
                <?php echo Security::escape($postDate); ?>
              </div>
              <div class="btn-group btn-group-sm" role="group" aria-label="Beitragsaktionen">
                <?php if ($author !== null): ?>
                  <a class="btn btn-outline-secondary"
                     href="showprofile.php?userid=<?php echo (int) $author['id']; ?>&catid=<?php echo (int) ($catid ?? 0); ?>&boardid=<?php echo (int) $boardid; ?>"
                     title="<?php echo $lang_profile ?? 'Profile'; ?>">
                    <i class="bi bi-person" aria-hidden="true"></i>
                  </a>
                  <?php if (($author['hideemail'] ?? 'YES') === 'NO'): ?>
                    <a class="btn btn-outline-secondary"
                       href="sendmail.php?userid=<?php echo (int) $author['id']; ?>&catid=<?php echo (int) ($catid ?? 0); ?>&boardid=<?php echo (int) $boardid; ?>"
                       title="<?php echo $lang_writemail ?? 'Write mail to'; ?> <?php echo Security::escape($authorName); ?>">
                      <i class="bi bi-envelope" aria-hidden="true"></i>
                    </a>
                  <?php endif; ?>
                  <?php if (!empty($author['homepage']) && $author['homepage'] !== 'http://'): ?>
                    <a class="btn btn-outline-secondary"
                       href="<?php echo Security::escape((string) $author['homepage']); ?>"
                       target="_blank" rel="noopener noreferrer"
                       title="<?php echo $lang_homepage ?? 'Homepage'; ?>">
                      <i class="bi bi-globe" aria-hidden="true"></i>
                    </a>
                  <?php endif; ?>
                <?php endif; ?>
                <a class="btn btn-outline-secondary"
                   href="editpost.php?postid=<?php echo (int) $row['id']; ?>&catid=<?php echo (int) ($catid ?? 0); ?>&boardid=<?php echo (int) $boardid; ?>"
                   title="<?php echo $lang_editpost ?? 'Edit post'; ?>">
                  <i class="bi bi-pencil" aria-hidden="true"></i>
                </a>
                <a class="btn btn-outline-secondary"
                   href="newpost.php?threadid=<?php echo (int) $thread['id']; ?>&postid=<?php echo (int) $row['id']; ?>"
                   title="<?php echo $lang_writequotedanswer ?? 'Quote reply'; ?>">
                  <i class="bi bi-chat-quote" aria-hidden="true"></i>
                </a>
              </div>
            </header>

            <div class="card-body">
              <div class="post-content">
                <?php echo $renderedText; ?>
              </div>
              <?php if ($author !== null && !empty($author['signature'])):
                  // Signaturen immer mit htmlcode=OFF rendern (BUG-010), Stored-XSS-Schutz.
                  $signature = TextFormatter::formatPost(
                      (string) $author['signature'],
                      $settings['bbcode'] ?? 'ON',
                      $settings['smilies'] ?? 'ON',
                      'OFF'
                  );
              ?>
                <hr class="text-body-secondary mt-4">
                <div class="post-signature small text-body-secondary">
                  <?php echo $signature; ?>
                </div>
              <?php endif; ?>
            </div>

            <footer class="card-footer bg-body-tertiary text-end small">
              <span class="text-body-secondary">IP:</span>
              <a class="text-decoration-none"
                 href="showip.php?threadid=<?php echo (int) $thread['id']; ?>&postid=<?php echo (int) $row['id']; ?>">
                <?php echo $lang_logged ?? 'logged'; ?>
              </a>
            </footer>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php
  if (!empty($thread['id'])) {
      $threadViews = (int) ($thread['views'] ?? 0) + 1;
      $db->query('UPDATE ppb_posts SET views = ? WHERE id = ?', [$threadViews, $threadid]);
  }

  if ($loggedin === 'YES' && !empty($thread['title'])) {
      $now = time();
      $existingVisit = $db->fetchOne(
          "SELECT id FROM ppb_visits WHERE userid = ? AND vid = ? AND type = 'Thread'",
          [$ppbuser['id'], $threadid]
      );

      if ($existingVisit !== null) {
          $db->query('UPDATE ppb_visits SET time = ? WHERE id = ?', [$now, $existingVisit['id']]);
      } else {
          $db->query(
              "INSERT INTO ppb_visits (userid, vid, time, type) VALUES (?, ?, ?, 'Thread')",
              [$ppbuser['id'], $thread['id'], $now]
          );
      }
  }
  ?>

  <?php echo $renderPagination(); ?>

  <?php if (!empty($board['title'])): ?>
    <div class="d-flex flex-wrap justify-content-end gap-2 mb-4">
      <?php if (($board['status'] ?? '') === 'Closed'): ?>
        <span class="badge text-bg-secondary"><?php echo $lang_boardclosed ?? 'Board closed'; ?></span>
      <?php else: ?>
        <a href="newthread.php?boardid=<?php echo (int) $board['id']; ?>" class="btn btn-primary">
          <i class="bi bi-plus-circle" aria-hidden="true"></i>
          <?php echo $lang_newthread ?? 'New Thread'; ?>
        </a>
        <?php if (!empty($thread['title'])): ?>
          <?php if (($thread['status'] ?? '') !== 'Closed'): ?>
            <a href="newpost.php?threadid=<?php echo (int) $thread['id']; ?>&current=<?php echo (int) $current; ?>"
               class="btn btn-success">
              <i class="bi bi-reply" aria-hidden="true"></i>
              <?php echo $lang_newpost ?? 'New Post'; ?>
            </a>
          <?php else: ?>
            <span class="badge text-bg-secondary"><?php echo $lang_threadclosed ?? 'Thread closed'; ?></span>
          <?php endif; ?>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  <?php endif; ?>

<?php endif; ?>

<?php include __DIR__ . '/footer.inc.php'; ?>
