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

// Sichtbare Bezeichnungen der Status (die Werte in der Datenbank bleiben englisch)
$statusLabels = [
    'Administrator' => $lang_administrator ?? 'Administrator',
    'Normal user' => $lang_adm_status_normal ?? 'Normal user',
    'Deactivated' => $lang_deactivated ?? 'Deactivated',
];

// Sicherer Status-Filter (nur erlaubte Werte)
$statusWhere = '';
$statusParams = [];
if (array_key_exists($filterStatus, $statusLabels)) {
    $statusWhere = ' AND status = ?';
    $statusParams = [$filterStatus];
} else {
    $filterStatus = '';
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
$renderStatusBadge = static function (string $status) use ($statusLabels): string {
    $label = htmlspecialchars($statusLabels[$status] ?? $status, ENT_QUOTES, 'UTF-8');

    return match ($status) {
        'Administrator' => '<span class="badge text-bg-danger">' . $label . '</span>',
        'Deactivated' => '<span class="badge text-bg-secondary">' . $label . '</span>',
        'Normal user' => '<span class="badge text-bg-success-subtle text-success-emphasis border">' . $label . '</span>',
        default => '<span class="badge text-bg-light text-dark border">' . $label . '</span>',
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

$filters = [
    ['', '', $lang_adm_all ?? 'All', 'SELECT COUNT(*) c FROM ppb_users'],
    ['Administrator', 'bi-shield-fill-check', $lang_adm_administrators ?? 'Administrators', "SELECT COUNT(*) c FROM ppb_users WHERE status = 'Administrator'"],
    ['Normal user', 'bi-person', $lang_adm_normalusers ?? 'Normal users', "SELECT COUNT(*) c FROM ppb_users WHERE status = 'Normal user'"],
    ['Deactivated', 'bi-person-slash', $lang_deactivated ?? 'Deactivated', "SELECT COUNT(*) c FROM ppb_users WHERE status = 'Deactivated'"],
];
?>

<header class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
  <h1 class="h3 mb-0"><i class="bi bi-people" aria-hidden="true"></i> <?php echo Security::escape($lang_adm_usermanagement ?? 'User management'); ?></h1>
  <a class="btn btn-primary btn-sm" href="adduser.php">
    <i class="bi bi-person-plus" aria-hidden="true"></i> <?php echo Security::escape($lang_adm_adduser ?? 'Add user'); ?>
  </a>
</header>

<section class="card shadow-sm mb-3">
  <header class="card-header bg-secondary-subtle">
    <h2 class="h6 mb-0"><i class="bi bi-search" aria-hidden="true"></i> <?php echo Security::escape($lang_adm_searchuser ?? 'Search users'); ?></h2>
  </header>
  <div class="card-body">
    <form action="user.php" method="post" class="row g-2">
      <?php echo CSRF::getTokenField(); ?>
      <div class="col-sm-8">
        <label for="username" class="form-label fw-semibold"><?php echo Security::escape($lang_username ?? 'Username'); ?></label>
        <input id="username" name="username" type="text" class="form-control"
               maxlength="50" value="<?php echo Security::escape($username); ?>"
               aria-describedby="usernameHelp">
      </div>
      <div class="col-sm-4 d-flex align-items-end">
        <button type="submit" class="btn btn-primary w-100">
          <i class="bi bi-search" aria-hidden="true"></i> <?php echo Security::escape($lang_adm_search ?? 'Search'); ?>
        </button>
      </div>
      <div class="col-12">
        <div id="usernameHelp" class="form-text mt-0">
          <?php echo Security::escape($lang_adm_searchhelp ?? 'Part of the name is enough. Leave empty to show all users in the list below.'); ?>
        </div>
      </div>
    </form>
  </div>
</section>

<?php if ($username === ''): ?>
  <!-- Filter-Tabs für Listen-Modus -->
  <ul class="nav nav-pills mb-3 small">
    <?php foreach ($filters as [$value, $icon, $label, $countSql]): ?>
      <li class="nav-item">
        <a class="nav-link <?php echo $filterStatus === $value ? 'active' : ''; ?>"
           href="<?php echo Security::escape($filterUrl($value !== '' ? $value : null)); ?>">
          <?php if ($icon !== ''): ?><i class="bi <?php echo $icon; ?>" aria-hidden="true"></i><?php endif; ?>
          <?php echo Security::escape($label); ?>
          <span class="badge text-bg-secondary ms-1"><?php echo (int) ($db->fetchOne($countSql)['c'] ?? 0); ?></span>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>

<section class="card shadow-sm mb-3">
  <header class="card-header bg-secondary-subtle d-flex flex-wrap align-items-center justify-content-between gap-2">
    <h2 class="h6 mb-0">
      <?php if ($username !== ''): ?>
        <?php echo Security::escape(sprintf($lang_adm_searchresults ?? 'Search results for "%s"', $username)); ?>
      <?php elseif ($filterStatus !== ''): ?>
        <?php echo Security::escape(($lang_adm_userlist ?? 'User list') . ' – ' . $statusLabels[$filterStatus]); ?>
      <?php else: ?>
        <?php echo Security::escape($lang_adm_userlist ?? 'User list'); ?>
      <?php endif; ?>
    </h2>
    <span class="badge text-bg-secondary"><?php echo $totalUsers; ?></span>
  </header>

  <?php if (count($users) === 0): ?>
    <div class="card-body text-center text-body-secondary">
      <?php echo Security::escape($username !== '' ? ($lang_adm_nousersfound ?? 'No users found.') : ($lang_adm_nousersinlist ?? 'There are no users in this list.')); ?>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th scope="col" style="width:60px;">ID</th>
            <th scope="col"><?php echo Security::escape($lang_username ?? 'Username'); ?></th>
            <th scope="col" class="d-none d-md-table-cell"><?php echo Security::escape($lang_email ?? 'Email'); ?></th>
            <th scope="col" class="d-none d-md-table-cell"><?php echo Security::escape($lang_status ?? 'Status'); ?></th>
            <th scope="col" class="d-none d-lg-table-cell" style="width:120px;"><?php echo Security::escape($lang_adm_registered ?? 'Registered'); ?></th>
            <th scope="col" class="text-end" style="width:160px;"><?php echo Security::escape($lang_adm_action ?? 'Action'); ?></th>
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
                  <i class="bi bi-shield-fill-check text-danger" aria-hidden="true" title="<?php echo Security::escape($statusLabels['Administrator']); ?>"></i>
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
                  <i class="bi bi-pencil" aria-hidden="true"></i> <?php echo Security::escape($lang_adm_edit ?? 'Edit'); ?>
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
          <?php echo Security::escape(sprintf($lang_adm_pageof ?? 'Page %1$d of %2$d (%3$d users)', $page, $totalPages, $totalUsers)); ?>
        </small>
        <nav aria-label="<?php echo Security::escape($lang_pages ?? 'Pages'); ?>">
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
