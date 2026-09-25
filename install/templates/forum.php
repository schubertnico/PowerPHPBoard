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

/**
 * @param array<string, string> $old
 * @param array<string, string> $errors
 */
return static function (array $old, array $errors, string $message): void {
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

  <section class="card shadow-sm mb-4">
    <header class="card-header bg-secondary-subtle">
      <h2 class="h5 mb-0"><i class="bi bi-envelope" aria-hidden="true"></i> E-Mail-Versand <span class="badge text-bg-light border align-middle">optional</span></h2>
    </header>
    <div class="card-body">
      <p class="small text-body-secondary">
        PowerPHPBoard verschickt E-Mails (Registrierung, Passwort vergessen) direkt per SMTP,
        ohne Anmeldung am Mailserver. Wenn Sie unsicher sind, lassen Sie die Vorgaben stehen –
        die Werte lassen sich später in <code>config.local.php</code> ändern. Ein leeres Feld
        „SMTP-Server“ übernimmt die eingebauten Standardwerte.
      </p>
      <div class="row">
        <div class="col-md-8">
          <?php echo Html::input('smtp_host', 'SMTP-Server', $old['smtp_host'] ?? '', $errors, [
              'maxlength' => 255,
              'spellcheck' => 'false',
          ]); ?>
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
      <?php echo Html::input('smtp_from', 'Absenderadresse', $old['smtp_from'] ?? '', $errors, [
          'type' => 'email',
          'maxlength' => FormValidator::EMAIL_MAX,
      ], 'Leer lassen, um die E-Mail-Adresse des Forums zu verwenden.', 'Bitte eine gültige E-Mail-Adresse angeben.'); ?>
    </div>
  </section>

  <div class="d-flex justify-content-between mb-4">
    <a class="btn btn-outline-secondary" href="index.php?step=2" id="btn-back">
      <i class="bi bi-arrow-left" aria-hidden="true"></i> Zurück
    </a>
    <button type="submit" class="btn btn-primary" id="btn-forum-next">
      Weiter zum Administrator <i class="bi bi-arrow-right" aria-hidden="true"></i>
    </button>
  </div>
</form>
<?php
};
