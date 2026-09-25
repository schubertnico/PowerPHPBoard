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
$confirmText = '';
$confirmTitle = '';
$confirmHref = '';

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
} else {
    if ($catid > 0) {
        $confirmTitle = 'Alle Boards dieser Kategorie auf Kategorie-Design setzen';
        $confirmText = 'Sollen wirklich alle Boards in dieser Kategorie auf das Design der Kategorie zurückgesetzt werden?';
        $confirmHref = 'boarddesign.php?boarddesign=1&catid=' . $catid;
    } else {
        $confirmTitle = 'Alle Boards auf Default-Design setzen';
        $confirmText = 'Sollen wirklich alle Boards und Kategorien auf das Default-Design zurückgesetzt werden?';
        $confirmHref = 'boarddesign.php?boarddesign=1';
    }
}
?>

<header class="mb-3">
  <h1 class="h3 mb-0"><i class="bi bi-palette" aria-hidden="true"></i> Board-Design</h1>
</header>

<?php if ($applied): ?>
  <div class="alert alert-success" role="alert">
    <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
    Design wurde uebernommen.
    <a class="alert-link" href="boards.php">Zurück zur Board-Verwaltung</a>.
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
      <div class="d-flex flex-wrap gap-2">
        <form action="<?php echo Security::escape($confirmHref); ?>" method="post" class="d-inline">
          <?php echo CSRF::getTokenField(); ?>
          <button type="submit" class="btn btn-warning">
            <i class="bi bi-check-lg" aria-hidden="true"></i> Ja, anwenden
          </button>
        </form>
        <a href="boards.php" class="btn btn-outline-secondary">
          <i class="bi bi-x-lg" aria-hidden="true"></i> Abbrechen
        </a>
      </div>
    </div>
  </section>
<?php endif; ?>

<?php include __DIR__ . '/footer.inc.php'; ?>
