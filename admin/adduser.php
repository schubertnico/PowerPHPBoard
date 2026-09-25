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
    $hideemail = ppb_hide_email(Security::getString('hideemail', 'POST'));

    if ($username === '' || $email1 === '' || $email2 === '' || $password1 === '' || $password2 === '') {
        $formError = $lang_adm_fillrequired ?? 'Please fill in all required fields.';
    } elseif (!Validator::isValidUsername($username)) {
        $formError = $lang_usernameinvalid ?? 'The username must be 2 to 50 characters long and may only contain letters, digits and . _ -';
    } elseif ($email1 !== $email2) {
        $formError = $lang_adm_emailsdifferent ?? 'The email addresses do not match.';
    } elseif (!Security::isValidEmail($email1)) {
        $formError = $lang_insertvalidemail ?? 'Please enter a valid email address.';
    } elseif ($password1 !== $password2) {
        $formError = $lang_adm_pwdsdifferent ?? 'The passwords do not match.';
    } elseif (!Validator::isStrongPassword($password1)) {
        $formError = $lang_pwdtooshort ?? 'The password must be at least 8 characters long.';
    } elseif (Validator::normalizeHomepage($homepage) === null) {
        $formError = $lang_homepageinvalid ?? 'Please enter a valid homepage address starting with http:// or https://.';
    } else {
        $homepage = (string) Validator::normalizeHomepage($homepage);
        $existingUser = $db->fetchOne('SELECT id FROM ppb_users WHERE email = ?', [$email1]);
        if ($existingUser !== null) {
            $formError = $lang_emailalreadyexists ?? 'This email address is already registered.';
        } elseif ($db->fetchOne('SELECT id FROM ppb_users WHERE username = ?', [$username]) !== null) {
            $formError = $lang_usernametaken ?? 'This username is already taken.';
        } else {
            $icqInt = (int) $icq;
            $passwordHash = Security::hashPassword($password1);
            $biography = strip_tags($biography);
            $now = time();
            try {
                $db->execute(
                    "INSERT INTO ppb_users (username, email, password, homepage, icq, biography, signature, hideemail, status, registered) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Normal user', ?)",
                    [$username, $email1, $passwordHash, $homepage, $icqInt, $biography, $signature, $hideemail, $now]
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
                $formError = $lang_adm_usercreatefailed ?? 'The user could not be created.';
            }
        }
    }
}

// Nach einem Fehler die Eingaben (außer Passwörtern) wieder anzeigen
$old = static fn (string $field, string $default = ''): string => ($formError !== '' && !$saved)
    ? Security::getString($field, 'POST', $default)
    : $default;
// Neues Formular: Adresse verbergen ist vorausgewählt
$hideChoice = ppb_hide_email($old('hideemail'));
?>

<header class="mb-3">
  <h1 class="h3 mb-0"><i class="bi bi-person-plus" aria-hidden="true"></i> <?php echo Security::escape($lang_adm_adduser ?? 'Add user'); ?></h1>
</header>

<?php if ($saved): ?>
  <div class="alert alert-success" role="alert">
    <?php echo sprintf(
        Security::escape($lang_adm_usercreated ?? 'The user %s has been created.'),
        '<strong>' . Security::escape($savedUsername) . '</strong>'
    ); ?>
    <?php echo Security::escape($mailSent
        ? ($lang_adm_mailsent ?? 'A notification has been sent by email.')
        : ($lang_adm_mailfailed ?? 'The email notification could not be sent (see the error log for details).')); ?>
    <a class="alert-link" href="user.php"><?php echo Security::escape($lang_adm_tousermanagement ?? 'To user management'); ?></a>
  </div>
<?php endif; ?>
<?php if ($formError !== ''): ?>
  <div class="alert alert-danger" role="alert"><?php echo Security::escape($formError); ?></div>
<?php endif; ?>

<form action="adduser.php?adduser=1" method="post" class="needs-validation" novalidate>
  <?php echo CSRF::getTokenField(); ?>

  <section class="card shadow-sm mb-3">
    <header class="card-header bg-secondary-subtle">
      <h2 class="h6 mb-0"><i class="bi bi-asterisk" aria-hidden="true"></i> <?php echo Security::escape($lang_requiredinfo ?? 'Required information'); ?></h2>
    </header>
    <div class="card-body">
      <div class="mb-3">
        <label for="username" class="form-label fw-semibold"><?php echo Security::escape($lang_username ?? 'Username'); ?> <span class="text-danger" aria-hidden="true">*</span></label>
        <input id="username" name="username" type="text" class="form-control" maxlength="50" required
               value="<?php echo Security::escape($old('username')); ?>" aria-describedby="usernameHelp">
        <div id="usernameHelp" class="form-text"><?php echo Security::escape($lang_usernamehelp ?? '2 to 50 characters: letters (no umlauts), digits and . _ -'); ?></div>
        <div class="invalid-feedback"><?php echo Security::escape($lang_adm_insertusername ?? 'Please enter a username.'); ?></div>
      </div>
      <div class="row g-3">
        <div class="col-md-6">
          <label for="email1" class="form-label fw-semibold"><?php echo Security::escape($lang_email ?? 'Email'); ?> <span class="text-danger" aria-hidden="true">*</span></label>
          <input id="email1" name="email1" type="email" class="form-control" maxlength="100" required
                 value="<?php echo Security::escape($old('email1')); ?>">
          <div class="invalid-feedback"><?php echo Security::escape($lang_insertvalidemail ?? 'Please enter a valid email address.'); ?></div>
        </div>
        <div class="col-md-6">
          <label for="email2" class="form-label fw-semibold"><?php echo Security::escape($lang_email ?? 'Email'); ?> <small class="text-body-secondary">(<?php echo Security::escape($lang_confirmation ?? 'Confirmation'); ?>)</small></label>
          <input id="email2" name="email2" type="email" class="form-control" maxlength="100" required
                 value="<?php echo Security::escape($old('email2')); ?>">
          <div class="invalid-feedback"><?php echo Security::escape($lang_insertvalidemail ?? 'Please enter a valid email address.'); ?></div>
        </div>
        <div class="col-md-6">
          <label for="password1" class="form-label fw-semibold"><?php echo Security::escape($lang_password ?? 'Password'); ?> <span class="text-danger" aria-hidden="true">*</span></label>
          <input id="password1" name="password1" type="password" class="form-control" minlength="8" maxlength="255" required autocomplete="new-password"
                 aria-describedby="password1Help">
          <div id="password1Help" class="form-text"><?php echo Security::escape($lang_pwdminlength ?? 'At least 8 characters.'); ?></div>
          <div class="invalid-feedback"><?php echo Security::escape($lang_pwdtooshort ?? 'The password must be at least 8 characters long.'); ?></div>
        </div>
        <div class="col-md-6">
          <label for="password2" class="form-label fw-semibold"><?php echo Security::escape($lang_password ?? 'Password'); ?> <small class="text-body-secondary">(<?php echo Security::escape($lang_confirmation ?? 'Confirmation'); ?>)</small></label>
          <input id="password2" name="password2" type="password" class="form-control" minlength="8" maxlength="255" required autocomplete="new-password">
          <div class="invalid-feedback"><?php echo Security::escape($lang_repeatpwd ?? 'Please enter the password again.'); ?></div>
        </div>
      </div>
    </div>
  </section>

  <section class="card shadow-sm mb-3">
    <header class="card-header bg-secondary-subtle">
      <h2 class="h6 mb-0"><i class="bi bi-person-plus" aria-hidden="true"></i> <?php echo Security::escape($lang_optionalinfo ?? 'Optional information'); ?></h2>
    </header>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-8">
          <label for="homepage" class="form-label"><?php echo Security::escape($lang_homepage ?? 'Homepage'); ?></label>
          <input id="homepage" name="homepage" type="text" inputmode="url" class="form-control" maxlength="150"
                 placeholder="https://example.org" value="<?php echo Security::escape($old('homepage')); ?>"
                 aria-describedby="homepageHelp">
          <div id="homepageHelp" class="form-text"><?php echo Security::escape($lang_homepagehelp ?? 'Optional. https:// is added automatically if missing.'); ?></div>
        </div>
        <div class="col-md-4">
          <label for="icq" class="form-label"><?php echo Security::escape($lang_icq ?? 'ICQ number'); ?></label>
          <input id="icq" name="icq" type="number" class="form-control" maxlength="10" min="0"
                 value="<?php echo Security::escape($old('icq')); ?>">
        </div>
        <div class="col-12">
          <label for="biography" class="form-label"><?php echo Security::escape($lang_biography ?? 'Biography'); ?></label>
          <textarea id="biography" name="biography" class="form-control" rows="3"><?php echo Security::escape($old('biography')); ?></textarea>
        </div>
        <div class="col-12">
          <label for="signature" class="form-label"><?php echo Security::escape($lang_signature ?? 'Signature'); ?></label>
          <textarea id="signature" name="signature" class="form-control" rows="3"><?php echo Security::escape($old('signature')); ?></textarea>
        </div>
        <div class="col-md-6">
          <fieldset>
            <legend class="form-label fw-semibold mb-1 fs-6"><?php echo Security::escape($lang_hideemail ?? 'Hide email address?'); ?></legend>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" id="hideY" name="hideemail" value="YES" <?php echo $hideChoice === 'YES' ? 'checked' : ''; ?>>
              <label class="form-check-label" for="hideY"><?php echo Security::escape($lang_yes ?? 'yes'); ?></label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" id="hideN" name="hideemail" value="NO" <?php echo $hideChoice === 'NO' ? 'checked' : ''; ?>>
              <label class="form-check-label" for="hideN"><?php echo Security::escape($lang_no ?? 'no'); ?></label>
            </div>
          </fieldset>
        </div>
      </div>
    </div>
  </section>

  <div class="d-flex flex-wrap gap-2 mb-4">
    <button type="submit" class="btn btn-primary">
      <i class="bi bi-person-plus" aria-hidden="true"></i> <?php echo Security::escape($lang_adm_adduser ?? 'Add user'); ?>
    </button>
    <button type="reset" class="btn btn-outline-secondary">
      <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> <?php echo Security::escape($lang_reset ?? 'Reset'); ?>
    </button>
    <a class="btn btn-link" href="user.php"><?php echo Security::escape($lang_adm_backtousermanagement ?? 'Back to user management'); ?></a>
  </div>
</form>

<?php include __DIR__ . '/footer.inc.php'; ?>
