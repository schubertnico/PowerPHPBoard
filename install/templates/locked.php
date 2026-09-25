<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Web-Installer: gesperrt
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

use PowerPHPBoard\Installer\InstallState;
use PowerPHPBoard\Security;

return static function (string $reason): void {
    $text = match ($reason) {
        InstallState::REASON_LOCK_FILE => 'Die Installation wurde bereits abgeschlossen (Sperrdatei install/.installed vorhanden).',
        InstallState::REASON_LOCAL_CONFIG => 'Es gibt bereits eine config.local.php. Damit eine bestehende Installation nicht überschrieben wird, startet der Installer nicht.',
        InstallState::REASON_DATABASE => 'In der konfigurierten Datenbank ist PowerPHPBoard bereits eingerichtet.',
        default => 'Es ist bereits eine Datenbank konfiguriert (Umgebungsvariablen oder angepasste config.inc.php), die gerade nicht erreichbar ist. Aus Sicherheitsgründen startet der Installer in diesem Zustand nicht.',
    };
    ?>
<div class="alert alert-warning d-flex align-items-start gap-2" role="alert" id="installer-locked"
     data-reason="<?php echo Security::escapeAttr($reason); ?>">
  <i class="bi bi-lock-fill fs-4" aria-hidden="true"></i>
  <div>
    <strong>Der Installer ist gesperrt.</strong><br>
    <?php echo Security::escape($text); ?>
  </div>
</div>

<section class="card shadow-sm mb-4">
  <div class="card-body">
    <p>
      <strong>Bitte löschen Sie das Verzeichnis <code>install/</code> vom Server.</strong>
      Für den laufenden Betrieb wird es nicht gebraucht.
    </p>
    <p class="small text-body-secondary mb-0">
      Wirklich neu installieren? Dann entfernen Sie <code>config.local.php</code> und
      <code>install/.installed</code> und verwenden eine leere Datenbank. Für ein Update
      bestehender Installationen ist der Installer nicht nötig (siehe INSTALLATION.md, Abschnitt „Update“).
    </p>
  </div>
  <footer class="card-footer">
    <a class="btn btn-primary" href="../index.php" id="link-forum">
      <i class="bi bi-house-door" aria-hidden="true"></i> Zum Forum
    </a>
  </footer>
</section>
<?php
};
