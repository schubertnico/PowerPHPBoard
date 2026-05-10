<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Edit Board
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

use PowerPHPBoard\CSRF;
use PowerPHPBoard\Security;

include __DIR__ . '/header.inc.php';

$row = $db->fetchOne('SELECT * FROM ppb_boards WHERE id = ?', [$boardid]);
$editboard = Security::getInt('editboard', 'GET', 0);
$saved = false;
$deleted = false;
$formError = '';

// Anzahl der Threads im Board ermitteln (für Lösch-Warnung)
$threadsInBoard = 0;
if ($row !== null) {
    $cnt = $db->fetchOne(
        "SELECT COUNT(*) c FROM ppb_posts WHERE type = 'Thread' AND boardid = ?",
        [$boardid]
    );
    $threadsInBoard = (int) ($cnt['c'] ?? 0);
}

if ($row !== null && $editboard === 1 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::validateOrDie();

    $deleteBoard = Security::getString('deleteboard', 'POST');
    $deleteConfirm = Security::getString('deleteconfirm', 'POST');

    if ($deleteBoard === 'YES') {
        if ($threadsInBoard > 0 && $deleteConfirm !== 'YES') {
            $formError = 'Dieses Board enthält ' . $threadsInBoard . ' Thread(s) mit Beiträgen. '
                . 'Aktiviere zusätzlich "Lösch-Bestätigung", wenn du alle Threads und '
                . 'Beiträge mit löschen möchtest.';
        } else {
            // Alle Posts/Threads im Board löschen
            $threadIds = $db->fetchAll(
                "SELECT id FROM ppb_posts WHERE type = 'Thread' AND boardid = ?",
                [$boardid]
            );
            foreach ($threadIds as $t) {
                $db->execute('DELETE FROM ppb_posts WHERE id = ? OR threadid = ?', [(int) $t['id'], (int) $t['id']]);
            }
            // Visits aufräumen
            $db->execute("DELETE FROM ppb_visits WHERE vid = ? AND type = 'Board'", [$boardid]);
            // Board löschen
            $db->execute('DELETE FROM ppb_boards WHERE id = ?', [$boardid]);
            CSRF::regenerate();
            $deleted = true;
            $row = null;
        }
    } else {
        $title = Security::getString('title', 'POST');
        $description = Security::getString('description', 'POST');
        $mods = Security::getString('mods', 'POST');
        $catidPost = Security::getInt('catid', 'POST', 0);
        $status = Security::getString('status', 'POST');
        $password = Security::getString('password', 'POST');
        $header = Security::getString('header', 'POST');
        $footer = Security::getString('footer', 'POST');
        $bordercolor = Security::getString('bordercolor', 'POST');
        $tablebg1 = Security::getString('tablebg1', 'POST');
        $tablebg2 = Security::getString('tablebg2', 'POST');
        $tablebg3 = Security::getString('tablebg3', 'POST');
        $newthread = Security::getString('newthread', 'POST');
        $newpost = Security::getString('newpost', 'POST');

        if ($status === 'Private' && $password === '') {
            $formError = 'Wenn der Status "Private" gewählt ist, muss ein Passwort gesetzt werden.';
        } elseif ($title === '' || $description === '' || $bordercolor === '' || $catidPost === 0) {
            $formError = 'Bitte fülle alle Pflichtfelder aus.';
        } else {
            $title = strip_tags($title);
            $description = strip_tags($description);
            $mods = trim($mods);
            $passwordEncoded = base64_encode($password);

            $db->execute(
                'UPDATE ppb_boards SET title = ?, description = ?, mods = ?, catid = ?, status = ?, password = ?, header = ?, footer = ?, bordercolor = ?, tablebg1 = ?, tablebg2 = ?, tablebg3 = ?, newthread = ?, newpost = ? WHERE id = ?',
                [$title, $description, $mods, $catidPost, $status, $passwordEncoded, $header, $footer, $bordercolor, $tablebg1, $tablebg2, $tablebg3, $newthread, $newpost, $boardid]
            );
            CSRF::regenerate();
            $saved = true;
            $row = $db->fetchOne('SELECT * FROM ppb_boards WHERE id = ?', [$boardid]) ?? $row;
        }
    }
}
?>

<header class="mb-3">
  <h1 class="h3 mb-0"><i class="bi bi-pencil-square" aria-hidden="true"></i> Board bearbeiten</h1>
</header>

<?php if ($deleted): ?>
  <div class="alert alert-success" role="alert">
    <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
    Board wurde gelöscht.
    <a class="alert-link" href="boards.php">Zurück zur Board-Verwaltung</a>.
  </div>
<?php elseif ($row === null): ?>
  <div class="alert alert-warning" role="alert">
    Kein Board mit dieser ID gefunden.
    <a class="alert-link" href="boards.php">Zurück zur Board-Verwaltung</a>.
  </div>
<?php else:
    $password = base64_decode((string) ($row['password'] ?? ''));
    $categories = $db->fetchAll('SELECT id, title FROM ppb_boards WHERE type = ? ORDER BY id', ['Boardcategory']);
    ?>

  <?php if ($saved): ?>
    <div class="alert alert-success" role="alert"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Board gespeichert.</div>
  <?php endif; ?>
  <?php if ($formError !== ''): ?>
    <div class="alert alert-danger" role="alert"><?php echo Security::escape($formError); ?></div>
  <?php endif; ?>

  <form action="editboard.php?editboard=1&boardid=<?php echo (int) $row['id']; ?>"
        method="post" class="needs-validation" novalidate>
    <?php echo CSRF::getTokenField(); ?>

    <section class="card shadow-sm mb-3">
      <header class="card-header bg-secondary-subtle">
        <h2 class="h6 mb-0"><i class="bi bi-info-circle" aria-hidden="true"></i> Board-Informationen</h2>
      </header>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label for="title" class="form-label fw-semibold">Titel</label>
            <input id="title" name="title" type="text" class="form-control"
                   maxlength="100" required value="<?php echo Security::escape((string) $row['title']); ?>">
          </div>
          <div class="col-md-6">
            <label for="description" class="form-label">Beschreibung</label>
            <input id="description" name="description" type="text" class="form-control"
                   maxlength="150" required value="<?php echo Security::escape((string) $row['description']); ?>">
          </div>
          <div class="col-md-6">
            <label for="mods" class="form-label">Moderatoren</label>
            <input id="mods" name="mods" type="text" class="form-control" maxlength="250"
                   value="<?php echo Security::escape((string) $row['mods']); ?>">
            <div class="form-text">Komma-getrennte Liste der E-Mail-Adressen.</div>
          </div>
          <div class="col-md-6">
            <label for="catid" class="form-label fw-semibold">Kategorie</label>
            <select id="catid" name="catid" class="form-select">
              <?php foreach ($categories as $cat): ?>
                <option value="<?php echo (int) $cat['id']; ?>" <?php echo (int) $cat['id'] === (int) $row['catid'] ? 'selected' : ''; ?>>
                  <?php echo Security::escape((string) $cat['title']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label for="status" class="form-label fw-semibold">Status</label>
            <select id="status" name="status" class="form-select">
              <?php foreach (['Open', 'Closed', 'Private'] as $s): ?>
                <option value="<?php echo $s; ?>" <?php echo $row['status'] === $s ? 'selected' : ''; ?>><?php echo $s; ?></option>
              <?php endforeach; ?>
            </select>
            <div class="form-text">"Closed" deaktiviert neue Threads. "Private" verlangt ein Passwort.</div>
          </div>
          <div class="col-md-6">
            <label for="password" class="form-label">Board-Passwort (nur bei "Private")</label>
            <input id="password" name="password" type="text" class="form-control" maxlength="25"
                   value="<?php echo Security::escape($password); ?>">
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
            <strong>nicht mehr</strong> für die Darstellung verwendet. Sie bleiben aus
            Kompatibilitätsgründen erhalten – Änderungen hier haben keine sichtbare Wirkung
            im Forum.
          </div>
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <label for="header" class="form-label">Eigenes Header-Template</label>
            <input id="header" name="header" type="text" class="form-control" maxlength="250"
                   value="<?php echo Security::escape((string) $row['header']); ?>"
                   aria-describedby="headerHelp">
            <div id="headerHelp" class="form-text">Dateiname aus dem <code>inc/</code>-Ordner. Leer = Standard-Header.</div>
          </div>
          <div class="col-md-6">
            <label for="footer" class="form-label">Eigenes Footer-Template</label>
            <input id="footer" name="footer" type="text" class="form-control" maxlength="250"
                   value="<?php echo Security::escape((string) $row['footer']); ?>"
                   aria-describedby="footerHelp">
            <div id="footerHelp" class="form-text">Dateiname aus dem <code>inc/</code>-Ordner. Leer = Standard-Footer.</div>
          </div>
          <div class="col-md-3">
            <label for="bordercolor" class="form-label">Rahmenfarbe</label>
            <div class="input-group">
              <input id="bordercolor" name="bordercolor" type="text" class="form-control" maxlength="7" required
                     value="<?php echo Security::escape((string) $row['bordercolor']); ?>"
                     aria-describedby="bordercolorHelp">
              <span class="input-group-text" style="background:<?php echo Security::escape((string) $row['bordercolor']); ?>;width:38px;" aria-hidden="true">&nbsp;</span>
            </div>
            <div id="bordercolorHelp" class="form-text">Hex-Farbcode, z.B. <code>#000000</code></div>
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
            <div class="form-text">Tabellen-Header</div>
          </div>
          <div class="col-md-6">
            <label for="newthread" class="form-label">Bild für "Neuer Thread"-Button</label>
            <input id="newthread" name="newthread" type="text" class="form-control" maxlength="250"
                   value="<?php echo Security::escape((string) $row['newthread']); ?>"
                   aria-describedby="newthreadHelp">
            <div id="newthreadHelp" class="form-text">Pfad zu einem 120×20-px-GIF/PNG, z.B. <code>images/newthread.gif</code></div>
          </div>
          <div class="col-md-6">
            <label for="newpost" class="form-label">Bild für "Neuer Beitrag"-Button</label>
            <input id="newpost" name="newpost" type="text" class="form-control" maxlength="250"
                   value="<?php echo Security::escape((string) $row['newpost']); ?>"
                   aria-describedby="newpostHelp">
            <div id="newpostHelp" class="form-text">Pfad zu einem 120×20-px-GIF/PNG, z.B. <code>images/newpost.gif</code></div>
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
        <?php if ($threadsInBoard > 0): ?>
          <div class="alert alert-warning small mb-3" role="alert">
            <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
            Dieses Board enthält <strong><?php echo $threadsInBoard; ?></strong>
            Thread(s) mit Beiträgen. Beim Löschen werden <strong>alle Threads
            und Beiträge unwiderruflich entfernt</strong>.
          </div>
        <?php endif; ?>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="deleteboard"
                 name="deleteboard" value="YES">
          <label class="form-check-label fw-semibold text-danger" for="deleteboard">
            Dieses Board löschen
          </label>
        </div>
        <?php if ($threadsInBoard > 0): ?>
          <div class="form-check mt-2">
            <input class="form-check-input" type="checkbox" id="deleteconfirm"
                   name="deleteconfirm" value="YES">
            <label class="form-check-label text-danger" for="deleteconfirm">
              Lösch-Bestätigung: Ich weiß, dass alle <?php echo $threadsInBoard; ?>
              Thread(s) inklusive aller Beiträge unwiderruflich entfernt werden.
            </label>
          </div>
        <?php endif; ?>
        <div class="form-text mt-2">
          <i class="bi bi-info-circle" aria-hidden="true"></i>
          Diese Aktion kann nicht rückgängig gemacht werden.
        </div>
      </div>
    </section>

    <div class="d-flex flex-wrap gap-2 mb-4">
      <button type="submit" class="btn btn-primary">
        <i class="bi bi-save" aria-hidden="true"></i> Speichern / Aktion ausführen
      </button>
      <a class="btn btn-link" href="boards.php">Abbrechen</a>
    </div>
  </form>

<?php endif; ?>

<?php include __DIR__ . '/footer.inc.php'; ?>
