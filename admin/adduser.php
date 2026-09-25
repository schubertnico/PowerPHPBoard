<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Add User
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

use PowerPHPBoard\CSRF;
use PowerPHPBoard\ErrorHandler;
use PowerPHPBoard\Mailer;
use PowerPHPBoard\Security;
use PowerPHPBoard\Validator;

include __DIR__ . '/header.inc.php';

$adduser = Security::getInt('adduser', 'GET', 0);
$saved = false;
$savedUsername = '';
$mailSent = true;
$formError = '';

if ($adduser === 1 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::validateOrDie();
    $username = Security::getString('username', 'POST');
    $email1 = Security::getString('email1', 'POST');
    $email2 = Security::getString('email2', 'POST');
    $password1 = Security::getString('password1', 'POST');
    $password2 = Security::getString('password2', 'POST');
    $homepage = Security::getString('homepage', 'POST');
    $icq = Security::getString('icq', 'POST');
    $biography = Security::getString('biography', 'POST');
    $signature = Security::getString('signature', 'POST');
    $hideemail = Security::getString('hideemail', 'POST', 'NO');
    $logincookie = Security::getString('logincookie', 'POST', 'YES');
    $hideemail = $hideemail === 'YES' ? 'YES' : 'NO';
    $logincookie = $logincookie === 'NO' ? 'NO' : 'YES';

    if ($username === '' || $email1 === '' || $email2 === '' || $password1 === '' || $password2 === '') {
        $formError = 'Bitte fülle alle Pflichtfelder aus.';
    } elseif (!Validator::isValidUsername($username)) {
        $formError = 'Der Benutzername muss 2 bis 50 Zeichen lang sein und darf nur Buchstaben, Ziffern sowie . _ - enthalten.';
    } elseif ($email1 !== $email2) {
        $formError = 'Die E-Mail-Adressen stimmen nicht überein.';
    } elseif (!Security::isValidEmail($email1)) {
        $formError = 'Bitte eine gültige E-Mail-Adresse angeben.';
    } elseif ($password1 !== $password2) {
        $formError = 'Die Passwörter stimmen nicht überein.';
    } elseif (!Validator::isStrongPassword($password1)) {
        $formError = 'Das Passwort muss mindestens 8 Zeichen lang sein.';
    } elseif (Validator::normalizeHomepage($homepage) === null) {
        $formError = 'Bitte eine gültige Homepage-Adresse mit http:// oder https:// angeben.';
    } else {
        $homepage = (string) Validator::normalizeHomepage($homepage);
        $existingUser = $db->fetchOne('SELECT id FROM ppb_users WHERE email = ?', [$email1]);
        if ($existingUser !== null) {
            $formError = 'Diese E-Mail-Adresse ist bereits registriert.';
        } elseif ($db->fetchOne('SELECT id FROM ppb_users WHERE username = ?', [$username]) !== null) {
            $formError = 'Dieser Benutzername ist bereits vergeben.';
        } else {
            $icqInt = (int) $icq;
            $passwordHash = Security::hashPassword($password1);
            $biography = strip_tags($biography);
            $now = time();
            try {
                $db->execute(
                    "INSERT INTO ppb_users (username, email, password, homepage, icq, biography, signature, hideemail, logincookie, status, registered) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Normal user', ?)",
                    [$username, $email1, $passwordHash, $homepage, $icqInt, $biography, $signature, $hideemail, $logincookie, $now]
                );
                CSRF::regenerate();
                // Benachrichtigung über SMTP; das Passwort steht nicht in der Mail
                $mailSent = Mailer::fromConfig($mail ?? [])->send(
                    $email1,
                    Mailer::senderAddress($settings, $mail ?? []),
                    ($settings['boardtitle'] ?? 'PowerPHPBoard') . ' – ' . ($lang_registration ?? 'Registration'),
                    ppb_welcome_mail_text($settings, $username, $email1, true)
                );
                if (!$mailSent) {
                    ErrorHandler::logConfigurationError('Benachrichtigung an neu angelegten Benutzer „' . $username . '“ konnte nicht versendet werden (SMTP-Einstellungen prüfen).');
                }
                $saved = true;
                $savedUsername = $username;
            } catch (Exception) {
                $formError = 'Fehler beim Anlegen des Nutzers.';
            }
        }
    }
}
?>

<header class="mb-3">
  <h1 class="h3 mb-0"><i class="bi bi-person-plus" aria-hidden="true"></i> Nutzer anlegen</h1>
</header>

<?php if ($saved): ?>
  <div class="alert alert-success" role="alert">
    Nutzer <strong><?php echo Security::escape($savedUsername); ?></strong> wurde angelegt.
    <?php echo $mailSent
        ? 'Eine Benachrichtigung wurde per E-Mail versendet.'
        : 'Die Benachrichtigung per E-Mail konnte nicht versendet werden (Details im Fehlerprotokoll).'; ?>
    <a class="alert-link" href="user.php?username=<?php echo urlencode($savedUsername); ?>">Zur Nutzerverwaltung</a>.
  </div>
<?php endif; ?>
<?php if ($formError !== ''): ?>
  <div class="alert alert-danger" role="alert"><?php echo Security::escape($formError); ?></div>
<?php endif; ?>

<?php
// Nach einem Fehler die Eingaben (außer Passwörtern) wieder anzeigen
$old = static fn (string $field, string $default = ''): string => ($formError !== '' && !$saved)
    ? Security::getString($field, 'POST', $default)
    : $default;
?>
<form action="adduser.php?adduser=1" method="post" class="needs-validation" novalidate>
  <?php echo CSRF::getTokenField(); ?>

  <section class="card shadow-sm mb-3">
    <header class="card-header bg-secondary-subtle">
      <h2 class="h6 mb-0"><i class="bi bi-asterisk" aria-hidden="true"></i> Pflichtangaben</h2>
    </header>
    <div class="card-body">
      <div class="mb-3">
        <label for="username" class="form-label fw-semibold">Benutzername <span class="text-danger" aria-hidden="true">*</span></label>
        <input id="username" name="username" type="text" class="form-control" maxlength="50" required
               value="<?php echo Security::escape($old('username')); ?>">
        <div class="invalid-feedback">Bitte einen Benutzernamen angeben.</div>
      </div>
      <div class="row g-3">
        <div class="col-md-6">
          <label for="email1" class="form-label fw-semibold">E-Mail <span class="text-danger" aria-hidden="true">*</span></label>
          <input id="email1" name="email1" type="email" class="form-control" maxlength="100" required
                 value="<?php echo Security::escape($old('email1')); ?>">
          <div class="invalid-feedback">Bitte eine gültige E-Mail eingeben.</div>
        </div>
        <div class="col-md-6">
          <label for="email2" class="form-label fw-semibold">E-Mail <small class="text-body-secondary">(Bestätigung)</small></label>
          <input id="email2" name="email2" type="email" class="form-control" maxlength="100" required
                 value="<?php echo Security::escape($old('email2')); ?>">
          <div class="invalid-feedback">Bitte zur Bestätigung wiederholen.</div>
        </div>
        <div class="col-md-6">
          <label for="password1" class="form-label fw-semibold">Passwort <span class="text-danger" aria-hidden="true">*</span></label>
          <input id="password1" name="password1" type="password" class="form-control" minlength="8" maxlength="255" required autocomplete="new-password">
          <div class="invalid-feedback">Mindestens 8 Zeichen.</div>
        </div>
        <div class="col-md-6">
          <label for="password2" class="form-label fw-semibold">Passwort <small class="text-body-secondary">(Bestätigung)</small></label>
          <input id="password2" name="password2" type="password" class="form-control" minlength="8" maxlength="255" required autocomplete="new-password">
          <div class="invalid-feedback">Bitte zur Bestätigung wiederholen.</div>
        </div>
      </div>
    </div>
  </section>

  <section class="card shadow-sm mb-3">
    <header class="card-header bg-secondary-subtle">
      <h2 class="h6 mb-0"><i class="bi bi-person-plus" aria-hidden="true"></i> Optionale Angaben</h2>
    </header>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-8">
          <label for="homepage" class="form-label">Homepage</label>
          <input id="homepage" name="homepage" type="text" inputmode="url" class="form-control" maxlength="150"
                 placeholder="https://example.org" value="<?php echo Security::escape($old('homepage')); ?>"
                 aria-describedby="homepageHelp">
          <div id="homepageHelp" class="form-text">Optional. Fehlt https://, wird es ergänzt.</div>
        </div>
        <div class="col-md-4">
          <label for="icq" class="form-label">ICQ</label>
          <input id="icq" name="icq" type="number" class="form-control" maxlength="10" min="0"
                 value="<?php echo Security::escape($old('icq')); ?>">
        </div>
        <div class="col-12">
          <label for="biography" class="form-label">Biografie</label>
          <textarea id="biography" name="biography" class="form-control" rows="3"><?php echo Security::escape($old('biography')); ?></textarea>
        </div>
        <div class="col-12">
          <label for="signature" class="form-label">Signatur</label>
          <textarea id="signature" name="signature" class="form-control" rows="3"><?php echo Security::escape($old('signature')); ?></textarea>
        </div>
        <div class="col-md-6">
          <fieldset>
            <legend class="form-label fw-semibold mb-1 fs-6">E-Mail verbergen</legend>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" id="hideY" name="hideemail" value="YES" <?php echo $old('hideemail', 'NO') === 'YES' ? 'checked' : ''; ?>>
              <label class="form-check-label" for="hideY">ja</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" id="hideN" name="hideemail" value="NO" <?php echo $old('hideemail', 'NO') !== 'YES' ? 'checked' : ''; ?>>
              <label class="form-check-label" for="hideN">nein</label>
            </div>
          </fieldset>
        </div>
        <div class="col-md-6">
          <fieldset>
            <legend class="form-label fw-semibold mb-1 fs-6">Login merken</legend>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" id="cookY" name="logincookie" value="YES" <?php echo $old('logincookie', 'YES') !== 'NO' ? 'checked' : ''; ?>>
              <label class="form-check-label" for="cookY">ja</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" id="cookN" name="logincookie" value="NO" <?php echo $old('logincookie', 'YES') === 'NO' ? 'checked' : ''; ?>>
              <label class="form-check-label" for="cookN">nein</label>
            </div>
          </fieldset>
        </div>
      </div>
    </div>
  </section>

  <div class="d-flex flex-wrap gap-2 mb-4">
    <button type="submit" class="btn btn-primary">
      <i class="bi bi-person-plus" aria-hidden="true"></i> Nutzer anlegen
    </button>
    <button type="reset" class="btn btn-outline-secondary">
      <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Zurücksetzen
    </button>
    <a class="btn btn-link" href="user.php">Zurück zur Nutzerverwaltung</a>
  </div>
</form>

<?php include __DIR__ . '/footer.inc.php'; ?>
