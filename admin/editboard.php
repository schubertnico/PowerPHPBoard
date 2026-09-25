<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Edit Board
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

use PowerPHPBoard\BoardAccess;
use PowerPHPBoard\CSRF;
use PowerPHPBoard\Security;

include __DIR__ . '/header.inc.php';

$row = $db->fetchOne("SELECT * FROM ppb_boards WHERE id = ? AND type = 'Board'", [$boardid]);
$editboard = Security::getInt('editboard', 'GET', 0);
$saved = false;
$deleted = false;
$formError = '';
$statusLabels = [
    'Open' => $lang_adm_status_open ?? 'Open',
    'Closed' => $lang_adm_status_closed ?? 'Closed',
    'Private' => $lang_adm_status_private ?? 'Private',
];

// Anzahl der Themen im Board ermitteln (für Lösch-Warnung)
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
            $formError = sprintf(
                $lang_adm_deleteneedsconfirm ?? 'This board contains threads (%d). Tick "Deletion confirmation" as well if all threads and posts should be deleted.',
                $threadsInBoard
            );
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

        if (!array_key_exists($status, $statusLabels)) {
            $status = 'Open';
        }
        // Board-Passwort nur als Hash speichern; leeres Feld behält das
        // bisherige Passwort, ohne Status "Private" wird es gelöscht.
        $passwordHash = (string) $row['password'];
        if ($status !== 'Private') {
            $passwordHash = '';
        } elseif ($password !== '') {
            $passwordHash = BoardAccess::hashPassword($password);
        }

        if ($status === 'Private' && $passwordHash === '') {
            $formError = $lang_adm_privateneedspassword ?? 'A private board needs a password.';
        } elseif ($title === '' || $catidPost === 0) {
            // Beschreibung und Design-Felder sind optional
            $formError = $lang_adm_titleandcategory ?? 'Please enter a title and choose a category.';
        } elseif (!ppb_valid_template_setting($header) || !ppb_valid_template_setting($footer)) {
            $formError = $lang_adm_templateinvalid ?? 'Header and footer templates must be file names from the inc/ folder or stay empty.';
        } else {
            $title = strip_tags($title);
            $description = strip_tags($description);
            $mods = trim($mods);

            $db->execute(
                'UPDATE ppb_boards SET title = ?, description = ?, mods = ?, catid = ?, status = ?, password = ?, header = ?, footer = ?, bordercolor = ?, tablebg1 = ?, tablebg2 = ?, tablebg3 = ?, newthread = ?, newpost = ? WHERE id = ?',
                [$title, $description, $mods, $catidPost, $status, $passwordHash, $header, $footer, $bordercolor, $tablebg1, $tablebg2, $tablebg3, $newthread, $newpost, $boardid]
            );
            // Neues oder entferntes Passwort: bisherige Zugangsnachweise verwerfen
            if ($passwordHash !== (string) $row['password']) {
                BoardAccess::revokeAll($boardid, $db);
            }
            CSRF::regenerate();
            $saved = true;
            $row = $db->fetchOne('SELECT * FROM ppb_boards WHERE id = ?', [$boardid]) ?? $row;
        }

        // Nach einem Fehler die Eingaben zeigen (das gespeicherte Passwort bleibt unberührt)
        if ($formError !== '') {
            $row = array_merge($row, compact(
                'title',
                'description',
                'mods',
                'status',
                'header',
                'footer',
                'bordercolor',
                'tablebg1',
                'tablebg2',
                'tablebg3',
                'newthread',
                'newpost'
            ), ['catid' => $catidPost]);
        }
    }
}
?>

<header class="mb-3">
  <h1 class="h3 mb-0"><i class="bi bi-pencil-square" aria-hidden="true"></i> <?php echo Security::escape($lang_adm_editboard ?? 'Edit board'); ?></h1>
</header>

<?php if ($deleted): ?>
  <div class="alert alert-success" role="alert">
    <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
    <?php echo Security::escape($lang_adm_boarddeleted ?? 'The board has been deleted.'); ?>
    <a class="alert-link" href="boards.php"><?php echo Security::escape($lang_adm_backtoboards ?? 'Back to board management'); ?></a>
  </div>
<?php elseif ($row === null): ?>
  <div class="alert alert-warning" role="alert">
    <?php echo Security::escape($lang_adm_boardnotfound ?? 'There is no board with this ID.'); ?>
    <a class="alert-link" href="boards.php"><?php echo Security::escape($lang_adm_backtoboards ?? 'Back to board management'); ?></a>
  </div>
<?php else:
    $hasBoardPassword = (string) ($row['password'] ?? '') !== '';
    $categories = $db->fetchAll('SELECT id, title FROM ppb_boards WHERE type = ? ORDER BY id', ['Boardcategory']);
    ?>

  <?php if ($saved): ?>
    <div class="alert alert-success" role="alert"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> <?php echo Security::escape($lang_adm_boardsaved ?? 'The board has been saved.'); ?></div>
  <?php endif; ?>
  <?php if ($formError !== ''): ?>
    <div class="alert alert-danger" role="alert"><?php echo Security::escape($formError); ?></div>
  <?php endif; ?>

  <form action="editboard.php?editboard=1&boardid=<?php echo (int) $row['id']; ?>"
        method="post" class="needs-validation" novalidate>
    <?php echo CSRF::getTokenField(); ?>

    <section class="card shadow-sm mb-3">
      <header class="card-header bg-secondary-subtle">
        <h2 class="h6 mb-0"><i class="bi bi-info-circle" aria-hidden="true"></i> <?php echo Security::escape($lang_adm_boardinfo ?? 'Board information'); ?></h2>
      </header>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label for="title" class="form-label fw-semibold"><?php echo Security::escape($lang_title ?? 'Title'); ?></label>
            <input id="title" name="title" type="text" class="form-control"
                   maxlength="100" required value="<?php echo Security::escape((string) $row['title']); ?>">
            <div class="invalid-feedback"><?php echo Security::escape($lang_adm_insertboardtitle ?? 'Please enter a board title.'); ?></div>
          </div>
          <div class="col-md-6">
            <label for="description" class="form-label"><?php echo Security::escape($lang_description ?? 'Description'); ?></label>
            <input id="description" name="description" type="text" class="form-control"
                   maxlength="150" value="<?php echo Security::escape((string) $row['description']); ?>">
          </div>
          <div class="col-md-6">
            <label for="mods" class="form-label"><?php echo Security::escape($lang_adm_moderators ?? 'Moderators'); ?></label>
            <input id="mods" name="mods" type="text" class="form-control" maxlength="250"
                   value="<?php echo Security::escape((string) $row['mods']); ?>" aria-describedby="modsHelp">
            <div id="modsHelp" class="form-text"><?php echo Security::escape($lang_adm_modshelp ?? 'Comma-separated list of email addresses, e.g. anna@example.org, moritz@example.org'); ?></div>
          </div>
          <div class="col-md-6">
            <label for="catid" class="form-label fw-semibold"><?php echo Security::escape($lang_adm_category ?? 'Category'); ?></label>
            <select id="catid" name="catid" class="form-select">
              <?php foreach ($categories as $cat): ?>
                <option value="<?php echo (int) $cat['id']; ?>" <?php echo (int) $cat['id'] === (int) $row['catid'] ? 'selected' : ''; ?>>
                  <?php echo Security::escape((string) $cat['title']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label for="status" class="form-label fw-semibold"><?php echo Security::escape($lang_status ?? 'Status'); ?></label>
            <select id="status" name="status" class="form-select" aria-describedby="statusHelp">
              <?php foreach ($statusLabels as $value => $label): ?>
                <option value="<?php echo $value; ?>" <?php echo $row['status'] === $value ? 'selected' : ''; ?>><?php echo Security::escape($label); ?></option>
              <?php endforeach; ?>
            </select>
            <div id="statusHelp" class="form-text"><?php echo Security::escape($lang_adm_statushelp ?? '"Closed" blocks new threads and replies, "Private" requires a password.'); ?></div>
          </div>
          <div class="col-md-6">
            <label for="password" class="form-label"><?php echo Security::escape($lang_adm_boardpassword ?? 'Board password (only for "Private")'); ?></label>
            <input id="password" name="password" type="password" class="form-control" maxlength="100"
                   autocomplete="new-password" value="" aria-describedby="passwordHelp">
            <div id="passwordHelp" class="form-text">
              <?php if ($hasBoardPassword): ?>
                <?php echo Security::escape($lang_adm_boardpasswordset ?? 'A password is set. It is only stored as a hash and cannot be displayed again. Leave empty to keep it.'); ?>
              <?php else: ?>
                <?php echo Security::escape($lang_adm_boardpasswordrequired ?? 'Required if the status is "Private".'); ?>
                <?php echo Security::escape($lang_adm_boardpasswordhashed ?? 'Only stored as a hash and cannot be displayed again.'); ?>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="card shadow-sm mb-3">
      <header class="card-header bg-secondary-subtle">
        <h2 class="h6 mb-0"><i class="bi bi-palette" aria-hidden="true"></i> <?php echo Security::escape($lang_adm_legacydesign ?? 'Design (legacy fields)'); ?></h2>
      </header>
      <div class="card-body">
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
        <?php if ($threadsInBoard > 0): ?>
          <div class="alert alert-warning small mb-3" role="alert">
            <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
            <?php echo Security::escape(sprintf($lang_adm_boardhasthreads ?? 'Threads in this board: %d. Deleting the board removes all threads and posts permanently.', $threadsInBoard)); ?>
          </div>
        <?php endif; ?>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="deleteboard"
                 name="deleteboard" value="YES">
          <label class="form-check-label fw-semibold text-danger" for="deleteboard">
            <?php echo Security::escape($lang_adm_deleteboard ?? 'Delete this board'); ?>
          </label>
        </div>
        <?php if ($threadsInBoard > 0): ?>
          <div class="form-check mt-2">
            <input class="form-check-input" type="checkbox" id="deleteconfirm"
                   name="deleteconfirm" value="YES">
            <label class="form-check-label text-danger" for="deleteconfirm">
              <?php echo Security::escape(sprintf($lang_adm_deleteconfirm ?? 'Deletion confirmation: I understand that all threads of this board (%d) including all posts will be removed permanently.', $threadsInBoard)); ?>
            </label>
          </div>
        <?php endif; ?>
        <div class="form-text mt-2">
          <i class="bi bi-info-circle" aria-hidden="true"></i>
          <?php echo Security::escape($lang_cannotbeundone ?? 'This action cannot be undone.'); ?>
        </div>
      </div>
    </section>

    <div class="d-flex flex-wrap gap-2 mb-4">
      <button type="submit" class="btn btn-primary">
        <i class="bi bi-save" aria-hidden="true"></i> <?php echo Security::escape($lang_adm_saveoraction ?? 'Save / perform action'); ?>
      </button>
      <a class="btn btn-link" href="boards.php"><?php echo Security::escape($lang_adm_cancel ?? 'Cancel'); ?></a>
    </div>
  </form>

<?php endif; ?>

<?php include __DIR__ . '/footer.inc.php'; ?>
