<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Admin Index
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

use PowerPHPBoard\Security;

include __DIR__ . '/header.inc.php';

$userCount = (int) ($db->fetchOne('SELECT COUNT(*) c FROM ppb_users')['c'] ?? 0);
$boardCount = (int) ($db->fetchOne("SELECT COUNT(*) c FROM ppb_boards WHERE type = 'Board'")['c'] ?? 0);
$threadCount = (int) ($db->fetchOne("SELECT COUNT(*) c FROM ppb_posts WHERE type = 'Thread'")['c'] ?? 0);
$postCount = (int) ($db->fetchOne('SELECT COUNT(*) c FROM ppb_posts')['c'] ?? 0);

$tiles = [
    ['bi-people', $lang_adm_users ?? 'Users', $userCount],
    ['bi-folder2-open', $lang_adm_boards ?? 'Boards', $boardCount],
    ['bi-card-list', $lang_threads ?? 'Threads', $threadCount],
    ['bi-chat-square-text', $lang_postings ?? 'Posts', $postCount],
];

$areas = [
    ['bi-sliders', $lang_adm_generalsettings ?? 'General settings', $lang_adm_generaldesc ?? 'Board name, address, administrator email, language, default design and features.', 'general.php', 'bi-gear', $lang_adm_configure ?? 'Configure'],
    ['bi-folder2-open', $lang_adm_boards ?? 'Boards', $lang_adm_boardsdesc ?? 'Create, edit, close or delete categories and boards.', 'boards.php', 'bi-list-task', $lang_adm_manage ?? 'Manage'],
    ['bi-people', $lang_adm_usermanagement ?? 'User management', $lang_adm_usersdesc ?? 'Create and edit users, grant administrator rights or deactivate accounts.', 'user.php', 'bi-person-gear', $lang_adm_manage ?? 'Manage'],
];
?>

<header class="mb-3">
  <h1 class="h3 mb-1"><i class="bi bi-shield-lock-fill" aria-hidden="true"></i> <?php echo Security::escape($lang_adm_title ?? 'Administration'); ?></h1>
  <p class="text-body-secondary mb-0">
    <?php echo sprintf(
        Security::escape($lang_adm_welcome ?? 'Welcome, %s. Please choose an area.'),
        '<strong>' . Security::escape((string) $ppbuser['username']) . '</strong>'
    ); ?>
  </p>
</header>

<div class="row g-3 row-cols-1 row-cols-md-2 row-cols-xl-4 mb-4">
  <?php foreach ($tiles as [$icon, $label, $count]): ?>
    <div class="col">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="text-body-secondary small"><i class="bi <?php echo $icon; ?>" aria-hidden="true"></i> <?php echo Security::escape($label); ?></div>
          <div class="display-6"><?php echo $count; ?></div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="row g-3 row-cols-1 row-cols-md-3">
  <?php foreach ($areas as [$icon, $title, $description, $href, $buttonIcon, $buttonText]): ?>
    <div class="col">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h2 class="h5 card-title">
            <i class="bi <?php echo $icon; ?>" aria-hidden="true"></i>
            <?php echo Security::escape($title); ?>
          </h2>
          <p class="card-text text-body-secondary small"><?php echo Security::escape($description); ?></p>
        </div>
        <div class="card-footer bg-light">
          <a class="btn btn-primary btn-sm" href="<?php echo $href; ?>">
            <i class="bi <?php echo $buttonIcon; ?>" aria-hidden="true"></i> <?php echo Security::escape($buttonText); ?>
          </a>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php include __DIR__ . '/footer.inc.php'; ?>
