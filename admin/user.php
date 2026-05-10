<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - User Administration
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

use PowerPHPBoard\CSRF;
use PowerPHPBoard\Security;

include __DIR__ . '/header.inc.php';

$username = Security::getString('username', 'POST');
$filterStatus = Security::getString('status', 'GET');
$page = max(1, Security::getInt('page', 'GET', 1));
$perPage = 25;

// Sicherer Status-Filter (nur erlaubte Werte)
$allowedStatus = ['Administrator', 'Normal user', 'Deactivated'];
$statusWhere = '';
$statusParams = [];
if (in_array($filterStatus, $allowedStatus, true)) {
    $statusWhere = ' AND status = ?';
    $statusParams = [$filterStatus];
}

if ($username !== '') {
    // Such-Modus: Treffer auf Benutzername (LIKE) + optional Status-Filter
    $users = $db->fetchAll(
        'SELECT * FROM ppb_users WHERE username LIKE ?' . $statusWhere . ' ORDER BY id',
        array_merge(['%' . $username . '%'], $statusParams)
    );
    $totalUsers = count($users);
    $totalPages = 1;
} else {
    // Listen-Modus: alle Nutzer paginiert + optional Status-Filter
    $totalRow = $db->fetchOne(
        'SELECT COUNT(*) AS c FROM ppb_users WHERE 1=1' . $statusWhere,
        $statusParams
    );
    $totalUsers = (int) ($totalRow['c'] ?? 0);
    $totalPages = max(1, (int) ceil($totalUsers / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $perPage;
    $users = $db->fetchAll(
        'SELECT * FROM ppb_users WHERE 1=1' . $statusWhere
            . ' ORDER BY username LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset,
        $statusParams
    );
}

/**
 * Render Status-Badge for ppb_users.status
 */
$renderStatusBadge = static function (string $status): string {
    return match ($status) {
        'Administrator' => '<span class="badge text-bg-danger">Administrator</span>',
        'Deactivated' => '<span class="badge text-bg-secondary">Deaktiviert</span>',
        'Normal user' => '<span class="badge text-bg-success-subtle text-success-emphasis border">Normal</span>',
        default => '<span class="badge text-bg-light text-dark border">' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '</span>',
    };
};

// Filter-URL-Helper
$filterUrl = static function (?string $status, int $page = 1): string {
    $params = [];
    if ($status !== null && $status !== '') {
        $params['status'] = $status;
    }
    if ($page > 1) {
        $params['page'] = (string) $page;
    }
    return 'user.php' . ($params === [] ? '' : '?' . http_build_query($params));
};
?>

<header class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
  <h1 class="h3 mb-0"><i class="bi bi-people" aria-hidden="true"></i> Nutzerverwaltung</h1>
  <a class="btn btn-primary btn-sm" href="adduser.php">
    <i class="bi bi-person-plus" aria-hidden="true"></i> Nutzer anlegen
  </a>
</header>

<section class="card shadow-sm mb-3">
  <header class="card-header bg-secondary-subtle">
    <h2 class="h6 mb-0"><i class="bi bi-search" aria-hidden="true"></i> Nutzer suchen</h2>
  </header>
  <div class="card-body">
    <form action="user.php" method="post" class="row g-2">
      <?php echo CSRF::getTokenField(); ?>
      <div class="col-sm-8">
        <label for="username" class="form-label fw-semibold">Benutzername</label>
        <input id="username" name="username" type="text" class="form-control"
               maxlength="50" value="<?php echo Security::escape($username); ?>"
               aria-describedby="usernameHelp">
      </div>
      <div class="col-sm-4 d-flex align-items-end">
        <button type="submit" class="btn btn-primary w-100">
          <i class="bi bi-search" aria-hidden="true"></i> Suchen
        </button>
      </div>
      <div class="col-12">
        <div id="usernameHelp" class="form-text mt-0">
          Teil-String reicht; Suche per <code>LIKE %...%</code>. Leer lassen, um alle
          Nutzer in der Liste unten anzuzeigen.
        </div>
      </div>
    </form>
  </div>
</section>

<?php if ($username === ''): ?>
  <!-- Filter-Tabs für Listen-Modus -->
  <ul class="nav nav-pills mb-3 small">
    <li class="nav-item">
      <a class="nav-link <?php echo $filterStatus === '' ? 'active' : ''; ?>"
         href="<?php echo Security::escape($filterUrl(null)); ?>">
        Alle <span class="badge text-bg-secondary ms-1"><?php echo (int) ($db->fetchOne('SELECT COUNT(*) c FROM ppb_users')['c'] ?? 0); ?></span>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?php echo $filterStatus === 'Administrator' ? 'active' : ''; ?>"
         href="<?php echo Security::escape($filterUrl('Administrator')); ?>">
        <i class="bi bi-shield-fill-check" aria-hidden="true"></i> Administratoren
        <span class="badge text-bg-secondary ms-1"><?php echo (int) ($db->fetchOne("SELECT COUNT(*) c FROM ppb_users WHERE status = 'Administrator'")['c'] ?? 0); ?></span>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?php echo $filterStatus === 'Normal user' ? 'active' : ''; ?>"
         href="<?php echo Security::escape($filterUrl('Normal user')); ?>">
        <i class="bi bi-person" aria-hidden="true"></i> Normale Nutzer
        <span class="badge text-bg-secondary ms-1"><?php echo (int) ($db->fetchOne("SELECT COUNT(*) c FROM ppb_users WHERE status = 'Normal user'")['c'] ?? 0); ?></span>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?php echo $filterStatus === 'Deactivated' ? 'active' : ''; ?>"
         href="<?php echo Security::escape($filterUrl('Deactivated')); ?>">
        <i class="bi bi-person-slash" aria-hidden="true"></i> Deaktiviert
        <span class="badge text-bg-secondary ms-1"><?php echo (int) ($db->fetchOne("SELECT COUNT(*) c FROM ppb_users WHERE status = 'Deactivated'")['c'] ?? 0); ?></span>
      </a>
    </li>
  </ul>
<?php endif; ?>

<section class="card shadow-sm mb-3">
  <header class="card-header bg-secondary-subtle d-flex flex-wrap align-items-center justify-content-between gap-2">
    <h2 class="h6 mb-0">
      <?php if ($username !== ''): ?>
        Suchergebnisse für "<?php echo Security::escape($username); ?>"
      <?php elseif ($filterStatus !== ''): ?>
        Nutzerliste – <?php echo Security::escape($filterStatus); ?>
      <?php else: ?>
        Nutzerliste
      <?php endif; ?>
    </h2>
    <span class="badge text-bg-secondary"><?php echo $totalUsers; ?></span>
  </header>

  <?php if (count($users) === 0): ?>
    <div class="card-body text-center text-body-secondary">
      <?php echo $username !== '' ? 'Keine Nutzer gefunden.' : 'Keine Nutzer in dieser Liste.'; ?>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th scope="col" style="width:60px;">ID</th>
            <th scope="col">Benutzername</th>
            <th scope="col" class="d-none d-md-table-cell">E-Mail</th>
            <th scope="col" class="d-none d-md-table-cell">Status</th>
            <th scope="col" class="d-none d-lg-table-cell" style="width:120px;">Registriert</th>
            <th scope="col" class="text-end" style="width:160px;">Aktion</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $row):
              $registered = (int) ($row['registered'] ?? 0);
              ?>
            <tr>
              <td class="text-body-secondary small">#<?php echo (int) $row['id']; ?></td>
              <td>
                <span class="fw-semibold"><?php echo Security::escape((string) $row['username']); ?></span>
                <?php if ($row['status'] === 'Administrator'): ?>
                  <i class="bi bi-shield-fill-check text-danger" aria-hidden="true" title="Administrator"></i>
                <?php endif; ?>
                <div class="small text-body-secondary d-md-none">
                  <a class="text-decoration-none" href="mailto:<?php echo Security::escape((string) $row['email']); ?>">
                    <?php echo Security::escape((string) $row['email']); ?>
                  </a>
                  <br>
                  <?php echo $renderStatusBadge((string) $row['status']); ?>
                </div>
              </td>
              <td class="d-none d-md-table-cell">
                <a class="text-decoration-none" href="mailto:<?php echo Security::escape((string) $row['email']); ?>">
                  <?php echo Security::escape((string) $row['email']); ?>
                </a>
              </td>
              <td class="d-none d-md-table-cell"><?php echo $renderStatusBadge((string) $row['status']); ?></td>
              <td class="d-none d-lg-table-cell small text-body-secondary">
                <?php echo $registered > 0 ? date('d.m.Y', $registered) : '–'; ?>
              </td>
              <td class="text-end">
                <a class="btn btn-outline-primary btn-sm"
                   href="edituser.php?userid=<?php echo (int) $row['id']; ?>">
                  <i class="bi bi-pencil" aria-hidden="true"></i> Bearbeiten
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($username === '' && $totalPages > 1): ?>
      <footer class="card-footer bg-light d-flex flex-wrap align-items-center justify-content-between gap-2">
        <small class="text-body-secondary">
          Seite <?php echo $page; ?> von <?php echo $totalPages; ?>
          (<?php echo $totalUsers; ?> Nutzer)
        </small>
        <nav aria-label="Seiten">
          <ul class="pagination pagination-sm mb-0">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
              <li class="page-item<?php echo $i === $page ? ' active' : ''; ?>"
                  <?php echo $i === $page ? ' aria-current="page"' : ''; ?>>
                <a class="page-link" href="<?php echo Security::escape($filterUrl($filterStatus !== '' ? $filterStatus : null, $i)); ?>">
                  <?php echo $i; ?>
                </a>
              </li>
            <?php endfor; ?>
          </ul>
        </nav>
      </footer>
    <?php endif; ?>
  <?php endif; ?>
</section>

<?php include __DIR__ . '/footer.inc.php'; ?>
