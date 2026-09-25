<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Add Board Category
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

use PowerPHPBoard\CSRF;
use PowerPHPBoard\Security;

include __DIR__ . '/header.inc.php';

$addboardcategory = Security::getInt('addboardcategory', 'GET', 0);
$saved = false;
$formError = '';

if ($addboardcategory === 1 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::validateOrDie();
    $title = Security::getString('title', 'POST');

    if ($title === '') {
        $formError = $lang_adm_insertcategorytitle ?? 'Please enter a category title.';
    } elseif (!ppb_valid_template_setting(Security::getString('header', 'POST'))
        || !ppb_valid_template_setting(Security::getString('footer', 'POST'))) {
        $formError = $lang_adm_templateinvalid ?? 'Header and footer templates must be file names from the inc/ folder or stay empty.';
    } else {
        $title = trim(strip_tags($title));
        $header = Security::getString('header', 'POST') ?: ($settings['header'] ?? '');
        $footer = Security::getString('footer', 'POST') ?: ($settings['footer'] ?? '');
        $bordercolor = Security::getString('bordercolor', 'POST') ?: ($settings['bordercolor'] ?? '#000000');
        $tablebg1 = Security::getString('tablebg1', 'POST') ?: ($settings['tablebg1'] ?? '#FFFFFF');
        $tablebg2 = Security::getString('tablebg2', 'POST') ?: ($settings['tablebg2'] ?? '#F0F0F0');
        $tablebg3 = Security::getString('tablebg3', 'POST') ?: ($settings['tablebg3'] ?? '#E0E0E0');
        $newthread = Security::getString('newthread', 'POST') ?: ($settings['newthread'] ?? '');
        $newpost = Security::getString('newpost', 'POST') ?: ($settings['newpost'] ?? '');

        $db->execute(
            "INSERT INTO ppb_boards (title, type, header, footer, bordercolor, tablebg1, tablebg2, tablebg3, newthread, newpost) VALUES (?, 'Boardcategory', ?, ?, ?, ?, ?, ?, ?, ?)",
            [$title, $header, $footer, $bordercolor, $tablebg1, $tablebg2, $tablebg3, $newthread, $newpost]
        );
        CSRF::regenerate();
        $saved = true;
    }
}

// Nach einem Fehler die Eingaben wieder anzeigen, sonst die Vorgaben
$old = static fn (string $field, string $default = ''): string => ($formError !== '' && !$saved)
    ? Security::getString($field, 'POST', $default)
    : $default;
$designValues = [];
foreach (['header', 'footer', 'bordercolor', 'tablebg1', 'tablebg2', 'tablebg3', 'newthread', 'newpost'] as $field) {
    $designValues[$field] = $old($field, (string) ($settings[$field] ?? ''));
}
?>

<header class="mb-3">
  <h1 class="h3 mb-0"><i class="bi bi-folder-plus" aria-hidden="true"></i> <?php echo Security::escape($lang_adm_addcategory ?? 'Add category'); ?></h1>
</header>

<?php if ($saved): ?>
  <div class="alert alert-success" role="alert">
    <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
    <?php echo Security::escape($lang_adm_categorycreated ?? 'The category has been created.'); ?>
    <a class="alert-link" href="boards.php"><?php echo Security::escape($lang_adm_backtoboards ?? 'Back to board management'); ?></a>
  </div>
<?php endif; ?>
<?php if ($formError !== ''): ?>
  <div class="alert alert-danger" role="alert"><?php echo Security::escape($formError); ?></div>
<?php endif; ?>

<form action="addboardcategory.php?addboardcategory=1" method="post" class="needs-validation" novalidate>
  <?php echo CSRF::getTokenField(); ?>

  <section class="card shadow-sm mb-3">
    <header class="card-header bg-secondary-subtle">
      <h2 class="h6 mb-0"><i class="bi bi-info-circle" aria-hidden="true"></i> <?php echo Security::escape($lang_adm_category ?? 'Category'); ?></h2>
    </header>
    <div class="card-body">
      <div class="mb-3">
        <label for="title" class="form-label fw-semibold"><?php echo Security::escape($lang_title ?? 'Title'); ?> <span class="text-danger" aria-hidden="true">*</span></label>
        <input id="title" name="title" type="text" class="form-control" maxlength="100" required
               value="<?php echo Security::escape($old('title')); ?>">
        <div class="invalid-feedback"><?php echo Security::escape($lang_adm_insertcategorytitle ?? 'Please enter a category title.'); ?></div>
      </div>
      <?php echo ppb_admin_design_fields($designValues, $lang_adm_designnote_new ?? 'The Bootstrap 5 layout no longer uses these fields. They are optional and kept for compatibility; the defaults come from the general settings.'); ?>
    </div>
  </section>

  <div class="d-flex flex-wrap gap-2 mb-4">
    <button type="submit" class="btn btn-primary">
      <i class="bi bi-folder-plus" aria-hidden="true"></i> <?php echo Security::escape($lang_adm_addcategory ?? 'Add category'); ?>
    </button>
    <a class="btn btn-link" href="boards.php"><?php echo Security::escape($lang_adm_cancel ?? 'Cancel'); ?></a>
  </div>
</form>

<?php include __DIR__ . '/footer.inc.php'; ?>
