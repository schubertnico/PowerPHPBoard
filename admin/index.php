<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Admin Index
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

use PowerPHPBoard\Security;

include __DIR__ . '/header.inc.php';

$userCount = (int) ($db->fetchOne('SELECT COUNT(*) c FROM ppb_users')['c'] ?? 0);
$boardCount = (int) ($db->fetchOne("SELECT COUNT(*) c FROM ppb_boards WHERE type = 'Board'")['c'] ?? 0);
$threadCount = (int) ($db->fetchOne("SELECT COUNT(*) c FROM ppb_posts WHERE type = 'Thread'")['c'] ?? 0);
$postCount = (int) ($db->fetchOne('SELECT COUNT(*) c FROM ppb_posts')['c'] ?? 0);
?>

<header class="mb-3">
  <h1 class="h3 mb-1"><i class="bi bi-shield-lock-fill" aria-hidden="true"></i> Administrationsbereich</h1>
  <p class="text-body-secondary mb-0">Willkommen, <strong><?php echo Security::escape((string) $ppbuser['username']); ?></strong>. Wähle einen Bereich.</p>
</header>

<div class="row g-3 row-cols-1 row-cols-md-2 row-cols-xl-4 mb-4">
  <div class="col">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="text-body-secondary small"><i class="bi bi-people" aria-hidden="true"></i> Nutzer</div>
        <div class="display-6"><?php echo $userCount; ?></div>
      </div>
    </div>
  </div>
  <div class="col">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="text-body-secondary small"><i class="bi bi-folder2-open" aria-hidden="true"></i> Boards</div>
        <div class="display-6"><?php echo $boardCount; ?></div>
      </div>
    </div>
  </div>
  <div class="col">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="text-body-secondary small"><i class="bi bi-card-list" aria-hidden="true"></i> Threads</div>
        <div class="display-6"><?php echo $threadCount; ?></div>
      </div>
    </div>
  </div>
  <div class="col">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="text-body-secondary small"><i class="bi bi-chat-square-text" aria-hidden="true"></i> Beiträge</div>
        <div class="display-6"><?php echo $postCount; ?></div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 row-cols-1 row-cols-md-3">
  <div class="col">
    <div class="card shadow-sm h-100">
      <div class="card-body">
        <h2 class="h5 card-title">
          <i class="bi bi-sliders" aria-hidden="true"></i>
          Allgemeine Einstellungen
        </h2>
        <p class="card-text text-body-secondary small">
          Boardname, URL, Admin-E-Mail, Sprache, Layout-Defaults, Smilies/BBCode/HTML-Schalter.
        </p>
      </div>
      <div class="card-footer bg-light">
        <a class="btn btn-primary btn-sm" href="general.php">
          <i class="bi bi-gear" aria-hidden="true"></i> Konfigurieren
        </a>
      </div>
    </div>
  </div>
  <div class="col">
    <div class="card shadow-sm h-100">
      <div class="card-body">
        <h2 class="h5 card-title">
          <i class="bi bi-folder2-open" aria-hidden="true"></i>
          Boards
        </h2>
        <p class="card-text text-body-secondary small">
          Kategorien und Boards anlegen, sortieren, schließen oder löschen.
        </p>
      </div>
      <div class="card-footer bg-light">
        <a class="btn btn-primary btn-sm" href="boards.php">
          <i class="bi bi-list-task" aria-hidden="true"></i> Verwalten
        </a>
      </div>
    </div>
  </div>
  <div class="col">
    <div class="card shadow-sm h-100">
      <div class="card-body">
        <h2 class="h5 card-title">
          <i class="bi bi-people" aria-hidden="true"></i>
          Nutzerverwaltung
        </h2>
        <p class="card-text text-body-secondary small">
          Nutzer anlegen, Rolle ändern, deaktivieren oder löschen.
        </p>
      </div>
      <div class="card-footer bg-light">
        <a class="btn btn-primary btn-sm" href="user.php">
          <i class="bi bi-person-gear" aria-hidden="true"></i> Verwalten
        </a>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/footer.inc.php'; ?>
