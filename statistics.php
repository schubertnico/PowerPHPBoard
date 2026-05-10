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
?>

<section class="card shadow-sm mb-4">
  <header class="card-header bg-secondary-subtle d-flex align-items-center gap-2">
    <i class="bi bi-bar-chart-fill" aria-hidden="true"></i>
    <h1 class="h5 mb-0"><?php echo $lang_statistics ?? 'Statistics'; ?></h1>
  </header>
  <div class="card-body">
    <div class="row g-3 row-cols-1 row-cols-md-3">
      <div class="col">
        <div class="border rounded p-3 h-100 d-flex flex-column">
          <div class="text-body-secondary small">
            <i class="bi bi-people" aria-hidden="true"></i>
            <?php echo $lang_numregistered ?? 'Registered users'; ?>
          </div>
          <div class="display-6 mt-1"><?php echo $allusers; ?></div>
        </div>
      </div>
      <div class="col">
        <div class="border rounded p-3 h-100 d-flex flex-column">
          <div class="text-body-secondary small">
            <i class="bi bi-card-list" aria-hidden="true"></i>
            <?php echo $lang_numthreads ?? 'Threads'; ?>
          </div>
          <div class="display-6 mt-1"><?php echo $allthreads; ?></div>
        </div>
      </div>
      <div class="col">
        <div class="border rounded p-3 h-100 d-flex flex-column">
          <div class="text-body-secondary small">
            <i class="bi bi-chat-square-text" aria-hidden="true"></i>
            <?php echo $lang_numposts ?? 'Posts'; ?>
          </div>
          <div class="display-6 mt-1"><?php echo $allpostings; ?></div>
        </div>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/footer.inc.php'; ?>
