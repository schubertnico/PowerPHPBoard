<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - General Administration
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

use PowerPHPBoard\BoardUrl;
use PowerPHPBoard\CSRF;
use PowerPHPBoard\Security;

include __DIR__ . '/header.inc.php';

$row = $db->fetchOne('SELECT * FROM ppb_config WHERE id = ?', [1]) ?? [];
$editgeneral = Security::getInt('editgeneral', 'GET', 0);
$saveSuccess = false;
$formError = '';
$languages = ['English' => 'English', 'Deutsch-Sie' => 'Deutsch (Sie)', 'Deutsch-Du' => 'Deutsch (Du)'];

if ($editgeneral === 1 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::validateOrDie();

    $boardtitle = Security::getString('boardtitle', 'POST');
    $boardurl = Security::getString('boardurl', 'POST');
    $adminemail = Security::getString('adminemail', 'POST');
    $header = Security::getString('header', 'POST');
    $footer = Security::getString('footer', 'POST');
    $bordercolor = Security::getString('bordercolor', 'POST');
    $tablebg1 = Security::getString('tablebg1', 'POST');
    $tablebg2 = Security::getString('tablebg2', 'POST');
    $tablebg3 = Security::getString('tablebg3', 'POST');
    $htmlcode = Security::getString('htmlcode', 'POST');
    $bbcode = Security::getString('bbcode', 'POST');
    $smilies = Security::getString('smilies', 'POST');
    $newthread = Security::getString('newthread', 'POST');
    $newpost = Security::getString('newpost', 'POST');
    $language = Security::getString('language', 'POST');

    // Schalter und Sprache nur mit erlaubten Werten speichern
    $htmlcode = $htmlcode === 'ON' ? 'ON' : 'OFF';
    $bbcode = $bbcode === 'OFF' ? 'OFF' : 'ON';
    $smilies = $smilies === 'OFF' ? 'OFF' : 'ON';
    if (!array_key_exists($language, $languages)) {
        $language = (string) ($row['language'] ?? 'English');
    }

    // Die Design-Felder (Farben, Button-Bilder) wirken im Bootstrap-Layout
    // nicht mehr und sind deshalb optional.
    if ($boardtitle === '' || $boardurl === '' || $adminemail === '') {
        $formError = $lang_adm_fillrequired ?? 'Please fill in all required fields.';
    } elseif (BoardUrl::base(['boardurl' => $boardurl]) === null) {
        $formError = $lang_adm_boardurlinvalid ?? 'Please enter a valid board URL starting with http:// or https://, e.g. https://forum.example.org.';
    } elseif (!Security::isValidEmail($adminemail)) {
        $formError = $lang_adm_adminemailinvalid ?? 'Please enter a valid administrator email address.';
    } elseif (!ppb_valid_template_setting($header) || !ppb_valid_template_setting($footer)) {
        $formError = $lang_adm_templateinvalid ?? 'Header and footer templates must be file names from the inc/ folder or stay empty.';
    } else {
        $db->execute(
            'UPDATE ppb_config SET boardtitle = ?, boardurl = ?, adminemail = ?, header = ?, footer = ?, bordercolor = ?, tablebg1 = ?, tablebg2 = ?, tablebg3 = ?, htmlcode = ?, bbcode = ?, smilies = ?, newthread = ?, newpost = ?, language = ? WHERE id = ?',
            [$boardtitle, $boardurl, $adminemail, $header, $footer, $bordercolor, $tablebg1, $tablebg2, $tablebg3, $htmlcode, $bbcode, $smilies, $newthread, $newpost, $language, 1]
        );
        CSRF::regenerate();
        $saveSuccess = true;
        $row = $db->fetchOne('SELECT * FROM ppb_config WHERE id = ?', [1]) ?? $row;
    }

    // Nach einem Fehler die Eingaben statt der gespeicherten Werte zeigen
    if ($formError !== '') {
        $row = array_merge($row, compact(
            'boardtitle',
            'boardurl',
            'adminemail',
            'header',
            'footer',
            'bordercolor',
            'tablebg1',
            'tablebg2',
            'tablebg3',
            'htmlcode',
            'bbcode',
            'smilies',
            'newthread',
            'newpost',
            'language'
        ));
    }
}

$features = [
    ['htmlcode', $lang_adm_htmlinposts ?? 'HTML in posts', $lang_adm_htmlhelp ?? 'Only simple formatting tags without attributes (b, i, u, p, ul, li …); links and images via BBCode.', $row['htmlcode'] ?? 'OFF'],
    ['bbcode', $lang_adm_bbcodeinposts ?? 'BBCode in posts', '', $row['bbcode'] ?? 'ON'],
    ['smilies', $lang_adm_smiliesinposts ?? 'Smilies in posts', '', $row['smilies'] ?? 'ON'],
];
?>

<header class="mb-3">
  <h1 class="h3 mb-1"><i class="bi bi-sliders" aria-hidden="true"></i> <?php echo Security::escape($lang_adm_generalsettings ?? 'General settings'); ?></h1>
  <p class="text-body-secondary mb-0"><?php echo Security::escape($lang_adm_generalintro ?? 'Name, address and language of the board, default design and features.'); ?></p>
</header>

<?php if ($saveSuccess): ?>
  <div class="alert alert-success" role="alert">
    <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
    <?php echo Security::escape($lang_adm_settingssaved ?? 'The settings have been saved.'); ?>
  </div>
<?php endif; ?>
<?php if ($formError !== ''): ?>
  <div class="alert alert-danger" role="alert">
    <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
    <?php echo Security::escape($formError); ?>
  </div>
<?php endif; ?>

<form action="general.php?editgeneral=1" method="post" class="needs-validation" novalidate>
  <?php echo CSRF::getTokenField(); ?>

  <section class="card shadow-sm mb-3">
    <header class="card-header bg-secondary-subtle">
      <h2 class="h6 mb-0"><i class="bi bi-info-circle" aria-hidden="true"></i> <?php echo Security::escape($lang_adm_generalinfo ?? 'General information'); ?></h2>
    </header>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-6">
          <label for="boardtitle" class="form-label fw-semibold"><?php echo Security::escape($lang_adm_boardtitle ?? 'Board title'); ?></label>
          <input id="boardtitle" name="boardtitle" type="text" class="form-control"
                 maxlength="200" required
                 value="<?php echo Security::escape((string) ($row['boardtitle'] ?? '')); ?>">
          <div class="form-text"><?php echo Security::escape($lang_adm_boardtitlehelp ?? 'Shown in the navigation bar and in the browser tab.'); ?></div>
          <div class="invalid-feedback"><?php echo Security::escape($lang_adm_insertboardtitle ?? 'Please enter a board title.'); ?></div>
        </div>
        <div class="col-md-6">
          <label for="boardurl" class="form-label fw-semibold"><?php echo Security::escape($lang_adm_boardurl ?? 'Board URL'); ?></label>
          <input id="boardurl" name="boardurl" type="url" class="form-control"
                 maxlength="250" required placeholder="https://forum.example.org"
                 value="<?php echo Security::escape((string) ($row['boardurl'] ?? '')); ?>">
          <div class="form-text"><?php echo Security::escape($lang_adm_boardurlhelp ?? 'Address of the board, e.g. https://forum.example.org. Used for links in emails (registration, forgotten password).'); ?></div>
          <div class="invalid-feedback"><?php echo Security::escape($lang_adm_boardurlfeedback ?? 'Please enter the full address starting with https:// or http://.'); ?></div>
        </div>
        <div class="col-md-6">
          <label for="adminemail" class="form-label fw-semibold"><?php echo Security::escape($lang_adm_adminemail ?? 'Administrator email'); ?></label>
          <input id="adminemail" name="adminemail" type="email" class="form-control"
                 maxlength="100" required
                 value="<?php echo Security::escape((string) ($row['adminemail'] ?? '')); ?>">
          <div class="form-text"><?php echo Security::escape($lang_adm_adminemailhelp ?? 'Sender address of the emails sent by the board.'); ?></div>
          <div class="invalid-feedback"><?php echo Security::escape($lang_insertvalidemail ?? 'Please enter a valid email address.'); ?></div>
        </div>
        <div class="col-md-6">
          <label for="language" class="form-label fw-semibold"><?php echo Security::escape($lang_adm_language ?? 'Language'); ?></label>
          <select id="language" name="language" class="form-select">
            <?php foreach ($languages as $value => $label): ?>
              <option value="<?php echo Security::escape($value); ?>"
                <?php echo ($row['language'] ?? '') === $value ? 'selected' : ''; ?>>
                <?php echo Security::escape($label); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>
  </section>

  <section class="card shadow-sm mb-3">
    <header class="card-header bg-secondary-subtle">
      <h2 class="h6 mb-0"><i class="bi bi-palette" aria-hidden="true"></i> <?php echo Security::escape($lang_adm_defaultdesign ?? 'Default design'); ?></h2>
    </header>
    <div class="card-body">
      <?php echo ppb_admin_design_fields($row, $lang_adm_designnote_general ?? 'The Bootstrap 5 layout no longer uses the colour and button image fields. They are optional, kept for compatibility and passed on as defaults to new boards and categories.'); ?>
    </div>
  </section>

  <section class="card shadow-sm mb-3">
    <header class="card-header bg-secondary-subtle">
      <h2 class="h6 mb-0"><i class="bi bi-toggles" aria-hidden="true"></i> <?php echo Security::escape($lang_adm_features ?? 'Features'); ?></h2>
    </header>
    <div class="card-body">
      <?php foreach ($features as [$name, $label, $help, $val]): ?>
        <fieldset class="mb-2">
          <legend class="form-label fw-semibold mb-1 fs-6"><?php echo Security::escape($label); ?></legend>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="<?php echo $name; ?>"
                   id="<?php echo $name; ?>On" value="ON" <?php echo $val === 'ON' ? 'checked' : ''; ?>>
            <label class="form-check-label" for="<?php echo $name; ?>On"><?php echo Security::escape($lang_on ?? 'on'); ?></label>
          </div>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="<?php echo $name; ?>"
                   id="<?php echo $name; ?>Off" value="OFF" <?php echo $val !== 'ON' ? 'checked' : ''; ?>>
            <label class="form-check-label" for="<?php echo $name; ?>Off"><?php echo Security::escape($lang_off ?? 'off'); ?></label>
          </div>
          <?php if ($help !== ''): ?>
            <div class="form-text mt-0"><?php echo Security::escape($help); ?></div>
          <?php endif; ?>
        </fieldset>
      <?php endforeach; ?>
    </div>
  </section>

  <div class="d-flex flex-wrap gap-2 mb-4">
    <button type="submit" class="btn btn-primary">
      <i class="bi bi-save" aria-hidden="true"></i> <?php echo Security::escape($lang_adm_savesettings ?? 'Save settings'); ?>
    </button>
    <button type="reset" class="btn btn-outline-secondary">
      <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> <?php echo Security::escape($lang_reset ?? 'Reset'); ?>
    </button>
    <a class="btn btn-link" href="index.php">
      <?php echo Security::escape($lang_adm_backtooverview ?? 'Back to the overview'); ?>
    </a>
  </div>
</form>

<?php include __DIR__ . '/footer.inc.php'; ?>
