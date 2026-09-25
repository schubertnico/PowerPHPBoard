<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Web-Installer: Installation abgeschlossen
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

use PowerPHPBoard\CSRF;
use PowerPHPBoard\Security;

/**
 * @param array{config_written: bool, config_source: string, admin_username: string, admin_email: string, board_url: string} $done
 */
return static function (array $done, bool $configPresent): void {
    ?>
<div class="alert alert-success d-flex align-items-start gap-2" role="status" id="installer-success">
  <i class="bi bi-check-circle-fill fs-4" aria-hidden="true"></i>
  <div>
    <strong>PowerPHPBoard ist installiert.</strong><br>
    Ihr Administrator-Konto <strong><?php echo Security::escape($done['admin_username']); ?></strong>
    ist angelegt. Melden Sie sich mit <strong><?php echo Security::escape($done['admin_email']); ?></strong>
    und Ihrem Passwort an.
  </div>
</div>

<?php if ($done['config_written']): ?>
  <section class="card shadow-sm mb-4" id="config-written">
    <header class="card-header bg-secondary-subtle">
      <h2 class="h6 mb-0"><i class="bi bi-file-earmark-lock" aria-hidden="true"></i> Konfiguration gespeichert</h2>
    </header>
    <div class="card-body small">
      <p>
        Die Zugangsdaten stehen in <code>config.local.php</code> im Forumverzeichnis. Die Datei ist per
        <code>.htaccess</code> vor direktem Abruf geschützt und hat die Rechte <code>640</code> erhalten,
        sofern der Server das zulässt.
      </p>
      <p class="mb-0">
        Bitte prüfen Sie die Rechte im FTP-Programm: <code>640</code> (bzw. <code>600</code>) verhindert, dass
        andere Konten auf dem Server die Datei lesen. Legen Sie außerdem eine Sicherungskopie der Datei an.
      </p>
    </div>
  </section>
<?php elseif ($configPresent): ?>
  <div class="alert alert-success" role="status" id="config-present">
    <i class="bi bi-check2-circle" aria-hidden="true"></i>
    <code>config.local.php</code> wurde gefunden – das Forum ist einsatzbereit.
  </div>
<?php else: ?>
  <section class="card shadow-sm border-warning mb-4" id="config-manual">
    <header class="card-header bg-warning-subtle">
      <h2 class="h6 mb-0">
        <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
        Noch ein Schritt: <code>config.local.php</code> anlegen
      </h2>
    </header>
    <div class="card-body">
      <p>
        Der Installer durfte die Datei nicht selbst schreiben. Laden Sie sie herunter und legen Sie sie
        per FTP in das Forumverzeichnis (dort, wo auch <code>config.inc.php</code> liegt). Rechte danach
        auf <code>640</code> setzen. Die Datei enthält Ihr Datenbankpasswort – bitte nicht weitergeben.
      </p>
      <form method="post" action="index.php" class="mb-3" id="form-download-config">
        <?php echo CSRF::getTokenField(); ?>
        <button type="submit" name="action" value="download_config" class="btn btn-primary" id="btn-download-config">
          <i class="bi bi-download" aria-hidden="true"></i> config.local.php herunterladen
        </button>
      </form>
      <label for="config-local-content" class="form-label fw-semibold">Oder Inhalt kopieren:</label>
      <textarea id="config-local-content" class="form-control font-monospace small" rows="18" readonly
                spellcheck="false"><?php echo Security::escape($done['config_source']); ?></textarea>
      <p class="small text-body-secondary mt-2 mb-0">
        Sobald die Datei auf dem Server liegt, <a href="index.php" id="btn-config-recheck">laden Sie diese Seite neu</a>.
      </p>
    </div>
  </section>
<?php endif; ?>

<div class="alert alert-danger d-flex align-items-start gap-2" role="alert" id="delete-install-dir">
  <i class="bi bi-trash3-fill fs-4" aria-hidden="true"></i>
  <div>
    <strong>Bitte löschen Sie jetzt das Verzeichnis <code>install/</code> vom Server.</strong><br>
    Der Installer ist zwar gesperrt, gehört aber nicht auf ein laufendes Forum.
  </div>
</div>

<div class="d-flex flex-wrap gap-2 mb-4">
  <a class="btn btn-primary" href="../index.php" id="link-forum">
    <i class="bi bi-house-door" aria-hidden="true"></i> Zum Forum
  </a>
  <a class="btn btn-success" href="../login.php" id="link-login">
    <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> Anmelden
  </a>
  <a class="btn btn-outline-danger" href="../admin/" id="link-admin">
    <i class="bi bi-shield-lock" aria-hidden="true"></i> Adminbereich
  </a>
</div>
<?php
};
