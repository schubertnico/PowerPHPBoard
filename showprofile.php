<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Show User Profile
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

use PowerPHPBoard\Security;
use PowerPHPBoard\TextFormatter;

include __DIR__ . '/header.inc.php';

$userid = Security::getInt('userid');
$user = null;
if ($userid > 0) {
    $user = $db->fetchOne('SELECT * FROM ppb_users WHERE id = ?', [$userid]);
}
?>

<div class="row justify-content-center">
  <div class="col-lg-8">

  <?php if ($userid === 0 || $user === null): ?>
    <?php
    $msg = $userid === 0
        ? ($lang_chooseuser ?? 'Please choose a user')
        : ($lang_nouserwithid ?? 'No user with this ID');
      default_error($msg, 'index.php', $lang_boardlist ?? 'Board list');
      ?>
  <?php else:
      $rank = match ($user['status']) {
          'Administrator' => $lang_administrator ?? 'Administrator',
          'Deactivated' => $lang_deactivated ?? 'Deactivated',
          default => getrank((int) $user['id'], $db),
      };
      ?>
    <section class="card shadow-sm mb-4">
      <header class="card-header bg-secondary-subtle d-flex align-items-center gap-2">
        <i class="bi bi-person-circle fs-4" aria-hidden="true"></i>
        <h1 class="h5 mb-0"><?php echo Security::escape($lang_showuserprof ?? 'User Profile'); ?></h1>
      </header>
      <div class="card-body">
        <dl class="row mb-0">
          <dt class="col-sm-4 col-md-3"><?php echo Security::escape($lang_username ?? 'Username'); ?></dt>
          <dd class="col-sm-8 col-md-9 fw-semibold">
            <?php echo Security::escape((string) $user['username']); ?>
            <?php if ($user['status'] === 'Administrator'): ?>
              <span class="badge text-bg-danger ms-1"><?php echo Security::escape($lang_administrator ?? 'Administrator'); ?></span>
            <?php elseif ($user['status'] === 'Deactivated'): ?>
              <span class="badge text-bg-secondary ms-1"><?php echo Security::escape($lang_deactivated ?? 'Deactivated'); ?></span>
            <?php endif; ?>
          </dd>

          <dt class="col-sm-4 col-md-3"><?php echo Security::escape($lang_email ?? 'Email'); ?></dt>
          <dd class="col-sm-8 col-md-9">
            <?php echo ppb_profile_email($user, (int) $catid, (int) $boardid); ?>
          </dd>

          <dt class="col-sm-4 col-md-3"><?php echo Security::escape($lang_icq ?? 'ICQ'); ?></dt>
          <dd class="col-sm-8 col-md-9">
            <?php if (!empty($user['icq'])): ?>
              <?php echo Security::escape((string) $user['icq']); ?>
            <?php else: ?>
              <span class="text-body-secondary"><?php echo Security::escape($lang_notspecified ?? 'Not specified'); ?></span>
            <?php endif; ?>
          </dd>

          <dt class="col-sm-4 col-md-3"><?php echo Security::escape($lang_homepage ?? 'Homepage'); ?></dt>
          <dd class="col-sm-8 col-md-9">
            <?php $homepageUrl = TextFormatter::sanitizeUrl((string) ($user['homepage'] ?? '')); ?>
            <?php if ($homepageUrl !== null): ?>
              <a class="text-decoration-none" href="<?php echo Security::escape($homepageUrl); ?>"
                 target="_blank" rel="noopener noreferrer">
                <i class="bi bi-globe" aria-hidden="true"></i>
                <?php echo Security::escape((string) $user['homepage']); ?>
              </a>
            <?php else: ?>
              <span class="text-body-secondary"><?php echo Security::escape($lang_notspecified ?? 'Not specified'); ?></span>
            <?php endif; ?>
          </dd>

          <dt class="col-sm-4 col-md-3"><?php echo Security::escape($lang_biography ?? 'Biography'); ?></dt>
          <dd class="col-sm-8 col-md-9">
            <?php if (!empty($user['biography'])): ?>
              <?php echo nl2br(Security::escape((string) $user['biography'])); ?>
            <?php else: ?>
              <span class="text-body-secondary"><?php echo Security::escape($lang_notspecified ?? 'Not specified'); ?></span>
            <?php endif; ?>
          </dd>

          <dt class="col-sm-4 col-md-3"><?php echo Security::escape($lang_rank ?? 'Rank'); ?></dt>
          <dd class="col-sm-8 col-md-9"><?php echo Security::escape((string) $rank); ?></dd>
        </dl>
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
