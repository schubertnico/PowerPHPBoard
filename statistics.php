<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Statistics
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

use PowerPHPBoard\Security;

include __DIR__ . '/header.inc.php';

$allusers = (int) ($db->fetchOne('SELECT COUNT(*) as count FROM ppb_users')['count'] ?? 0);
$allthreads = (int) ($db->fetchOne("SELECT COUNT(*) as count FROM ppb_posts WHERE type = 'Thread'")['count'] ?? 0);
$allpostings = (int) ($db->fetchOne('SELECT COUNT(*) as count FROM ppb_posts')['count'] ?? 0);

$tiles = [
    ['bi-people', $lang_numregistered ?? 'Registered users', $lang_numregistereddesc ?? 'Number of all registered users', $allusers],
    ['bi-card-list', $lang_numthreads ?? 'Threads', $lang_numthreadsdesc ?? 'Number of all threads', $allthreads],
    ['bi-chat-square-text', $lang_numposts ?? 'Posts', $lang_numpostsdesc ?? 'Number of all posts including thread starts', $allpostings],
];
?>

<section class="card shadow-sm mb-4">
  <header class="card-header bg-secondary-subtle d-flex align-items-center gap-2">
    <i class="bi bi-bar-chart-fill" aria-hidden="true"></i>
    <h1 class="h5 mb-0"><?php echo Security::escape($lang_statistics ?? 'Statistics'); ?></h1>
  </header>
  <div class="card-body">
    <div class="row g-3 row-cols-1 row-cols-md-3">
      <?php foreach ($tiles as [$icon, $label, $description, $value]): ?>
        <div class="col">
          <div class="border rounded p-3 h-100 d-flex flex-column">
            <div class="fw-semibold">
              <i class="bi <?php echo $icon; ?>" aria-hidden="true"></i>
              <?php echo Security::escape($label); ?>
            </div>
            <div class="small text-body-secondary"><?php echo Security::escape($description); ?></div>
            <div class="display-6 mt-1"><?php echo $value; ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php include __DIR__ . '/footer.inc.php'; ?>
