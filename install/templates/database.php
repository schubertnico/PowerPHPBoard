<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Web-Installer: Schritt 2 (Datenbank)
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

use PowerPHPBoard\CSRF;
use PowerPHPBoard\Installer\Html;
use PowerPHPBoard\Security;

/**
 * @param array<string, string> $old
 * @param array<string, string> $errors
 * @param list<string> $tables
 */
return static function (array $old, array $errors, string $message, array $tables): void {
    ?>
<form method="post" action="index.php?step=2" id="form-database" class="needs-validation" novalidate>
  <?php echo CSRF::getTokenField(); ?>
  <input type="hidden" name="action" value="database">

  <section class="card shadow-sm mb-4">
    <header class="card-header bg-secondary-subtle">
      <h2 class="h5 mb-0"><i class="bi bi-database" aria-hidden="true"></i> Zugang zur Datenbank</h2>
    </header>
    <div class="card-body">
      <p>
        Legen Sie die Datenbank vorher im Kundenmenü Ihres Hosters (oder z. B. in phpMyAdmin) an.
        Dort finden Sie auch Server, Benutzername und Passwort. Die Verbindung wird beim Absenden geprüft.
      </p>

      <?php echo Html::alert($message, $tables === [] ? 'danger' : 'warning'); ?>

      <?php if ($tables !== []): ?>
        <div class="alert alert-light border small" id="existing-tables">
          <strong>Gefundene Tabellen:</strong>
          <?php echo Security::escape(implode(', ', $tables)); ?>
          <hr>
          Für ein <strong>Update</strong> von PowerPHPBoard 2.2.x brauchen Sie den Installer nicht –
          siehe Abschnitt „Update“ in der INSTALLATION.md. Für eine <strong>Neuinstallation</strong>
          wählen Sie bitte eine leere Datenbank oder löschen die Tabellen vorher selbst (Datensicherung!).
        </div>
      <?php endif; ?>

      <div class="row">
        <div class="col-md-8">
          <?php echo Html::input('db_host', 'Datenbank-Server', $old['db_host'] ?? '', $errors, [
              'required' => true,
              'maxlength' => 255,
              'autocomplete' => 'off',
              'spellcheck' => 'false',
          ], 'Meist „localhost“. Manche Hoster nennen einen eigenen Servernamen, z. B. „mysql.example.com“.', 'Bitte geben Sie den Datenbank-Server an.'); ?>
        </div>
        <div class="col-md-4">
          <?php echo Html::input('db_port', 'Port', $old['db_port'] ?? '3306', $errors, [
              'type' => 'number',
              'required' => true,
              'min' => 1,
              'max' => 65535,
              'inputmode' => 'numeric',
          ], 'Standard: 3306', 'Bitte eine Zahl zwischen 1 und 65535 eingeben.'); ?>
        </div>
      </div>

      <?php echo Html::input('db_name', 'Name der Datenbank', $old['db_name'] ?? '', $errors, [
          'required' => true,
          'maxlength' => 64,
          'pattern' => '[A-Za-z0-9_$\-]{1,64}',
          'autocomplete' => 'off',
          'spellcheck' => 'false',
      ], 'Die Datenbank muss bereits existieren und sollte leer sein.', 'Nur Buchstaben, Ziffern sowie _ - $ (höchstens 64 Zeichen).'); ?>

      <?php echo Html::input('db_user', 'Benutzername', $old['db_user'] ?? '', $errors, [
          'required' => true,
          'maxlength' => 80,
          'autocomplete' => 'off',
          'spellcheck' => 'false',
      ], '', 'Bitte geben Sie den Datenbank-Benutzer an.'); ?>

      <?php echo Html::input('db_password', 'Passwort', '', $errors, [
          'type' => 'password',
          'autocomplete' => 'new-password',
      ], 'Wird genau so übernommen, wie Sie es eingeben. Aus Sicherheitsgründen wird es nach einem Fehler nicht wieder angezeigt.'); ?>
    </div>
    <footer class="card-footer d-flex justify-content-between">
      <a class="btn btn-outline-secondary" href="index.php?step=1" id="btn-back">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> Zurück
      </a>
      <button type="submit" class="btn btn-primary" id="btn-database-next">
        Verbindung prüfen und weiter <i class="bi bi-arrow-right" aria-hidden="true"></i>
      </button>
    </footer>
  </section>
</form>
<?php
};
