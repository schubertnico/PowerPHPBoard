<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Main Header Include
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

use PowerPHPBoard\Database;
use PowerPHPBoard\Security;
use PowerPHPBoard\Session;

// Load configuration and core classes
require_once __DIR__ . '/config.inc.php';

// Start secure session
Session::start();

// Initialize variables
$settings = [];
$ppbuser = [];
$bcat = [];
$board = [];
$thread = [];
$loggedin = 'NO';

// Get URL parameters safely
$catid = Security::getInt('catid');
$threadid = Security::getInt('threadid');
$boardid = Security::getInt('boardid');
$postid = Security::getInt('postid');
$current = Security::getInt('current');

// Connect to database
try {
    $db = Database::getInstance($mysql);
} catch (PDOException $e) {
    echo '<!doctype html><html lang="de"><head><meta charset="UTF-8"><title>Database error</title>'
        . '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"></head>'
        . '<body class="bg-body-tertiary"><div class="container py-5"><div class="alert alert-danger" role="alert">'
        . '<h1 class="h4 alert-heading">Datenbankfehler</h1>'
        . '<p class="mb-0">Es konnte keine Verbindung zum Datenbankserver hergestellt werden.</p>'
        . '</div></div></body></html>';
    exit;
}

// Load global settings
$settings = $db->fetchOne('SELECT * FROM ppb_config WHERE id = ?', [1]);
if ($settings === null) {
    $settings = [];
}

// Load language file
$langFile = match ($settings['language'] ?? 'English') {
    'Deutsch-Sie' => 'deutsch-sie.inc.php',
    'Deutsch-Du' => 'deutsch-du.inc.php',
    default => 'english.inc.php',
};
require_once __DIR__ . '/' . $langFile;

// Load category settings if specified
if ($catid > 0) {
    $catSettings = $db->fetchOne(
        "SELECT header, footer, bordercolor, tablebg1, tablebg2, tablebg3, newthread, newpost
         FROM ppb_boards WHERE id = ? AND type = 'Boardcategory'",
        [$catid]
    );
    if ($catSettings !== null) {
        $settings = array_merge($settings, $catSettings);
    }
}

// Load thread if specified
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

// Load board if specified
if ($boardid > 0) {
    $board = $db->fetchOne(
        "SELECT * FROM ppb_boards WHERE id = ? AND type = 'Board'",
        [$boardid]
    );
    if ($board !== null) {
        $catid = (int) $board['catid'];
        foreach (['header', 'footer', 'bordercolor', 'tablebg1', 'tablebg2', 'tablebg3', 'newthread', 'newpost'] as $key) {
            if (!empty($board[$key])) {
                $settings[$key] = $board[$key];
            }
        }
    } else {
        $board = [];
    }
}

// Load board category title
if ($catid > 0) {
    $bcat = $db->fetchOne(
        "SELECT id, title FROM ppb_boards WHERE id = ? AND type = 'Boardcategory'",
        [$catid]
    );
    if ($bcat === null) {
        $bcat = [];
    }
}

// Check user authentication via session
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

// Include functions
require_once __DIR__ . '/functions.inc.php';

// Build navigation query string for shared links
$navQuery = http_build_query(['catid' => $catid, 'boardid' => $boardid]);

// Page title
$pageTitle = $settings['boardtitle'] ?? 'PowerPHPBoard';
$pageSubtitle = '';
if (!empty($board['title'])) {
    $pageSubtitle = (string) $board['title'];
} elseif (!empty($bcat['title'])) {
    $pageSubtitle = (string) $bcat['title'];
}

// Include header template (HTML5 doctype, head, opening body)
$headerFile = $settings['header'] ?? '';
if ($headerFile !== '' && file_exists(__DIR__ . '/inc/' . $headerFile)) {
    include __DIR__ . '/inc/' . $headerFile;
} else {
    include __DIR__ . '/inc/header.ppb';
}
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark" aria-label="Hauptnavigation">
  <div class="container-xl">
    <a class="navbar-brand fw-semibold" href="index.php">
      <i class="bi bi-chat-square-text-fill" aria-hidden="true"></i>
      <?php echo Security::escape($settings['boardtitle'] ?? 'PowerPHPBoard'); ?>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
            data-bs-target="#ppbNav" aria-controls="ppbNav" aria-expanded="false"
            aria-label="Navigation umschalten">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="ppbNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item">
          <a class="nav-link" href="index.php"><i class="bi bi-house-door" aria-hidden="true"></i> Home</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="statistics.php?<?php echo Security::escape($navQuery); ?>">
            <i class="bi bi-bar-chart" aria-hidden="true"></i>
            <?php echo $lang_statistics ?? 'Statistics'; ?>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="bbcode.php"><i class="bi bi-code-slash" aria-hidden="true"></i> BBCode</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="smilies.php"><i class="bi bi-emoji-smile" aria-hidden="true"></i> Smilies</a>
        </li>
      </ul>
      <ul class="navbar-nav align-items-lg-center">
        <?php if ($loggedin === 'YES'): ?>
          <li class="nav-item nav-link mb-0">
            <i class="bi bi-person-check" aria-hidden="true"></i>
            <?php echo $lang_loggedinas ?? 'Logged in as'; ?>
            <strong><?php echo Security::escape($ppbuser['username'] ?? ''); ?></strong>
          </li>
          <?php if (($ppbuser['status'] ?? '') === 'Administrator'): ?>
            <li class="nav-item">
              <a class="nav-link link-warning" href="admin/" title="Adminbereich">
                <i class="bi bi-shield-lock-fill" aria-hidden="true"></i>
                Adminbereich
              </a>
            </li>
          <?php endif; ?>
          <li class="nav-item">
            <a class="nav-link" href="profile.php?<?php echo Security::escape($navQuery); ?>">
              <i class="bi bi-person-gear" aria-hidden="true"></i>
              <?php echo $lang_profile ?? 'Profile'; ?>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="logout.php?<?php echo Security::escape($navQuery); ?>">
              <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
              <?php echo $lang_logout ?? 'Logout'; ?>
            </a>
          </li>
        <?php else: ?>
          <li class="nav-item">
            <a class="nav-link" href="login.php?<?php echo Security::escape($navQuery); ?>">
              <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>
              <?php echo $lang_login ?? 'Login'; ?>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="register.php?<?php echo Security::escape($navQuery); ?>">
              <i class="bi bi-person-plus" aria-hidden="true"></i>
              <?php echo $lang_register ?? 'Register'; ?>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="profile.php?<?php echo Security::escape($navQuery); ?>">
              <i class="bi bi-person" aria-hidden="true"></i>
              <?php echo $lang_profile ?? 'Profile'; ?>
            </a>
          </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<main class="container-xl py-4 flex-grow-1" role="main">

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="index.php"><?php echo Security::escape($settings['boardtitle'] ?? 'PowerPHPBoard'); ?></a></li>
<?php
if (!empty($bcat['title'])):
    $catLink = 'index.php?catid=' . (int) ($bcat['id'] ?? $catid);
    $isLast = empty($board['title']) && empty($thread['title']);
?>
    <li class="breadcrumb-item<?php echo $isLast ? ' active' : ''; ?>"<?php echo $isLast ? ' aria-current="page"' : ''; ?>>
      <?php if ($isLast): ?>
        <?php echo Security::escape((string) $bcat['title']); ?>
      <?php else: ?>
        <a href="<?php echo Security::escape($catLink); ?>"><?php echo Security::escape((string) $bcat['title']); ?></a>
      <?php endif; ?>
    </li>
<?php endif; ?>
<?php
if (!empty($board['title'])):
    $boardLink = 'showboard.php?boardid=' . (int) $board['id'];
    $isLast = empty($thread['title']);
?>
    <li class="breadcrumb-item<?php echo $isLast ? ' active' : ''; ?>"<?php echo $isLast ? ' aria-current="page"' : ''; ?>>
      <?php if ($isLast): ?>
        <?php echo Security::escape((string) $board['title']); ?>
      <?php else: ?>
        <a href="<?php echo Security::escape($boardLink); ?>"><?php echo Security::escape((string) $board['title']); ?></a>
      <?php endif; ?>
    </li>
<?php endif; ?>
<?php if (!empty($thread['title'])): ?>
    <li class="breadcrumb-item active" aria-current="page">
      <?php echo Security::escape((string) $thread['title']); ?>
    </li>
<?php endif; ?>
  </ol>
</nav>

<?php if (!empty($board['title'])): ?>
<header class="mb-3">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div>
      <h2 class="h3 mb-2"><?php echo Security::escape((string) $board['title']); ?></h2>
      <?php if (!empty($board['mods'])): ?>
        <p class="text-body-secondary small mb-0">
          <?php echo $lang_moderatedby ?? 'Moderated by'; ?>:
          <?php
          $mods = explode(',', (string) $board['mods']);
          $modLinks = [];
          foreach ($mods as $modEmail) {
              $modEmail = trim($modEmail);
              if ($modEmail === '') {
                  continue;
              }
              $mod = $db->fetchOne('SELECT id, username FROM ppb_users WHERE email = ?', [$modEmail]);
              if ($mod !== null) {
                  $modLinks[] = '<a href="showprofile.php?userid=' . (int) $mod['id']
                      . '&catid=' . (int) $catid . '&boardid=' . (int) $boardid . '">'
                      . Security::escape((string) $mod['username']) . '</a>';
              }
          }
          echo implode(', ', $modLinks);
          ?>
        </p>
      <?php endif; ?>
    </div>
    <div class="d-flex gap-2 flex-wrap" role="group" aria-label="Aktionen">
<?php
    if (($board['status'] ?? '') === 'Closed') {
        echo '<span class="badge text-bg-secondary align-self-center">'
            . ($lang_boardclosed ?? 'Board closed') . '</span>';
    } else {
        echo '<a class="btn btn-primary btn-sm" href="newthread.php?boardid='
            . (int) $board['id'] . '"><i class="bi bi-plus-circle" aria-hidden="true"></i> '
            . ($lang_newthread ?? 'New Thread') . '</a>';
        if (!empty($thread['title'])) {
            if (($thread['status'] ?? '') !== 'Closed') {
                echo '<a class="btn btn-success btn-sm" href="newpost.php?threadid='
                    . (int) $thread['id'] . '&current=' . (int) $current
                    . '"><i class="bi bi-reply" aria-hidden="true"></i> '
                    . ($lang_newpost ?? 'New Post') . '</a>';
            } else {
                echo '<span class="badge text-bg-secondary align-self-center">'
                    . ($lang_threadclosed ?? 'Thread closed') . '</span>';
            }
        }
    }
?>
    </div>
  </div>
</header>
<?php endif; ?>
