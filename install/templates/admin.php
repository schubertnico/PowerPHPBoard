<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Web-Installer: Schritt 4 (Administrator)
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

use PowerPHPBoard\CSRF;
use PowerPHPBoard\Installer\FormValidator;
use PowerPHPBoard\Installer\Html;
use PowerPHPBoard\Validator;

/**
 * @param array<string, string> $old
 * @param array<string, string> $errors
 */
return static function (array $old, array $errors, string $message): void {
    ?>
<form method="post" action="index.php?step=4" id="form-admin" class="needs-validation" novalidate>
  <?php echo CSRF::getTokenField(); ?>
  <input type="hidden" name="action" value="admin">

  <section class="card shadow-sm mb-4">
    <header class="card-header bg-secondary-subtle">
      <h2 class="h5 mb-0"><i class="bi bi-person-badge" aria-hidden="true"></i> Ihr Administrator-Konto</h2>
    </header>
    <div class="card-body">
      <p>
        Mit diesem Konto verwalten Sie das Forum. Angemeldet wird später mit
        <strong>E-Mail-Adresse und Passwort</strong>.
      </p>

      <?php echo Html::alert($message); ?>

      <?php echo Html::input('admin_username', 'Benutzername', $old['admin_username'] ?? '', $errors, [
          'required' => true,
          'minlength' => Validator::USERNAME_MIN,
          'maxlength' => Validator::USERNAME_MAX,
          'pattern' => '[A-Za-z0-9._\-]{2,50}',
          'autocomplete' => 'username',
          'spellcheck' => 'false',
      ], '2 bis 50 Zeichen: Buchstaben, Ziffern sowie . _ -', 'Der Benutzername muss 2 bis 50 Zeichen lang sein und darf nur Buchstaben, Ziffern sowie . _ - enthalten.'); ?>

      <?php echo Html::input('admin_email', 'E-Mail-Adresse', $old['admin_email'] ?? '', $errors, [
          'type' => 'email',
          'required' => true,
          'maxlength' => FormValidator::EMAIL_MAX,
          'autocomplete' => 'email',
      ], '', 'Bitte eine gültige E-Mail-Adresse angeben.'); ?>

      <div class="row">
        <div class="col-md-6">
          <?php echo Html::input('admin_password', 'Passwort', '', $errors, [
              'type' => 'password',
              'required' => true,
              'minlength' => Validator::PASSWORD_MIN,
              'maxlength' => 255,
              'autocomplete' => 'new-password',
          ], 'Mindestens ' . Validator::PASSWORD_MIN . ' Zeichen. Wird als Argon2id-Hash gespeichert.', 'Mindestens ' . Validator::PASSWORD_MIN . ' Zeichen erforderlich.'); ?>
        </div>
        <div class="col-md-6">
          <?php echo Html::input('admin_password_confirm', 'Passwort wiederholen', '', $errors, [
              'type' => 'password',
              'required' => true,
              'minlength' => Validator::PASSWORD_MIN,
              'maxlength' => 255,
              'autocomplete' => 'new-password',
          ], '', 'Bitte wiederholen Sie das Passwort.'); ?>
        </div>
      </div>
    </div>
    <footer class="card-footer d-flex justify-content-between">
      <a class="btn btn-outline-secondary" href="index.php?step=3" id="btn-back">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> Zurück
      </a>
      <button type="submit" class="btn btn-primary" id="btn-admin-next">
        Weiter zur Zusammenfassung <i class="bi bi-arrow-right" aria-hidden="true"></i>
      </button>
    </footer>
  </section>
</form>
<?php
};
