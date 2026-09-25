<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Web-Installer: Schritt 3 (Forum)
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

use PowerPHPBoard\CSRF;
use PowerPHPBoard\Installer\FormValidator;
use PowerPHPBoard\Installer\Html;
use PowerPHPBoard\Installer\Wizard;
use PowerPHPBoard\Mailer;

/**
 * @param array<string, string> $old
 * @param array<string, string> $errors
 * @param bool $passwordStored ein SMTP-Passwort liegt bereits in der Sitzung
 * @param array{type: string, message: string}|null $notice Ergebnis der Test-Mail
 */
return static function (array $old, array $errors, string $message, bool $passwordStored, ?array $notice): void {
    $passwordHelp = 'Wird genau so übernommen, wie Sie es eingeben, und nicht wieder angezeigt.'
        . ($passwordStored ? ' Leer lassen, um das bereits eingegebene Passwort zu behalten.' : '');
    ?>
<form method="post" action="index.php?step=3" id="form-forum" class="needs-validation" novalidate>
  <?php echo CSRF::getTokenField(); ?>
  <input type="hidden" name="action" value="forum">

  <?php echo Html::alert($message); ?>

  <section class="card shadow-sm mb-4">
    <header class="card-header bg-secondary-subtle">
      <h2 class="h5 mb-0"><i class="bi bi-chat-square-text" aria-hidden="true"></i> Ihr Forum</h2>
    </header>
    <div class="card-body">
      <?php echo Html::input('board_title', 'Name des Forums', $old['board_title'] ?? '', $errors, [
          'required' => true,
          'maxlength' => FormValidator::BOARDTITLE_MAX,
      ], 'Erscheint in der Navigationsleiste und im Browser-Tab.', 'Bitte geben Sie einen Namen an.'); ?>

      <?php echo Html::input('board_url', 'Adresse des Forums (URL)', $old['board_url'] ?? '', $errors, [
          'type' => 'url',
          'required' => true,
          'maxlength' => FormValidator::BOARDURL_MAX,
          'spellcheck' => 'false',
      ], 'Bitte prüfen: Vorbelegt mit der Adresse, unter der Sie den Installer gerade aufrufen. Sie wird für Links in E-Mails verwendet (z. B. „Passwort vergessen“) und muss mit http:// oder https:// beginnen.', 'Bitte eine vollständige Adresse mit http:// oder https:// angeben.'); ?>

      <?php echo Html::input('board_email', 'E-Mail-Adresse des Forums', $old['board_email'] ?? '', $errors, [
          'type' => 'email',
          'required' => true,
          'maxlength' => FormValidator::EMAIL_MAX,
          'autocomplete' => 'email',
      ], 'Absender der Forum-E-Mails und Kontaktadresse. Später im Adminbereich änderbar.', 'Bitte eine gültige E-Mail-Adresse angeben.'); ?>

      <?php echo Html::select(
          'board_language',
          'Sprache des Forums',
          FormValidator::LANGUAGES,
          $old['board_language'] ?? 'Deutsch-Du',
          $errors,
          'Gilt für alle Besucher und lässt sich später im Adminbereich unter „Allgemein“ ändern.'
      ); ?>

      <p class="small text-body-secondary mb-0" id="board-security-defaults">
        <i class="bi bi-shield-check" aria-hidden="true"></i>
        Sichere Voreinstellung: HTML in Beiträgen ist ausgeschaltet, BBCode und Smilies sind eingeschaltet.
      </p>
    </div>
  </section>

  <section class="card shadow-sm mb-4" id="smtp-settings">
    <header class="card-header bg-secondary-subtle">
      <h2 class="h5 mb-0"><i class="bi bi-envelope" aria-hidden="true"></i> E-Mail-Versand <span class="badge text-bg-light border align-middle">optional</span></h2>
    </header>
    <div class="card-body">
      <?php echo $notice !== null ? Html::alert($notice['message'], $notice['type'], 'smtp-test-result') : ''; ?>

      <p class="small text-body-secondary">
        PowerPHPBoard verschickt E-Mails (Registrierung, Passwort vergessen, Nachrichten an Mitglieder) per SMTP.
        Die meisten Hoster verlangen dafür eine Anmeldung mit dem Benutzernamen und Passwort eines E-Mail-Postfachs
        und eine verschlüsselte Verbindung.
        <strong id="smtp-help">Die Angaben finden Sie im Kundenmenü Ihres Hosters beim E-Mail-Postfach.</strong>
        Ein leeres Feld „SMTP-Server“ übernimmt die eingebauten Standardwerte; alle Werte lassen sich später
        in <code>config.local.php</code> ändern.
      </p>
      <div class="row">
        <div class="col-md-8">
          <?php echo Html::input('smtp_host', 'SMTP-Server', $old['smtp_host'] ?? '', $errors, [
              'maxlength' => FormValidator::HOST_MAX,
              'spellcheck' => 'false',
          ], 'Postausgangsserver, z. B. „smtp.ihr-hoster.de“. Bei Verschlüsselung den Namen aus dem Zertifikat verwenden, nicht „localhost“.'); ?>
        </div>
        <div class="col-md-4">
          <?php echo Html::input('smtp_port', 'SMTP-Port', $old['smtp_port'] ?? '25', $errors, [
              'type' => 'number',
              'min' => 1,
              'max' => 65535,
              'inputmode' => 'numeric',
          ], '', 'Bitte eine Zahl zwischen 1 und 65535 eingeben.'); ?>
        </div>
      </div>

      <?php echo Html::select(
          'smtp_encryption',
          'Verschlüsselung',
          FormValidator::ENCRYPTIONS,
          $old['smtp_encryption'] ?? Mailer::ENCRYPTION_NONE,
          $errors,
          'Ohne Verschlüsselung gehen Passwort und E-Mails im Klartext durchs Netz – das passt nur für einen Mailserver auf demselben Rechner oder im internen Netz.',
          false
      ); ?>

      <div class="row">
        <div class="col-md-6">
          <?php echo Html::input('smtp_user', 'Benutzername', $old['smtp_user'] ?? '', $errors, [
              'maxlength' => FormValidator::SMTP_USER_MAX,
              'autocomplete' => 'off',
              'spellcheck' => 'false',
          ], 'Meist die vollständige E-Mail-Adresse des Postfachs. Leer lassen, wenn der Server keine Anmeldung verlangt.'); ?>
        </div>
        <div class="col-md-6">
          <?php echo Html::input('smtp_password', 'Passwort', '', $errors, [
              'type' => 'password',
              'maxlength' => FormValidator::SMTP_PASSWORD_MAX,
              'autocomplete' => 'new-password',
          ], $passwordHelp, 'Bitte geben Sie das Passwort des E-Mail-Postfachs an.'); ?>
        </div>
      </div>

      <?php echo Html::input('smtp_from', 'Absenderadresse', $old['smtp_from'] ?? '', $errors, [
          'type' => 'email',
          'maxlength' => FormValidator::EMAIL_MAX,
      ], 'Absender (From) aller Mails des Forums – bei vielen Hostern muss er zum Postfach bzw. zu Ihrer Domain passen. Antworten gehen an die E-Mail-Adresse des Forums. Leer lassen, um die E-Mail-Adresse des Forums als Absender zu verwenden.', 'Bitte eine gültige E-Mail-Adresse angeben.'); ?>

      <p class="small text-body-secondary mb-0" id="smtp-test-hint">
        <i class="bi bi-send" aria-hidden="true"></i>
        „Test-Mail senden“ speichert die Angaben und schickt eine Nachricht an die E-Mail-Adresse des Forums.
      </p>
    </div>
  </section>

  <div class="d-flex flex-wrap justify-content-between gap-2 mb-4">
    <a class="btn btn-outline-secondary" href="index.php?step=2" id="btn-back">
      <i class="bi bi-arrow-left" aria-hidden="true"></i> Zurück
    </a>
    <!-- „Weiter“ steht im Quelltext zuerst: Die Eingabetaste löst so nie die Test-Mail aus. -->
    <div class="d-flex flex-row-reverse flex-wrap gap-2">
      <button type="submit" class="btn btn-primary" id="btn-forum-next">
        Weiter zum Administrator <i class="bi bi-arrow-right" aria-hidden="true"></i>
      </button>
      <button type="submit" name="action" value="<?php echo Wizard::ACTION_SMTP_TEST; ?>" class="btn btn-outline-primary" id="btn-smtp-test">
        <i class="bi bi-send" aria-hidden="true"></i> Test-Mail senden
      </button>
    </div>
  </div>
</form>
<script>
// Port-Vorschlag passend zur Verschlüsselung, solange der Port nicht von Hand geändert wurde.
// Ohne JavaScript ergibt ein leerer Port denselben Vorschlag auf dem Server.
(function () {
  var encryption = document.getElementById('smtp_encryption');
  var port = document.getElementById('smtp_port');
  if (!encryption || !port) {
    return;
  }
  var ports = <?php echo json_encode(array_map('strval', Mailer::DEFAULT_PORTS), JSON_THROW_ON_ERROR | JSON_HEX_TAG); ?>;
  var suggested = Object.keys(ports).map(function (key) { return ports[key]; });
  var edited = port.value.trim() !== '' && suggested.indexOf(port.value.trim()) === -1;
  port.addEventListener('input', function () {
    edited = true;
  });
  encryption.addEventListener('change', function () {
    if (!edited && ports[encryption.value]) {
      port.value = ports[encryption.value];
    }
  });
})();
</script>
<?php
};
