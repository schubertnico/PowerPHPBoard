<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Edit Board Category
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

use PowerPHPBoard\CSRF;
use PowerPHPBoard\Security;

include __DIR__ . '/header.inc.php';

$catid = Security::getInt('catid', 'GET', 0);
$row = $db->fetchOne("SELECT * FROM ppb_boards WHERE id = ? AND type = 'Boardcategory'", [$catid]);
$editboardcategory = Security::getInt('editboardcategory', 'GET', 0);
$saved = false;
$deleted = false;
$formError = '';

// Anzahl der Boards in dieser Kategorie ermitteln (für Lösch-Schutz)
$boardsInCategory = 0;
if ($row !== null) {
    $cnt = $db->fetchOne(
        "SELECT COUNT(*) c FROM ppb_boards WHERE type = 'Board' AND catid = ?",
        [$catid]
    );
    $boardsInCategory = (int) ($cnt['c'] ?? 0);
}

if ($row !== null && $editboardcategory === 1 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::validateOrDie();

    $deleteCategory = Security::getString('deletecategory', 'POST');

    if ($deleteCategory === 'YES') {
        if ($boardsInCategory > 0) {
            $formError = sprintf(
                $lang_adm_categorynotempty ?? 'The category cannot be deleted because it still contains boards (%d). Please move or delete them first.',
                $boardsInCategory
            );
        } else {
            $db->execute('DELETE FROM ppb_boards WHERE id = ?', [$catid]);
            CSRF::regenerate();
            $deleted = true;
            $row = null;
        }
    } else {
        $title = Security::getString('title', 'POST');
        $header = Security::getString('header', 'POST');
        $footer = Security::getString('footer', 'POST');
        $bordercolor = Security::getString('bordercolor', 'POST');
        $tablebg1 = Security::getString('tablebg1', 'POST');
        $tablebg2 = Security::getString('tablebg2', 'POST');
        $tablebg3 = Security::getString('tablebg3', 'POST');
        $newthread = Security::getString('newthread', 'POST');
        $newpost = Security::getString('newpost', 'POST');

        // Die Design-Felder sind optional
        if ($title === '') {
            $formError = $lang_adm_insertcategorytitle ?? 'Please enter a category title.';
        } elseif (!ppb_valid_template_setting($header) || !ppb_valid_template_setting($footer)) {
            $formError = $lang_adm_templateinvalid ?? 'Header and footer templates must be file names from the inc/ folder or stay empty.';
        } else {
            $title = trim(strip_tags($title));
            $db->execute(
                'UPDATE ppb_boards SET title = ?, header = ?, footer = ?, bordercolor = ?, tablebg1 = ?, tablebg2 = ?, tablebg3 = ?, newthread = ?, newpost = ? WHERE id = ?',
                [$title, $header, $footer, $bordercolor, $tablebg1, $tablebg2, $tablebg3, $newthread, $newpost, $catid]
            );
            CSRF::regenerate();
            $saved = true;
            $row = $db->fetchOne('SELECT * FROM ppb_boards WHERE id = ?', [$catid]) ?? $row;
        }

        // Nach einem Fehler die Eingaben zeigen
        if ($formError !== '') {
            $row = array_merge($row, compact(
                'title',
                'header',
                'footer',
                'bordercolor',
                'tablebg1',
                'tablebg2',
                'tablebg3',
                'newthread',
                'newpost'
            ));
        }
    }
}
?>

<header class="mb-3">
  <h1 class="h3 mb-0"><i class="bi bi-folder-symlink" aria-hidden="true"></i> <?php echo Security::escape($lang_adm_editcategory ?? 'Edit category'); ?></h1>
</header>

<?php if ($deleted): ?>
  <div class="alert alert-success" role="alert">
    <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
    <?php echo Security::escape($lang_adm_categorydeleted ?? 'The category has been deleted.'); ?>
    <a class="alert-link" href="boards.php"><?php echo Security::escape($lang_adm_backtoboards ?? 'Back to board management'); ?></a>
  </div>
<?php elseif ($row === null): ?>
  <div class="alert alert-warning" role="alert">
    <?php echo Security::escape($lang_adm_categorynotfound ?? 'There is no category with this ID.'); ?>
    <a class="alert-link" href="boards.php"><?php echo Security::escape($lang_adm_backtoboards ?? 'Back to board management'); ?></a>
  </div>
<?php else: ?>

  <?php if ($saved): ?>
    <div class="alert alert-success" role="alert"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> <?php echo Security::escape($lang_adm_categorysaved ?? 'The category has been saved.'); ?></div>
  <?php endif; ?>
  <?php if ($formError !== ''): ?>
    <div class="alert alert-danger" role="alert"><?php echo Security::escape($formError); ?></div>
  <?php endif; ?>

  <form action="editboardcategory.php?editboardcategory=1&catid=<?php echo (int) $row['id']; ?>"
        method="post" class="needs-validation" novalidate>
    <?php echo CSRF::getTokenField(); ?>

    <section class="card shadow-sm mb-3">
      <header class="card-header bg-secondary-subtle">
        <h2 class="h6 mb-0"><i class="bi bi-info-circle" aria-hidden="true"></i> <?php echo Security::escape($lang_adm_category ?? 'Category'); ?></h2>
      </header>
      <div class="card-body">
        <div class="mb-3">
          <label for="title" class="form-label fw-semibold"><?php echo Security::escape($lang_title ?? 'Title'); ?></label>
          <input id="title" name="title" type="text" class="form-control"
                 maxlength="100" required value="<?php echo Security::escape((string) $row['title']); ?>">
          <div class="invalid-feedback"><?php echo Security::escape($lang_adm_insertcategorytitle ?? 'Please enter a category title.'); ?></div>
        </div>
        <?php echo ppb_admin_design_fields($row, $lang_adm_designnote_edit ?? 'The Bootstrap 5 layout no longer uses these fields. They are optional and kept for compatibility; changes here have no visible effect in the board.'); ?>
      </div>
    </section>

    <section class="card shadow-sm border-danger mb-3">
      <header class="card-header bg-danger-subtle">
        <h2 class="h6 mb-0 text-danger-emphasis">
          <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
          <?php echo Security::escape($lang_adm_dangerzone ?? 'Danger zone'); ?>
        </h2>
      </header>
      <div class="card-body">
        <?php if ($boardsInCategory > 0): ?>
          <p class="mb-2">
            <?php echo Security::escape(sprintf($lang_adm_categoryhasboards ?? 'Boards in this category: %d. The category can only be deleted once all boards have been moved or deleted.', $boardsInCategory)); ?>
          </p>
          <a class="btn btn-outline-secondary btn-sm"
             href="boards.php?catid=<?php echo (int) $row['id']; ?>">
            <i class="bi bi-folder2-open" aria-hidden="true"></i>
            <?php echo Security::escape($lang_adm_showboardsincategory ?? 'Show the boards in this category'); ?>
          </a>
        <?php else: ?>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="deletecategory"
                   name="deletecategory" value="YES">
            <label class="form-check-label fw-semibold text-danger" for="deletecategory">
              <?php echo Security::escape($lang_adm_deletecategory ?? 'Delete this category'); ?>
            </label>
            <div class="form-text">
              <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
              <?php echo Security::escape($lang_cannotbeundone ?? 'This action cannot be undone.'); ?>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <div class="d-flex flex-wrap gap-2 mb-4">
      <button type="submit" class="btn btn-primary"><i class="bi bi-save" aria-hidden="true"></i> <?php echo Security::escape($lang_adm_save ?? 'Save'); ?></button>
      <a class="btn btn-link" href="boards.php"><?php echo Security::escape($lang_adm_cancel ?? 'Cancel'); ?></a>
    </div>
  </form>
<?php endif; ?>

<?php include __DIR__ . '/footer.inc.php'; ?>
