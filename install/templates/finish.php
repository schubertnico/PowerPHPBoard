<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Web-Installer: Schritt 5 (Zusammenfassung und Abschluss)
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

use PowerPHPBoard\CSRF;
use PowerPHPBoard\Installer\FormValidator;
use PowerPHPBoard\Installer\Html;
use PowerPHPBoard\Installer\SmtpCheck;
use PowerPHPBoard\Installer\Wizard;
use PowerPHPBoard\Security;

return static function (Wizard $wizard, bool $configWritable, string $message): void {
    $database = $wizard->database();
    $forum = $wizard->forum();
    $admin = $wizard->admin();
    if ($database === null || $forum === null || $admin === null) {
        return;
    }
    $mail = $forum['mail'];
    ?>
<p>Bitte prüfen Sie Ihre Angaben. Mit „Jetzt installieren“ werden die Tabellen angelegt und Ihr Administrator-Konto erstellt.</p>

<?php echo Html::alert($message); ?>

<div class="row g-3 mb-4" id="installer-summary">
  <div class="col-md-6">
    <section class="card shadow-sm h-100">
      <header class="card-header bg-secondary-subtle d-flex justify-content-between align-items-center">
        <h2 class="h6 mb-0"><i class="bi bi-database" aria-hidden="true"></i> Datenbank</h2>
        <a class="small" href="index.php?step=2" id="edit-database">Ändern</a>
      </header>
      <dl class="card-body row align-content-start mb-0 small">
        <dt class="col-5">Server</dt>
        <dd class="col-7 text-break"><?php echo Security::escape($database['server'] . ':' . $database['port']); ?></dd>
        <dt class="col-5">Datenbank</dt>
        <dd class="col-7 text-break"><?php echo Security::escape($database['database']); ?></dd>
        <dt class="col-5">Benutzer</dt>
        <dd class="col-7 text-break mb-0"><?php echo Security::escape($database['user']); ?></dd>
      </dl>
    </section>
  </div>
  <div class="col-md-6">
    <section class="card shadow-sm h-100">
      <header class="card-header bg-secondary-subtle d-flex justify-content-between align-items-center">
        <h2 class="h6 mb-0"><i class="bi bi-person-badge" aria-hidden="true"></i> Administrator</h2>
        <a class="small" href="index.php?step=4" id="edit-admin">Ändern</a>
      </header>
      <dl class="card-body row align-content-start mb-0 small">
        <dt class="col-5">Benutzername</dt>
        <dd class="col-7 text-break"><?php echo Security::escape($admin['username']); ?></dd>
        <dt class="col-5">E-Mail</dt>
        <dd class="col-7 text-break mb-0"><?php echo Security::escape($admin['email']); ?></dd>
      </dl>
    </section>
  </div>
  <div class="col-12">
    <section class="card shadow-sm">
      <header class="card-header bg-secondary-subtle d-flex justify-content-between align-items-center">
        <h2 class="h6 mb-0"><i class="bi bi-chat-square-text" aria-hidden="true"></i> Forum</h2>
        <a class="small" href="index.php?step=3" id="edit-forum">Ändern</a>
      </header>
      <dl class="card-body row align-content-start mb-0 small">
        <dt class="col-sm-4">Name</dt>
        <dd class="col-sm-8 text-break"><?php echo Security::escape($forum['boardtitle']); ?></dd>
        <dt class="col-sm-4">Adresse</dt>
        <dd class="col-sm-8 text-break"><?php echo Security::escape($forum['boardurl']); ?></dd>
        <dt class="col-sm-4">E-Mail-Adresse</dt>
        <dd class="col-sm-8 text-break"><?php echo Security::escape($forum['adminemail']); ?></dd>
        <dt class="col-sm-4">Sprache</dt>
        <dd class="col-sm-8"><?php echo Security::escape(FormValidator::LANGUAGES[$forum['language']] ?? $forum['language']); ?></dd>
        <dt class="col-sm-4">E-Mail-Versand</dt>
        <dd class="col-sm-8 text-break mb-0">
          <?php if ($mail === null): ?>
            Standardwerte
          <?php else: ?>
            <?php echo Security::escape(SmtpCheck::describe($mail) . ', Absender ' . $mail['from']); ?>
          <?php endif; ?>
        </dd>
      </dl>
    </section>
  </div>
</div>

<?php if ($configWritable): ?>
  <div class="alert alert-info d-flex gap-2" role="note" id="config-mode-auto">
    <i class="bi bi-file-earmark-lock" aria-hidden="true"></i>
    <div>Die Zugangsdaten werden in <code>config.local.php</code> im Forumverzeichnis gespeichert.</div>
  </div>
<?php else: ?>
  <div class="alert alert-warning d-flex gap-2" role="note" id="config-mode-manual">
    <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
    <div>
      Das Forumverzeichnis ist nicht beschreibbar. Nach der Installation zeigt der Installer den
      Inhalt von <code>config.local.php</code> an – Sie laden die Datei dann selbst hoch (z. B. per FTP).
    </div>
  </div>
<?php endif; ?>

<form method="post" action="index.php?step=5" id="form-finish" class="d-flex justify-content-between align-items-center mb-4">
  <?php echo CSRF::getTokenField(); ?>
  <a class="btn btn-outline-secondary" href="index.php?step=4" id="btn-back">
    <i class="bi bi-arrow-left" aria-hidden="true"></i> Zurück
  </a>
  <button type="submit" name="action" value="finish" class="btn btn-success btn-lg" id="btn-finish">
    <i class="bi bi-rocket-takeoff" aria-hidden="true"></i> Jetzt installieren
  </button>
</form>
<?php
};
