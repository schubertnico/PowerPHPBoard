<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Add Board
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

use PowerPHPBoard\BoardAccess;
use PowerPHPBoard\CSRF;
use PowerPHPBoard\Security;

include __DIR__ . '/header.inc.php';

$addboard = Security::getInt('addboard', 'GET', 0);
$saved = false;
$formError = '';
$statusLabels = [
    'Open' => $lang_adm_status_open ?? 'Open',
    'Closed' => $lang_adm_status_closed ?? 'Closed',
    'Private' => $lang_adm_status_private ?? 'Private',
];

if ($addboard === 1 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::validateOrDie();
    $title = Security::getString('title', 'POST');
    $status = Security::getString('status', 'POST', 'Open');
    if (!array_key_exists($status, $statusLabels)) {
        $status = 'Open';
    }
    $password = Security::getString('password', 'POST');

    if ($title === '') {
        $formError = $lang_adm_insertboardtitle ?? 'Please enter a board title.';
    } elseif ($status === 'Private' && $password === '') {
        $formError = $lang_adm_privateneedspassword ?? 'A private board needs a password.';
    } elseif (!ppb_valid_template_setting(Security::getString('header', 'POST'))
        || !ppb_valid_template_setting(Security::getString('footer', 'POST'))) {
        $formError = $lang_adm_templateinvalid ?? 'Header and footer templates must be file names from the inc/ folder or stay empty.';
    } else {
        // Board-Passwort nur als Hash speichern
        $passwordHash = $status === 'Private' ? BoardAccess::hashPassword($password) : '';
        $description = Security::getString('description', 'POST');
        $mods = trim(Security::getString('mods', 'POST'));
        $catidPost = Security::getInt('catid', 'POST', 0);
        $header = Security::getString('header', 'POST') ?: ($settings['header'] ?? '');
        $footer = Security::getString('footer', 'POST') ?: ($settings['footer'] ?? '');
        $bordercolor = Security::getString('bordercolor', 'POST') ?: ($settings['bordercolor'] ?? '#000000');
        $tablebg1 = Security::getString('tablebg1', 'POST') ?: ($settings['tablebg1'] ?? '#FFFFFF');
        $tablebg2 = Security::getString('tablebg2', 'POST') ?: ($settings['tablebg2'] ?? '#F0F0F0');
        $tablebg3 = Security::getString('tablebg3', 'POST') ?: ($settings['tablebg3'] ?? '#E0E0E0');
        $newthread = Security::getString('newthread', 'POST') ?: ($settings['newthread'] ?? '');
        $newpost = Security::getString('newpost', 'POST') ?: ($settings['newpost'] ?? '');

        if ($catidPost === 0) {
            $firstCat = $db->fetchOne('SELECT id FROM ppb_boards WHERE type = ? ORDER BY id LIMIT 1', ['Boardcategory']);
            $catidPost = $firstCat ? (int) $firstCat['id'] : 0;
        }
        $title = strip_tags($title);
        $description = strip_tags($description);

        $db->execute(
            "INSERT INTO ppb_boards (title, description, type, mods, catid, status, password, header, footer, bordercolor, tablebg1, tablebg2, tablebg3, newthread, newpost) VALUES (?, ?, 'Board', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$title, $description, $mods, $catidPost, $status, $passwordHash, $header, $footer, $bordercolor, $tablebg1, $tablebg2, $tablebg3, $newthread, $newpost]
        );
        CSRF::regenerate();
        $saved = true;
    }
}

$categories = $db->fetchAll('SELECT * FROM ppb_boards WHERE type = ? ORDER BY id', ['Boardcategory']);

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
  <h1 class="h3 mb-0"><i class="bi bi-plus-circle" aria-hidden="true"></i> <?php echo Security::escape($lang_adm_addboard ?? 'Add board'); ?></h1>
</header>

<?php if ($saved): ?>
  <div class="alert alert-success" role="alert">
    <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
    <?php echo Security::escape($lang_adm_boardcreated ?? 'The board has been created.'); ?>
    <a class="alert-link" href="boards.php"><?php echo Security::escape($lang_adm_backtoboards ?? 'Back to board management'); ?></a>
  </div>
<?php endif; ?>
<?php if ($formError !== ''): ?>
  <div class="alert alert-danger" role="alert">
    <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
    <?php echo Security::escape($formError); ?>
  </div>
<?php endif; ?>

<form action="addboard.php?addboard=1" method="post" class="needs-validation" novalidate>
  <?php echo CSRF::getTokenField(); ?>
  <section class="card shadow-sm mb-3">
    <header class="card-header bg-secondary-subtle">
      <h2 class="h6 mb-0"><i class="bi bi-info-circle" aria-hidden="true"></i> <?php echo Security::escape($lang_adm_boardinfo ?? 'Board information'); ?></h2>
    </header>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-6">
          <label for="title" class="form-label fw-semibold"><?php echo Security::escape($lang_title ?? 'Title'); ?> <span class="text-danger" aria-hidden="true">*</span></label>
          <input id="title" name="title" type="text" class="form-control" maxlength="100" required
                 value="<?php echo Security::escape($old('title')); ?>">
          <div class="invalid-feedback"><?php echo Security::escape($lang_adm_insertboardtitle ?? 'Please enter a board title.'); ?></div>
        </div>
        <div class="col-md-6">
          <label for="description" class="form-label"><?php echo Security::escape($lang_description ?? 'Description'); ?></label>
          <input id="description" name="description" type="text" class="form-control" maxlength="150"
                 value="<?php echo Security::escape($old('description')); ?>">
        </div>
        <div class="col-md-6">
          <label for="mods" class="form-label"><?php echo Security::escape($lang_adm_moderators ?? 'Moderators'); ?></label>
          <input id="mods" name="mods" type="text" class="form-control" maxlength="250"
                 value="<?php echo Security::escape($old('mods')); ?>" aria-describedby="modsHelp">
          <div id="modsHelp" class="form-text"><?php echo Security::escape($lang_adm_modshelp ?? 'Comma-separated list of email addresses, e.g. anna@example.org, moritz@example.org'); ?></div>
        </div>
        <div class="col-md-6">
          <label for="catid" class="form-label fw-semibold"><?php echo Security::escape($lang_adm_category ?? 'Category'); ?></label>
          <?php if ($categories === []): ?>
            <p class="form-control-plaintext text-warning-emphasis mb-0">
              <?php echo Security::escape($lang_adm_nocategoriesyet ?? 'There are no categories yet.'); ?>
              <a href="addboardcategory.php"><?php echo Security::escape($lang_adm_addcategory ?? 'Add category'); ?></a>
            </p>
          <?php else: ?>
            <select id="catid" name="catid" class="form-select" required>
              <?php foreach ($categories as $cat): ?>
                <option value="<?php echo (int) $cat['id']; ?>" <?php echo $old('catid') === (string) $cat['id'] ? 'selected' : ''; ?>><?php echo Security::escape((string) $cat['title']); ?></option>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>
        </div>
        <div class="col-md-6">
          <label for="status" class="form-label fw-semibold"><?php echo Security::escape($lang_status ?? 'Status'); ?></label>
          <select id="status" name="status" class="form-select" aria-describedby="statusHelp">
            <?php foreach ($statusLabels as $value => $label): ?>
              <option value="<?php echo $value; ?>" <?php echo $old('status', 'Open') === $value ? 'selected' : ''; ?>><?php echo Security::escape($label); ?></option>
            <?php endforeach; ?>
          </select>
          <div id="statusHelp" class="form-text"><?php echo Security::escape($lang_adm_statushelp ?? '"Closed" blocks new threads and replies, "Private" requires a password.'); ?></div>
        </div>
        <div class="col-md-6">
          <label for="password" class="form-label"><?php echo Security::escape($lang_adm_boardpassword ?? 'Board password (only for "Private")'); ?></label>
          <input id="password" name="password" type="password" class="form-control" maxlength="100" autocomplete="new-password"
                 aria-describedby="passwordHelp">
          <div id="passwordHelp" class="form-text"><?php echo Security::escape($lang_adm_boardpasswordhashed ?? 'Only stored as a hash and cannot be displayed again.'); ?></div>
        </div>
      </div>
    </div>
  </section>

  <section class="card shadow-sm mb-3">
    <header class="card-header bg-secondary-subtle">
      <h2 class="h6 mb-0"><i class="bi bi-palette" aria-hidden="true"></i> <?php echo Security::escape($lang_adm_legacydesign ?? 'Design (legacy fields)'); ?></h2>
    </header>
    <div class="card-body">
      <?php echo ppb_admin_design_fields($designValues, $lang_adm_designnote_new ?? 'The Bootstrap 5 layout no longer uses these fields. They are optional and kept for compatibility; the defaults come from the general settings.'); ?>
    </div>
  </section>

  <div class="d-flex flex-wrap gap-2 mb-4">
    <button type="submit" class="btn btn-primary">
      <i class="bi bi-plus-circle" aria-hidden="true"></i> <?php echo Security::escape($lang_adm_addboard ?? 'Add board'); ?>
    </button>
    <a class="btn btn-link" href="boards.php"><?php echo Security::escape($lang_adm_cancel ?? 'Cancel'); ?></a>
  </div>
</form>

<?php include __DIR__ . '/footer.inc.php'; ?>
