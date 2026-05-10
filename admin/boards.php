<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Board Administration
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

use PowerPHPBoard\Security;

include __DIR__ . '/header.inc.php';

if ($catid > 0) {
    $categories = $db->fetchAll('SELECT * FROM ppb_boards WHERE type = ? AND id = ?', ['Boardcategory', $catid]);
} else {
    $categories = $db->fetchAll('SELECT * FROM ppb_boards WHERE type = ? ORDER BY id', ['Boardcategory']);
}
?>

<header class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
  <h1 class="h3 mb-0"><i class="bi bi-folder2-open" aria-hidden="true"></i> Board-Verwaltung</h1>
  <div class="btn-group" role="group" aria-label="Board-Aktionen">
    <a class="btn btn-primary btn-sm" href="addboard.php">
      <i class="bi bi-plus-circle" aria-hidden="true"></i> Board hinzufügen
    </a>
    <a class="btn btn-outline-primary btn-sm" href="addboardcategory.php">
      <i class="bi bi-folder-plus" aria-hidden="true"></i> Kategorie hinzufügen
    </a>
    <a class="btn btn-outline-secondary btn-sm" href="boarddesign.php">
      <i class="bi bi-palette" aria-hidden="true"></i> Default-Design
    </a>
  </div>
</header>

<?php if (count($categories) === 0): ?>
  <div class="alert alert-warning" role="alert">
    Keine Board-Kategorien in der Datenbank. Bitte mindestens eine Kategorie anlegen.
  </div>
<?php else: ?>
  <?php foreach ($categories as $row): ?>
    <section class="card shadow-sm mb-3">
      <header class="card-header bg-secondary-subtle d-flex flex-wrap align-items-center justify-content-between gap-2">
        <h2 class="h6 mb-0">
          <a class="link-dark text-decoration-none fw-semibold"
             href="boards.php?catid=<?php echo (int) $row['id']; ?>">
            <?php echo Security::escape((string) $row['title']); ?>
          </a>
        </h2>
        <div class="btn-group btn-group-sm" role="group" aria-label="Kategorie-Aktionen">
          <a class="btn btn-outline-secondary"
             href="editboardcategory.php?catid=<?php echo (int) $row['id']; ?>">
            <i class="bi bi-pencil" aria-hidden="true"></i> Kategorie bearbeiten
          </a>
          <a class="btn btn-outline-secondary"
             href="boarddesign.php?catid=<?php echo (int) $row['id']; ?>">
            <i class="bi bi-palette" aria-hidden="true"></i> Design anwenden
          </a>
        </div>
      </header>

      <?php
      $boards = $db->fetchAll('SELECT * FROM ppb_boards WHERE type = ? AND catid = ? ORDER BY title', ['Board', $row['id']]);
      if (count($boards) === 0):
          ?>
        <div class="card-body text-center text-body-secondary">
          Keine Boards in dieser Kategorie.
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th scope="col" style="width:48px;"><span class="visually-hidden">Status</span></th>
                <th scope="col">Board</th>
                <th scope="col" style="width:240px;">Moderiert von</th>
                <th scope="col" class="text-end" style="width:160px;">Aktion</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($boards as $row2):
                  $statusBadge = '';
                  if ($row2['status'] === 'Closed') {
                      $statusBadge = '<span class="badge text-bg-secondary"><i class="bi bi-lock-fill" aria-hidden="true"></i> Closed</span>';
                  } elseif ($row2['status'] === 'Private') {
                      $statusBadge = '<span class="badge text-bg-warning"><i class="bi bi-shield-lock-fill" aria-hidden="true"></i> Private</span>';
                  }
                  ?>
                <tr>
                  <td class="text-center">
                    <?php if ($statusBadge !== ''): ?>
                      <?php echo $statusBadge; ?>
                    <?php else: ?>
                      <i class="bi bi-folder text-secondary" aria-hidden="true"></i>
                    <?php endif; ?>
                  </td>
                  <td>
                    <a class="fw-semibold text-decoration-none link-dark"
                       href="../showboard.php?boardid=<?php echo (int) $row2['id']; ?>">
                      <?php echo Security::escape((string) $row2['title']); ?>
                    </a>
                    <?php if (!empty($row2['description'])): ?>
                      <div class="small text-body-secondary">
                        <?php echo Security::escape((string) $row2['description']); ?>
                      </div>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php
                        if (!empty($row2['mods'])) {
                            $mods = explode(',', (string) $row2['mods']);
                            $links = [];
                            foreach ($mods as $modEmail) {
                                $modEmail = trim($modEmail);
                                if ($modEmail === '') {
                                    continue;
                                }
                                $modUser = $db->fetchOne('SELECT id, username FROM ppb_users WHERE email = ?', [$modEmail]);
                                if ($modUser !== null) {
                                    $links[] = '<a class="text-decoration-none" href="../showprofile.php?userid='
                                        . (int) $modUser['id'] . '">'
                                        . Security::escape((string) $modUser['username']) . '</a>';
                                }
                            }
                            echo $links === [] ? '<span class="text-body-secondary">&ndash;</span>' : implode(', ', $links);
                        } else {
                            echo '<span class="text-body-secondary">&ndash;</span>';
                        }
                  ?>
                  </td>
                  <td class="text-end">
                    <a class="btn btn-outline-primary btn-sm"
                       href="editboard.php?boardid=<?php echo (int) $row2['id']; ?>">
                      <i class="bi bi-pencil" aria-hidden="true"></i> Bearbeiten
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  <?php endforeach; ?>
<?php endif; ?>

<?php include __DIR__ . '/footer.inc.php'; ?>
