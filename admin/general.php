<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - General Administration
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

use PowerPHPBoard\CSRF;
use PowerPHPBoard\Security;

include __DIR__ . '/header.inc.php';

$row = $db->fetchOne('SELECT * FROM ppb_config WHERE id = ?', [1]) ?? [];
$editgeneral = Security::getInt('editgeneral', 'GET', 0);
$saveSuccess = false;
$formError = '';

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

    if ($boardtitle === '' || $boardurl === '' || $adminemail === ''
        || $bordercolor === '' || $tablebg1 === '' || $tablebg2 === '' || $tablebg3 === '') {
        $formError = 'Bitte fülle alle Pflichtfelder aus.';
    } else {
        $db->execute(
            'UPDATE ppb_config SET boardtitle = ?, boardurl = ?, adminemail = ?, header = ?, footer = ?, bordercolor = ?, tablebg1 = ?, tablebg2 = ?, tablebg3 = ?, htmlcode = ?, bbcode = ?, smilies = ?, newthread = ?, newpost = ?, language = ? WHERE id = ?',
            [$boardtitle, $boardurl, $adminemail, $header, $footer, $bordercolor, $tablebg1, $tablebg2, $tablebg3, $htmlcode, $bbcode, $smilies, $newthread, $newpost, $language, 1]
        );
        CSRF::regenerate();
        $saveSuccess = true;
        $row = $db->fetchOne('SELECT * FROM ppb_config WHERE id = ?', [1]) ?? $row;
    }
}

$languages = ['English', 'Deutsch-Sie', 'Deutsch-Du'];
?>

<header class="mb-3">
  <h1 class="h3 mb-1"><i class="bi bi-sliders" aria-hidden="true"></i> Allgemeine Einstellungen</h1>
  <p class="text-body-secondary mb-0">Allgemeines Forum-Setup, Standard-Design und Feature-Schalter.</p>
</header>

<?php if ($saveSuccess): ?>
  <div class="alert alert-success" role="alert">
    <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
    Einstellungen wurden gespeichert.
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
      <h2 class="h6 mb-0"><i class="bi bi-info-circle" aria-hidden="true"></i> Allgemeine Informationen</h2>
    </header>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-6">
          <label for="boardtitle" class="form-label fw-semibold">Boardtitel</label>
          <input id="boardtitle" name="boardtitle" type="text" class="form-control"
                 maxlength="200" required
                 value="<?php echo Security::escape((string) ($row['boardtitle'] ?? '')); ?>">
          <div class="form-text">Wird als Marke im Header und Browser-Tab angezeigt.</div>
        </div>
        <div class="col-md-6">
          <label for="boardurl" class="form-label fw-semibold">Board-URL</label>
          <input id="boardurl" name="boardurl" type="url" class="form-control"
                 maxlength="250" required
                 value="<?php echo Security::escape((string) ($row['boardurl'] ?? '')); ?>">
          <div class="form-text">Wird in Mails verwendet.</div>
        </div>
        <div class="col-md-6">
          <label for="adminemail" class="form-label fw-semibold">Admin-E-Mail</label>
          <input id="adminemail" name="adminemail" type="email" class="form-control"
                 maxlength="100" required
                 value="<?php echo Security::escape((string) ($row['adminemail'] ?? '')); ?>">
        </div>
        <div class="col-md-6">
          <label for="language" class="form-label fw-semibold">Sprache</label>
          <select id="language" name="language" class="form-select">
            <?php foreach ($languages as $lang): ?>
              <option value="<?php echo Security::escape($lang); ?>"
                <?php echo ($row['language'] ?? '') === $lang ? 'selected' : ''; ?>>
                <?php echo Security::escape($lang); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>
  </section>

  <section class="card shadow-sm mb-3">
    <header class="card-header bg-secondary-subtle">
      <h2 class="h6 mb-0"><i class="bi bi-palette" aria-hidden="true"></i> Standard-Design</h2>
    </header>
    <div class="card-body">
      <div class="alert alert-info small d-flex align-items-start gap-2 mb-3" role="alert">
        <i class="bi bi-info-circle-fill fs-5" aria-hidden="true"></i>
        <div>
          <strong>Hinweis:</strong> Im neuen Bootstrap-5-Layout werden die Farb- und
          Button-Bild-Felder <strong>nicht mehr</strong> für die Darstellung verwendet.
          Sie bleiben aus Kompatibilitätsgründen erhalten und werden als Defaults an
          neue Boards / Kategorien vererbt.
        </div>
      </div>
      <div class="row g-3">
        <div class="col-md-6">
          <label for="header" class="form-label">Eigenes Header-Template</label>
          <input id="header" name="header" type="text" class="form-control" maxlength="250"
                 value="<?php echo Security::escape((string) ($row['header'] ?? '')); ?>"
                 aria-describedby="headerHelp">
          <div id="headerHelp" class="form-text">Dateiname aus dem <code>inc/</code>-Ordner. Leer = Standard-Header.</div>
        </div>
        <div class="col-md-6">
          <label for="footer" class="form-label">Eigenes Footer-Template</label>
          <input id="footer" name="footer" type="text" class="form-control" maxlength="250"
                 value="<?php echo Security::escape((string) ($row['footer'] ?? '')); ?>"
                 aria-describedby="footerHelp">
          <div id="footerHelp" class="form-text">Dateiname aus dem <code>inc/</code>-Ordner. Leer = Standard-Footer.</div>
        </div>
        <div class="col-md-4">
          <label for="bordercolor" class="form-label">Rahmenfarbe</label>
          <div class="input-group">
            <input id="bordercolor" name="bordercolor" type="text" class="form-control"
                   maxlength="7" required
                   value="<?php echo Security::escape((string) ($row['bordercolor'] ?? '')); ?>"
                   aria-describedby="bordercolorHelp">
            <span class="input-group-text" style="background:<?php echo Security::escape((string) ($row['bordercolor'] ?? '#000')); ?>;width:40px;" aria-hidden="true">&nbsp;</span>
          </div>
          <div id="bordercolorHelp" class="form-text">Hex-Farbcode, z.B. <code>#000000</code></div>
        </div>
        <div class="col-md-4">
          <label for="tablebg1" class="form-label">Tabelle Hintergrund 1</label>
          <div class="input-group">
            <input id="tablebg1" name="tablebg1" type="text" class="form-control"
                   maxlength="7" required
                   value="<?php echo Security::escape((string) ($row['tablebg1'] ?? '')); ?>">
            <span class="input-group-text" style="background:<?php echo Security::escape((string) ($row['tablebg1'] ?? '#fff')); ?>;width:40px;" aria-hidden="true">&nbsp;</span>
          </div>
          <div class="form-text">Helle Tabellenzeile</div>
        </div>
        <div class="col-md-4">
          <label for="tablebg2" class="form-label">Tabelle Hintergrund 2</label>
          <div class="input-group">
            <input id="tablebg2" name="tablebg2" type="text" class="form-control"
                   maxlength="7" required
                   value="<?php echo Security::escape((string) ($row['tablebg2'] ?? '')); ?>">
            <span class="input-group-text" style="background:<?php echo Security::escape((string) ($row['tablebg2'] ?? '#eee')); ?>;width:40px;" aria-hidden="true">&nbsp;</span>
          </div>
          <div class="form-text">Wechsel-Zeile</div>
        </div>
        <div class="col-md-4">
          <label for="tablebg3" class="form-label">Tabelle Hintergrund 3</label>
          <div class="input-group">
            <input id="tablebg3" name="tablebg3" type="text" class="form-control"
                   maxlength="7" required
                   value="<?php echo Security::escape((string) ($row['tablebg3'] ?? '')); ?>">
            <span class="input-group-text" style="background:<?php echo Security::escape((string) ($row['tablebg3'] ?? '#ccc')); ?>;width:40px;" aria-hidden="true">&nbsp;</span>
          </div>
          <div class="form-text">Tabellen-Header</div>
        </div>
        <div class="col-md-4">
          <label for="newthread" class="form-label">Bild für "Neuer Thread"-Button</label>
          <input id="newthread" name="newthread" type="text" class="form-control" maxlength="250"
                 value="<?php echo Security::escape((string) ($row['newthread'] ?? '')); ?>"
                 aria-describedby="newthreadHelp">
          <div id="newthreadHelp" class="form-text">Pfad zu einem 120×20-px-GIF/PNG, z.B. <code>images/newthread.gif</code></div>
        </div>
        <div class="col-md-4">
          <label for="newpost" class="form-label">Bild für "Neuer Beitrag"-Button</label>
          <input id="newpost" name="newpost" type="text" class="form-control" maxlength="250"
                 value="<?php echo Security::escape((string) ($row['newpost'] ?? '')); ?>"
                 aria-describedby="newpostHelp">
          <div id="newpostHelp" class="form-text">Pfad zu einem 120×20-px-GIF/PNG, z.B. <code>images/newpost.gif</code></div>
        </div>
      </div>
    </div>
  </section>

  <section class="card shadow-sm mb-3">
    <header class="card-header bg-secondary-subtle">
      <h2 class="h6 mb-0"><i class="bi bi-toggles" aria-hidden="true"></i> Feature-Einstellungen</h2>
    </header>
    <div class="card-body">
      <?php
      $features = [
          ['htmlcode', 'HTML in Beiträgen', $row['htmlcode'] ?? 'OFF'],
          ['bbcode',   'BBCode in Beiträgen', $row['bbcode'] ?? 'ON'],
          ['smilies',  'Smilies in Beiträgen', $row['smilies'] ?? 'ON'],
      ];
      foreach ($features as [$name, $label, $val]):
      ?>
        <fieldset class="mb-2">
          <legend class="form-label fw-semibold mb-1 fs-6"><?php echo Security::escape($label); ?></legend>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="<?php echo $name; ?>"
                   id="<?php echo $name; ?>On" value="ON" <?php echo $val === 'ON' ? 'checked' : ''; ?>>
            <label class="form-check-label" for="<?php echo $name; ?>On">an</label>
          </div>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="<?php echo $name; ?>"
                   id="<?php echo $name; ?>Off" value="OFF" <?php echo $val !== 'ON' ? 'checked' : ''; ?>>
            <label class="form-check-label" for="<?php echo $name; ?>Off">aus</label>
          </div>
        </fieldset>
      <?php endforeach; ?>
    </div>
  </section>

  <div class="d-flex flex-wrap gap-2 mb-4">
    <button type="submit" class="btn btn-primary">
      <i class="bi bi-save" aria-hidden="true"></i> Einstellungen speichern
    </button>
    <button type="reset" class="btn btn-outline-secondary">
      <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Zurücksetzen
    </button>
    <a class="btn btn-link" href="index.php">
      Zurück zur Übersicht
    </a>
  </div>
</form>

<?php include __DIR__ . '/footer.inc.php'; ?>
