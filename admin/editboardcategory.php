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
$row = $db->fetchOne('SELECT * FROM ppb_boards WHERE id = ?', [$catid]);
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
            $formError = 'Kategorie kann nicht gelöscht werden: Sie enthält noch '
                . $boardsInCategory . ' Board(s). Verschiebe oder lösche diese zuerst.';
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

        if ($title === '' || $bordercolor === '' || $tablebg1 === '' || $tablebg2 === '' || $tablebg3 === '') {
            $formError = 'Bitte fülle alle Pflichtfelder aus.';
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
    }
}
?>

<header class="mb-3">
  <h1 class="h3 mb-0"><i class="bi bi-folder-symlink" aria-hidden="true"></i> Kategorie bearbeiten</h1>
</header>

<?php if ($deleted): ?>
  <div class="alert alert-success" role="alert">
    <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
    Kategorie wurde gelöscht.
    <a class="alert-link" href="boards.php">Zurück zur Board-Verwaltung</a>.
  </div>
<?php elseif ($row === null): ?>
  <div class="alert alert-warning" role="alert">
    Keine Kategorie gefunden.
    <a class="alert-link" href="boards.php">Zurück zur Board-Verwaltung</a>.
  </div>
<?php else: ?>
  <?php if ($saved): ?>
    <div class="alert alert-success" role="alert"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Kategorie gespeichert.</div>
  <?php endif; ?>
  <?php if ($formError !== ''): ?>
    <div class="alert alert-danger" role="alert"><?php echo Security::escape($formError); ?></div>
  <?php endif; ?>

  <form action="editboardcategory.php?editboardcategory=1&catid=<?php echo (int) $row['id']; ?>"
        method="post" class="needs-validation" novalidate>
    <?php echo CSRF::getTokenField(); ?>
    <section class="card shadow-sm mb-3">
      <header class="card-header bg-secondary-subtle">
        <h2 class="h6 mb-0"><i class="bi bi-info-circle" aria-hidden="true"></i> Kategorie</h2>
      </header>
      <div class="card-body">
        <div class="mb-3">
          <label for="title" class="form-label fw-semibold">Titel</label>
          <input id="title" name="title" type="text" class="form-control"
                 maxlength="100" required value="<?php echo Security::escape((string) $row['title']); ?>">
        </div>
        <div class="alert alert-info small d-flex align-items-start gap-2 mt-3 mb-3" role="alert">
          <i class="bi bi-info-circle-fill fs-5" aria-hidden="true"></i>
          <div>
            <strong>Hinweis zu Design-Feldern:</strong> Im neuen Bootstrap-5-Layout werden
            diese Felder <strong>nicht mehr</strong> für die Darstellung verwendet. Sie
            bleiben aus Kompatibilitätsgründen erhalten.
          </div>
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <label for="header" class="form-label">Eigenes Header-Template</label>
            <input id="header" name="header" type="text" class="form-control" maxlength="250"
                   value="<?php echo Security::escape((string) $row['header']); ?>"
                   aria-describedby="headerHelp">
            <div id="headerHelp" class="form-text">Datei aus dem <code>inc/</code>-Ordner; leer = Standard.</div>
          </div>
          <div class="col-md-6">
            <label for="footer" class="form-label">Eigenes Footer-Template</label>
            <input id="footer" name="footer" type="text" class="form-control" maxlength="250"
                   value="<?php echo Security::escape((string) $row['footer']); ?>"
                   aria-describedby="footerHelp">
            <div id="footerHelp" class="form-text">Datei aus dem <code>inc/</code>-Ordner; leer = Standard.</div>
          </div>
          <div class="col-md-3">
            <label for="bordercolor" class="form-label">Rahmenfarbe</label>
            <div class="input-group">
              <input id="bordercolor" name="bordercolor" type="text" class="form-control" maxlength="7" required
                     value="<?php echo Security::escape((string) $row['bordercolor']); ?>">
              <span class="input-group-text" style="background:<?php echo Security::escape((string) $row['bordercolor']); ?>;width:38px;" aria-hidden="true">&nbsp;</span>
            </div>
            <div class="form-text">Hex, z.B. <code>#000000</code></div>
          </div>
          <div class="col-md-3">
            <label for="tablebg1" class="form-label">Tabelle Hintergrund 1</label>
            <div class="input-group">
              <input id="tablebg1" name="tablebg1" type="text" class="form-control" maxlength="7"
                     value="<?php echo Security::escape((string) $row['tablebg1']); ?>">
              <span class="input-group-text" style="background:<?php echo Security::escape((string) $row['tablebg1']); ?>;width:38px;" aria-hidden="true">&nbsp;</span>
            </div>
            <div class="form-text">Helle Zeile</div>
          </div>
          <div class="col-md-3">
            <label for="tablebg2" class="form-label">Tabelle Hintergrund 2</label>
            <div class="input-group">
              <input id="tablebg2" name="tablebg2" type="text" class="form-control" maxlength="7"
                     value="<?php echo Security::escape((string) $row['tablebg2']); ?>">
              <span class="input-group-text" style="background:<?php echo Security::escape((string) $row['tablebg2']); ?>;width:38px;" aria-hidden="true">&nbsp;</span>
            </div>
            <div class="form-text">Wechsel-Zeile</div>
          </div>
          <div class="col-md-3">
            <label for="tablebg3" class="form-label">Tabelle Hintergrund 3</label>
            <div class="input-group">
              <input id="tablebg3" name="tablebg3" type="text" class="form-control" maxlength="7"
                     value="<?php echo Security::escape((string) $row['tablebg3']); ?>">
              <span class="input-group-text" style="background:<?php echo Security::escape((string) $row['tablebg3']); ?>;width:38px;" aria-hidden="true">&nbsp;</span>
            </div>
            <div class="form-text">Header-Zeile</div>
          </div>
          <div class="col-md-6">
            <label for="newthread" class="form-label">Bild für "Neuer Thread"-Button</label>
            <input id="newthread" name="newthread" type="text" class="form-control" maxlength="250"
                   value="<?php echo Security::escape((string) $row['newthread']); ?>"
                   aria-describedby="newthreadHelp">
            <div id="newthreadHelp" class="form-text">Pfad zu einem 120×20-px-GIF/PNG.</div>
          </div>
          <div class="col-md-6">
            <label for="newpost" class="form-label">Bild für "Neuer Beitrag"-Button</label>
            <input id="newpost" name="newpost" type="text" class="form-control" maxlength="250"
                   value="<?php echo Security::escape((string) $row['newpost']); ?>"
                   aria-describedby="newpostHelp">
            <div id="newpostHelp" class="form-text">Pfad zu einem 120×20-px-GIF/PNG.</div>
          </div>
        </div>
      </div>
    </section>

    <section class="card shadow-sm border-danger mb-3">
      <header class="card-header bg-danger-subtle">
        <h2 class="h6 mb-0 text-danger-emphasis">
          <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
          Gefahrenzone
        </h2>
      </header>
      <div class="card-body">
        <?php if ($boardsInCategory > 0): ?>
          <p class="mb-2">
            Diese Kategorie enthält <strong><?php echo $boardsInCategory; ?></strong>
            Board(s). Sie kann erst gelöscht werden, wenn alle Boards verschoben oder
            gelöscht wurden.
          </p>
          <a class="btn btn-outline-secondary btn-sm"
             href="boards.php?catid=<?php echo (int) $row['id']; ?>">
            <i class="bi bi-folder2-open" aria-hidden="true"></i>
            Boards in dieser Kategorie anzeigen
          </a>
        <?php else: ?>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="deletecategory"
                   name="deletecategory" value="YES">
            <label class="form-check-label fw-semibold text-danger" for="deletecategory">
              Diese Kategorie löschen
            </label>
            <div class="form-text">
              <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
              Diese Aktion kann nicht rückgängig gemacht werden. Nur möglich, wenn die
              Kategorie keine Boards mehr enthält (aktuell: 0).
            </div>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <div class="d-flex flex-wrap gap-2 mb-4">
      <button type="submit" class="btn btn-primary"><i class="bi bi-save" aria-hidden="true"></i> Speichern</button>
      <a class="btn btn-link" href="boards.php">Abbrechen</a>
    </div>
  </form>
<?php endif; ?>

<?php include __DIR__ . '/footer.inc.php'; ?>
