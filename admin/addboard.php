<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Add Board
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

use PowerPHPBoard\CSRF;
use PowerPHPBoard\Security;

include __DIR__ . '/header.inc.php';

$addboard = Security::getInt('addboard', 'GET', 0);
$saved = false;
$formError = '';

if ($addboard === 1 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::validateOrDie();
    $title = Security::getString('title', 'POST');

    if ($title === '') {
        $formError = 'Bitte einen Boardtitel angeben.';
    } else {
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
            "INSERT INTO ppb_boards (title, description, type, mods, catid, header, footer, bordercolor, tablebg1, tablebg2, tablebg3, newthread, newpost) VALUES (?, ?, 'Board', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$title, $description, $mods, $catidPost, $header, $footer, $bordercolor, $tablebg1, $tablebg2, $tablebg3, $newthread, $newpost]
        );
        CSRF::regenerate();
        $saved = true;
    }
}

$categories = $db->fetchAll('SELECT * FROM ppb_boards WHERE type = ? ORDER BY id', ['Boardcategory']);
?>

<header class="mb-3">
  <h1 class="h3 mb-0"><i class="bi bi-plus-circle" aria-hidden="true"></i> Board anlegen</h1>
</header>

<?php if ($saved): ?>
  <div class="alert alert-success" role="alert">
    <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
    Board erfolgreich angelegt.
    <a class="alert-link" href="boards.php">Zurück zur Board-Verwaltung</a>.
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
      <h2 class="h6 mb-0"><i class="bi bi-info-circle" aria-hidden="true"></i> Board-Informationen</h2>
    </header>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-6">
          <label for="title" class="form-label fw-semibold">Titel <span class="text-danger" aria-hidden="true">*</span></label>
          <input id="title" name="title" type="text" class="form-control" maxlength="100" required>
          <div class="invalid-feedback">Bitte einen Boardtitel angeben.</div>
        </div>
        <div class="col-md-6">
          <label for="description" class="form-label">Beschreibung</label>
          <input id="description" name="description" type="text" class="form-control" maxlength="150">
        </div>
        <div class="col-md-6">
          <label for="mods" class="form-label">Moderatoren</label>
          <input id="mods" name="mods" type="text" class="form-control" maxlength="250">
          <div class="form-text">Komma-getrennte Liste der E-Mail-Adressen, z.B. <code>email1@x,email2@x</code>.</div>
        </div>
        <div class="col-md-6">
          <label for="catid" class="form-label fw-semibold">Kategorie</label>
          <?php if ($categories === []): ?>
            <p class="form-control-plaintext text-warning-emphasis mb-0">Keine Kategorien vorhanden.
              <a href="addboardcategory.php">Kategorie anlegen</a>.</p>
          <?php else: ?>
            <select id="catid" name="catid" class="form-select" required>
              <?php foreach ($categories as $cat): ?>
                <option value="<?php echo (int) $cat['id']; ?>"><?php echo Security::escape((string) $cat['title']); ?></option>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>

  <section class="card shadow-sm mb-3">
    <header class="card-header bg-secondary-subtle">
      <h2 class="h6 mb-0"><i class="bi bi-palette" aria-hidden="true"></i> Design (Legacy-Felder)</h2>
    </header>
    <div class="card-body">
      <div class="alert alert-info small d-flex align-items-start gap-2 mb-3" role="alert">
        <i class="bi bi-info-circle-fill fs-5" aria-hidden="true"></i>
        <div>
          <strong>Hinweis:</strong> Im neuen Bootstrap-5-Layout werden diese Felder
          <strong>nicht mehr</strong> für die Darstellung verwendet. Sie sind aus
          Kompatibilitätsgründen vorhanden – die Defaults werden aus den
          <a href="general.php" class="alert-link">allgemeinen Einstellungen</a> übernommen.
        </div>
      </div>
      <div class="row g-3">
        <div class="col-md-6">
          <label for="header" class="form-label">Eigenes Header-Template</label>
          <input id="header" name="header" type="text" class="form-control" maxlength="250"
                 value="<?php echo Security::escape((string) ($settings['header'] ?? '')); ?>"
                 aria-describedby="headerHelp">
          <div id="headerHelp" class="form-text">Dateiname aus dem <code>inc/</code>-Ordner. Leer = Standard-Header.</div>
        </div>
        <div class="col-md-6">
          <label for="footer" class="form-label">Eigenes Footer-Template</label>
          <input id="footer" name="footer" type="text" class="form-control" maxlength="250"
                 value="<?php echo Security::escape((string) ($settings['footer'] ?? '')); ?>"
                 aria-describedby="footerHelp">
          <div id="footerHelp" class="form-text">Dateiname aus dem <code>inc/</code>-Ordner. Leer = Standard-Footer.</div>
        </div>
        <div class="col-md-3">
          <label for="bordercolor" class="form-label">Rahmenfarbe</label>
          <div class="input-group">
            <input id="bordercolor" name="bordercolor" type="text" class="form-control" maxlength="7"
                   value="<?php echo Security::escape((string) ($settings['bordercolor'] ?? '')); ?>"
                   aria-describedby="bordercolorHelp">
            <span class="input-group-text" style="background:<?php echo Security::escape((string) ($settings['bordercolor'] ?? '#000')); ?>;width:38px;" aria-hidden="true">&nbsp;</span>
          </div>
          <div id="bordercolorHelp" class="form-text">Hex-Farbcode, z.B. <code>#000000</code></div>
        </div>
        <div class="col-md-3">
          <label for="tablebg1" class="form-label">Tabelle Hintergrund 1</label>
          <div class="input-group">
            <input id="tablebg1" name="tablebg1" type="text" class="form-control" maxlength="7"
                   value="<?php echo Security::escape((string) ($settings['tablebg1'] ?? '')); ?>">
            <span class="input-group-text" style="background:<?php echo Security::escape((string) ($settings['tablebg1'] ?? '#fff')); ?>;width:38px;" aria-hidden="true">&nbsp;</span>
          </div>
          <div class="form-text">Helle Zeile</div>
        </div>
        <div class="col-md-3">
          <label for="tablebg2" class="form-label">Tabelle Hintergrund 2</label>
          <div class="input-group">
            <input id="tablebg2" name="tablebg2" type="text" class="form-control" maxlength="7"
                   value="<?php echo Security::escape((string) ($settings['tablebg2'] ?? '')); ?>">
            <span class="input-group-text" style="background:<?php echo Security::escape((string) ($settings['tablebg2'] ?? '#eee')); ?>;width:38px;" aria-hidden="true">&nbsp;</span>
          </div>
          <div class="form-text">Wechsel-Zeile</div>
        </div>
        <div class="col-md-3">
          <label for="tablebg3" class="form-label">Tabelle Hintergrund 3</label>
          <div class="input-group">
            <input id="tablebg3" name="tablebg3" type="text" class="form-control" maxlength="7"
                   value="<?php echo Security::escape((string) ($settings['tablebg3'] ?? '')); ?>">
            <span class="input-group-text" style="background:<?php echo Security::escape((string) ($settings['tablebg3'] ?? '#ccc')); ?>;width:38px;" aria-hidden="true">&nbsp;</span>
          </div>
          <div class="form-text">Tabellen-Header</div>
        </div>
        <div class="col-md-6">
          <label for="newthread" class="form-label">Bild für "Neuer Thread"-Button</label>
          <input id="newthread" name="newthread" type="text" class="form-control" maxlength="250"
                 value="<?php echo Security::escape((string) ($settings['newthread'] ?? '')); ?>"
                 aria-describedby="newthreadHelp">
          <div id="newthreadHelp" class="form-text">Pfad zu einem 120×20-px-GIF/PNG, z.B. <code>images/newthread.gif</code></div>
        </div>
        <div class="col-md-6">
          <label for="newpost" class="form-label">Bild für "Neuer Beitrag"-Button</label>
          <input id="newpost" name="newpost" type="text" class="form-control" maxlength="250"
                 value="<?php echo Security::escape((string) ($settings['newpost'] ?? '')); ?>"
                 aria-describedby="newpostHelp">
          <div id="newpostHelp" class="form-text">Pfad zu einem 120×20-px-GIF/PNG, z.B. <code>images/newpost.gif</code></div>
        </div>
      </div>
    </div>
  </section>

  <div class="d-flex flex-wrap gap-2 mb-4">
    <button type="submit" class="btn btn-primary">
      <i class="bi bi-plus-circle" aria-hidden="true"></i> Board anlegen
    </button>
    <a class="btn btn-link" href="boards.php">Abbrechen</a>
  </div>
</form>

<?php include __DIR__ . '/footer.inc.php'; ?>
