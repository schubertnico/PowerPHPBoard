<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Board Design Administration
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

use PowerPHPBoard\CSRF;
use PowerPHPBoard\Security;

include __DIR__ . '/header.inc.php';

$boarddesign = Security::getInt('boarddesign', 'GET', 0);
$applied = false;

// Übernehmen ändert Daten und geht deshalb nur per POST mit CSRF-Token
if ($boarddesign === 1 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::validateOrDie();
    CSRF::regenerate();
    if ($catid > 0) {
        $category = $db->fetchOne(
            'SELECT * FROM ppb_boards WHERE id = ? AND type = ?',
            [$catid, 'Boardcategory']
        );
        if ($category !== null) {
            $db->execute(
                'UPDATE ppb_boards SET header = ?, footer = ?, bordercolor = ?, tablebg1 = ?, tablebg2 = ?, tablebg3 = ?, newthread = ?, newpost = ? WHERE catid = ?',
                [
                    $category['header'], $category['footer'], $category['bordercolor'],
                    $category['tablebg1'], $category['tablebg2'], $category['tablebg3'],
                    $category['newthread'], $category['newpost'], $catid,
                ]
            );
            $applied = true;
        }
    } else {
        $db->execute(
            'UPDATE ppb_boards SET header = ?, footer = ?, bordercolor = ?, tablebg1 = ?, tablebg2 = ?, tablebg3 = ?, newthread = ?, newpost = ?',
            [
                $settings['header'] ?? '',
                $settings['footer'] ?? '',
                $settings['bordercolor'] ?? '#000000',
                $settings['tablebg1'] ?? '#ffffff',
                $settings['tablebg2'] ?? '#eeeeee',
                $settings['tablebg3'] ?? '#cccccc',
                $settings['newthread'] ?? 'images/newthread.gif',
                $settings['newpost'] ?? 'images/newpost.gif',
            ]
        );
        $applied = true;
    }
}

if ($catid > 0) {
    $confirmTitle = $lang_adm_applycategorydesign_title ?? 'Apply the category design to all boards of this category';
    $confirmText = $lang_adm_applycategorydesign_text ?? 'Do you really want all boards of this category to take over the design of the category?';
    $confirmHref = 'boarddesign.php?boarddesign=1&catid=' . $catid;
} else {
    $confirmTitle = $lang_adm_applydefaultdesign_title ?? 'Apply the default design to all boards';
    $confirmText = $lang_adm_applydefaultdesign_text ?? 'Do you really want all boards and categories to take over the default design?';
    $confirmHref = 'boarddesign.php?boarddesign=1';
}
?>

<header class="mb-3">
  <h1 class="h3 mb-0"><i class="bi bi-palette" aria-hidden="true"></i> <?php echo Security::escape($lang_adm_boarddesign ?? 'Board design'); ?></h1>
</header>

<?php if ($applied): ?>
  <div class="alert alert-success" role="alert">
    <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
    <?php echo Security::escape($lang_adm_designapplied ?? 'The design has been applied.'); ?>
    <a class="alert-link" href="boards.php"><?php echo Security::escape($lang_adm_backtoboards ?? 'Back to board management'); ?></a>
  </div>
<?php else: ?>
  <section class="card shadow-sm border-warning mb-4">
    <header class="card-header bg-warning-subtle">
      <h2 class="h6 mb-0">
        <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
        <?php echo Security::escape($confirmTitle); ?>
      </h2>
    </header>
    <div class="card-body">
      <p class="mb-3"><?php echo Security::escape($confirmText); ?></p>
      <p class="small text-body-secondary mb-3"><?php echo Security::escape($lang_adm_designnote_edit ?? 'The Bootstrap 5 layout no longer uses these fields. They are optional and kept for compatibility; changes here have no visible effect in the board.'); ?></p>
      <div class="d-flex flex-wrap gap-2">
        <form action="<?php echo Security::escape($confirmHref); ?>" method="post" class="d-inline">
          <?php echo CSRF::getTokenField(); ?>
          <button type="submit" class="btn btn-warning">
            <i class="bi bi-check-lg" aria-hidden="true"></i> <?php echo Security::escape($lang_adm_yesapply ?? 'Yes, apply'); ?>
          </button>
        </form>
        <a href="boards.php" class="btn btn-outline-secondary">
          <i class="bi bi-x-lg" aria-hidden="true"></i> <?php echo Security::escape($lang_adm_cancel ?? 'Cancel'); ?>
        </a>
      </div>
    </div>
  </section>
<?php endif; ?>

<?php include __DIR__ . '/footer.inc.php'; ?>
