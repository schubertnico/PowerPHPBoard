<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - User Registration
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

use PowerPHPBoard\CSRF;
use PowerPHPBoard\ErrorHandler;
use PowerPHPBoard\Mailer;
use PowerPHPBoard\Security;
use PowerPHPBoard\Validator;

include __DIR__ . '/header.inc.php';

$acception = Security::getInt('acception', 'REQUEST');
$register = Security::getInt('register', 'POST');
$registrationDone = false;
$registrationMailSent = true;
$formError = '';

if ($acception === 0) {
    // Display board rules acceptance page
    ?>
  <div class="row justify-content-center">
    <div class="col-lg-9">
      <section class="card shadow-sm">
        <header class="card-header bg-secondary-subtle">
          <h1 class="h5 mb-0">
            <i class="bi bi-journal-text" aria-hidden="true"></i>
            <?php echo Security::escape($lang_boardrules ?? 'Board Rules'); ?>
          </h1>
        </header>
        <div class="card-body">
          <p class="mb-4">
            <?php echo Security::escape($lang_boardrulescontent ?? 'Please read and accept the board rules.'); ?>
          </p>
          <div class="d-flex flex-wrap gap-2">
            <form action="register.php" method="get" class="d-inline">
              <input type="hidden" name="acception" value="1">
              <input type="hidden" name="catid" value="<?php echo (int) $catid; ?>">
              <input type="hidden" name="boardid" value="<?php echo (int) $boardid; ?>">
              <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-circle" aria-hidden="true"></i>
                <?php echo Security::escape($lang_agree ?? 'I Agree'); ?>
              </button>
            </form>
            <form action="index.php" method="get" class="d-inline">
              <button type="submit" class="btn btn-outline-secondary">
                <i class="bi bi-x-circle" aria-hidden="true"></i>
                <?php echo Security::escape($lang_disagree ?? 'I Disagree'); ?>
              </button>
            </form>
          </div>
        </div>
      </section>
    </div>
  </div>
<?php
} else {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $register === 1) {
        if (!CSRF::validateFromPost()) {
            $formError = $lang_csrfinvalid ?? 'The security token is invalid. Please reload the page and try again.';
        } else {
            $username = Security::getString('username', 'POST');
            $email1 = Security::getString('email1', 'POST');
            $email2 = Security::getString('email2', 'POST');
            $password1 = Security::getString('password1', 'POST');
            $password2 = Security::getString('password2', 'POST');
            $homepage = Security::getString('homepage', 'POST');
            $icq = Security::getString('icq', 'POST');
            $biography = Security::getString('biography', 'POST');
            $signature = Security::getString('signature', 'POST');
            $hideemail = Security::getString('hideemail', 'POST');

            if ($username === '' || $username === '0' || ($email1 === '' || $email1 === '0') || ($email2 === '' || $email2 === '0') || ($password1 === '' || $password1 === '0') || ($password2 === '' || $password2 === '0')) {
                $formError = $lang_insertvaluesforall ?? 'Please fill in all required fields';
            } elseif ($email1 !== $email2) {
                $formError = $lang_emailsdifferent ?? 'Email addresses do not match';
            } elseif (!Security::isValidEmail($email1)) {
                $formError = $lang_emailnotcorrect ?? 'Invalid email address format';
            } elseif ($password1 !== $password2) {
                $formError = $lang_pwdsdifferent ?? 'Passwords do not match';
            } elseif (!Validator::isStrongPassword($password1)) {
                $formError = $lang_pwdtooshort ?? 'Password must be at least 8 characters';
            } elseif (!Validator::isValidUsername($username)) {
                $formError = $lang_usernameinvalid ?? 'Username must be 2-50 chars and only contain letters, digits, . _ -';
            } elseif (!Validator::withinLength($biography, Validator::BIOGRAPHY_MAX)
                || !Validator::withinLength($signature, Validator::SIGNATURE_MAX)
                || !Validator::withinLength($homepage, Validator::HOMEPAGE_MAX)) {
                $formError = $lang_inputstoolong ?? 'One or more fields exceed the allowed length';
            } elseif (Validator::normalizeHomepage($homepage) === null) {
                $formError = $lang_homepageinvalid ?? 'Please enter a valid homepage address starting with http:// or https://.';
            } else {
                $icqNum = 0;
                $icqValid = true;
                if ($icq !== '' && $icq !== '0') {
                    $filtered = filter_var($icq, FILTER_VALIDATE_INT);
                    if ($filtered === false) {
                        $formError = $lang_icqnotcorrect ?? 'ICQ number must be numeric';
                        $icqValid = false;
                    } else {
                        $icqNum = $filtered;
                    }
                }

                if ($icqValid) {
                    $existing = $db->fetchOne('SELECT id FROM ppb_users WHERE email = ?', [$email1]);
                    $existingUsername = $existing !== null
                        ? null
                        : $db->fetchOne('SELECT id FROM ppb_users WHERE username = ?', [$username]);

                    if ($existing !== null) {
                        $formError = $lang_emailalreadyexists ?? 'Email address already registered';
                    } elseif ($existingUsername !== null) {
                        $formError = $lang_usernametaken ?? 'This username is already taken';
                    } else {
                        $passwordHash = Security::hashPassword($password1);

                        $biography = strip_tags($biography);
                        $signature = strip_tags($signature, '<b><i><u><strong><em><br><a>');
                        $homepage = (string) Validator::normalizeHomepage($homepage);
                        $hideemail = ppb_hide_email($hideemail);

                        $now = time();

                        try {
                            $db->query(
                                "INSERT INTO ppb_users
                                 (username, email, password, homepage, icq, biography, signature, hideemail, status, registered)
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Normal user', ?)",
                                [$username, $email1, $passwordHash, $homepage, $icqNum, $biography, $signature, $hideemail, $now]
                            );

                            $subject = ($settings['boardtitle'] ?? 'PowerPHPBoard') . ' – ' . ($lang_registration ?? 'Registration');
                            $message = ppb_welcome_mail_text($settings, $username, $email1, false);
                            $registrationMailSent = Mailer::fromConfig($mail ?? [])->send(
                                $email1,
                                Mailer::senderAddress($settings, $mail ?? []),
                                $subject,
                                $message
                            );
                            if (!$registrationMailSent) {
                                ErrorHandler::logConfigurationError('Registrierungsmail an Benutzer „' . $username . '“ konnte nicht versendet werden (SMTP-Einstellungen prüfen).');
                            }

                            $registrationDone = true;
                        } catch (PDOException) {
                            $formError = $lang_errorwhilereg ?? 'An error occurred during registration';
                        }
                    }
                }
            }
        }
    }

    if ($registrationDone) {
        ?>
  <div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
      <section class="card shadow-sm border-success">
        <header class="card-header bg-success text-white">
          <h2 class="h6 mb-0">
            <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
            <?php echo Security::escape($lang_statusmessage ?? 'Status'); ?>
          </h2>
        </header>
        <div class="card-body">
          <p class="mb-3">
            <?php echo Security::escape($lang_registrationsuccessfull ?? 'Registration successful!'); ?>
          </p>
          <?php if (!$registrationMailSent): ?>
            <div class="alert alert-warning small" role="alert">
              <?php echo Security::escape($lang_confirmationmailfailed ?? 'The confirmation email could not be sent. You can still log in.'); ?>
            </div>
          <?php endif; ?>
          <a href="login.php?catid=<?php echo (int) $catid; ?>&boardid=<?php echo (int) $boardid; ?>"
             class="btn btn-primary">
            <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>
            <?php echo Security::escape($lang_login ?? 'Login'); ?>
          </a>
        </div>
      </section>
    </div>
  </div>
<?php
    } else {
        // Repopulate fields with submitted values when validation failed
        $oldUsername = ($_SERVER['REQUEST_METHOD'] === 'POST') ? Security::getString('username', 'POST') : '';
        $oldEmail1 = ($_SERVER['REQUEST_METHOD'] === 'POST') ? Security::getString('email1', 'POST') : '';
        $oldEmail2 = ($_SERVER['REQUEST_METHOD'] === 'POST') ? Security::getString('email2', 'POST') : '';
        $oldHomepage = ($_SERVER['REQUEST_METHOD'] === 'POST') ? Security::getString('homepage', 'POST') : '';
        $oldIcq = ($_SERVER['REQUEST_METHOD'] === 'POST') ? Security::getString('icq', 'POST') : '';
        $oldBio = ($_SERVER['REQUEST_METHOD'] === 'POST') ? Security::getString('biography', 'POST') : '';
        $oldSig = ($_SERVER['REQUEST_METHOD'] === 'POST') ? Security::getString('signature', 'POST') : '';
        // Neues Formular: Adresse verbergen ist vorausgewählt
        $oldHide = ppb_hide_email(($_SERVER['REQUEST_METHOD'] === 'POST') ? Security::getString('hideemail', 'POST') : '');
        ?>
  <div class="row justify-content-center">
    <div class="col-lg-9">

      <?php if ($formError !== ''): ?>
        <div class="alert alert-danger" role="alert">
          <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
          <?php echo Security::escape($formError); ?>
        </div>
      <?php endif; ?>

      <form action="register.php?acception=1" method="post" class="needs-validation" novalidate>
        <?php echo CSRF::getTokenField(); ?>
        <input type="hidden" name="acception" value="1">
        <input type="hidden" name="register" value="1">
        <input type="hidden" name="catid" value="<?php echo (int) $catid; ?>">
        <input type="hidden" name="boardid" value="<?php echo (int) $boardid; ?>">

        <section class="card shadow-sm mb-3">
          <header class="card-header bg-secondary-subtle">
            <h2 class="h6 mb-0">
              <i class="bi bi-asterisk" aria-hidden="true"></i>
              <?php echo Security::escape($lang_requiredinfo ?? 'Required Information'); ?>
            </h2>
          </header>
          <div class="card-body">
            <div class="mb-3">
              <label for="username" class="form-label fw-semibold">
                <?php echo Security::escape($lang_username ?? 'Username'); ?>
                <span class="text-danger" aria-hidden="true">*</span>
              </label>
              <input id="username" name="username" type="text" class="form-control"
                     maxlength="50" required minlength="2"
                     pattern="[A-Za-z0-9._\-]{2,50}"
                     value="<?php echo Security::escape($oldUsername); ?>"
                     aria-describedby="usernameHelp">
              <div id="usernameHelp" class="form-text">
                <?php echo Security::escape($lang_usernamehelp ?? '2 to 50 characters: letters (no umlauts), digits and . _ -'); ?>
              </div>
              <div class="invalid-feedback">
                <?php echo Security::escape($lang_usernameinvalid ?? 'Username must be 2-50 chars and only contain letters, digits, . _ -'); ?>
              </div>
            </div>

            <div class="row g-3">
              <div class="col-md-6">
                <label for="email1" class="form-label fw-semibold">
                  <?php echo Security::escape($lang_email ?? 'Email'); ?>
                  <span class="text-danger" aria-hidden="true">*</span>
                </label>
                <input id="email1" name="email1" type="email" class="form-control"
                       maxlength="100" required autocomplete="email"
                       value="<?php echo Security::escape($oldEmail1); ?>">
                <div class="invalid-feedback"><?php echo Security::escape($lang_insertvalidemail ?? 'Please enter a valid email address.'); ?></div>
              </div>
              <div class="col-md-6">
                <label for="email2" class="form-label fw-semibold">
                  <?php echo Security::escape($lang_email ?? 'Email'); ?>
                  <small class="text-body-secondary">(<?php echo Security::escape($lang_confirmation ?? 'Confirmation'); ?>)</small>
                </label>
                <input id="email2" name="email2" type="email" class="form-control"
                       maxlength="100" required autocomplete="email"
                       value="<?php echo Security::escape($oldEmail2); ?>"
                       aria-describedby="email2Help">
                <div id="email2Help" class="form-text"><?php echo Security::escape($lang_repeatemailhelp ?? 'Please enter the email address again to confirm it.'); ?></div>
                <div class="invalid-feedback"><?php echo Security::escape($lang_insertvalidemail ?? 'Please enter a valid email address.'); ?></div>
              </div>
            </div>

            <div class="row g-3 mt-1">
              <div class="col-md-6">
                <label for="password1" class="form-label fw-semibold">
                  <?php echo Security::escape($lang_password ?? 'Password'); ?>
                  <span class="text-danger" aria-hidden="true">*</span>
                </label>
                <input id="password1" name="password1" type="password" class="form-control"
                       minlength="8" maxlength="255" required autocomplete="new-password"
                       aria-describedby="password1Help">
                <div id="password1Help" class="form-text"><?php echo Security::escape($lang_pwdminlength ?? 'At least 8 characters.'); ?></div>
                <div class="invalid-feedback"><?php echo Security::escape($lang_pwdtooshort ?? 'The password must be at least 8 characters long.'); ?></div>
              </div>
              <div class="col-md-6">
                <label for="password2" class="form-label fw-semibold">
                  <?php echo Security::escape($lang_password ?? 'Password'); ?>
                  <small class="text-body-secondary">(<?php echo Security::escape($lang_confirmation ?? 'Confirmation'); ?>)</small>
                </label>
                <input id="password2" name="password2" type="password" class="form-control"
                       minlength="8" maxlength="255" required autocomplete="new-password">
                <div class="invalid-feedback"><?php echo Security::escape($lang_repeatpwd ?? 'Please enter the password again.'); ?></div>
              </div>
            </div>
          </div>
        </section>

        <section class="card shadow-sm mb-3">
          <header class="card-header bg-secondary-subtle">
            <h2 class="h6 mb-0">
              <i class="bi bi-person-plus" aria-hidden="true"></i>
              <?php echo Security::escape($lang_optionalinfo ?? 'Optional Information'); ?>
            </h2>
          </header>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-8">
                <label for="homepage" class="form-label">
                  <?php echo Security::escape($lang_homepage ?? 'Homepage'); ?>
                </label>
                <input id="homepage" name="homepage" type="text" inputmode="url" class="form-control"
                       maxlength="150" autocomplete="url" placeholder="https://example.org"
                       value="<?php echo Security::escape($oldHomepage); ?>"
                       aria-describedby="homepageHelp">
                <div id="homepageHelp" class="form-text">
                  <?php echo Security::escape($lang_homepagehelp ?? 'Optional. https:// is added automatically if missing.'); ?>
                </div>
              </div>
              <div class="col-md-4">
                <label for="icq" class="form-label">
                  <?php echo Security::escape($lang_icq ?? 'ICQ'); ?>
                </label>
                <input id="icq" name="icq" type="number" class="form-control"
                       maxlength="10" min="0"
                       value="<?php echo Security::escape($oldIcq); ?>">
              </div>
            </div>
            <div class="mt-3">
              <label for="biography" class="form-label">
                <?php echo Security::escape($lang_biography ?? 'Biography'); ?>
                <small class="text-body-secondary">(<?php echo Security::escape($lang_writesomethingaboutyou ?? 'Tell us about yourself'); ?>)</small>
              </label>
              <textarea id="biography" name="biography" class="form-control" rows="4"
                        maxlength="<?php echo Validator::BIOGRAPHY_MAX; ?>"><?php echo Security::escape($oldBio); ?></textarea>
            </div>
          </div>
        </section>

        <section class="card shadow-sm mb-3">
          <header class="card-header bg-secondary-subtle">
            <h2 class="h6 mb-0">
              <i class="bi bi-gear" aria-hidden="true"></i>
              <?php echo Security::escape($lang_othersettings ?? 'Other Settings'); ?>
            </h2>
          </header>
          <div class="card-body">
            <div class="mb-3">
              <label for="signature" class="form-label">
                <?php echo Security::escape($lang_signature ?? 'Signature'); ?>
              </label>
              <textarea id="signature" name="signature" class="form-control" rows="3"
                        maxlength="<?php echo Validator::SIGNATURE_MAX; ?>"><?php echo Security::escape($oldSig); ?></textarea>
              <div class="form-text">
                <?php echo Security::escape($lang_htmlcodeis ?? 'HTML ist'); ?>
                <strong><?php echo Security::escape(ppb_onoff_label('OFF')); ?></strong>,
                <a href="bbcode.php" target="_blank" rel="noopener">
                  <?php echo Security::escape($lang_bbcodeis ?? 'BBCode ist'); ?>
                  <strong><?php echo Security::escape(ppb_onoff_label($settings['bbcode'] ?? 'ON')); ?></strong></a>,
                <a href="smilies.php" target="_blank" rel="noopener">
                  <?php echo Security::escape($lang_smiliesare ?? 'Smilies sind'); ?>
                  <strong><?php echo Security::escape(ppb_onoff_label($settings['smilies'] ?? 'ON')); ?></strong></a>.
              </div>
            </div>

            <fieldset class="mb-1">
              <legend class="form-label fw-semibold mb-1 fs-6"><?php echo Security::escape($lang_hideemail ?? 'Hide Email'); ?></legend>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="hideemail" id="hideemailYes" value="YES" <?php echo $oldHide === 'YES' ? 'checked' : ''; ?>>
                <label class="form-check-label" for="hideemailYes"><?php echo Security::escape($lang_yes ?? 'ja'); ?></label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="hideemail" id="hideemailNo" value="NO" <?php echo $oldHide === 'NO' ? 'checked' : ''; ?>>
                <label class="form-check-label" for="hideemailNo"><?php echo Security::escape($lang_no ?? 'nein'); ?></label>
              </div>
              <div class="form-text"><?php echo Security::escape($lang_hideemailhelp ?? 'If enabled, other users cannot see your email address.'); ?></div>
            </fieldset>

          </div>
        </section>

        <div class="d-flex flex-wrap gap-2 mb-4">
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-person-plus" aria-hidden="true"></i>
            <?php echo Security::escape($lang_send ?? 'Submit'); ?>
          </button>
          <button type="reset" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
            <?php echo Security::escape($lang_reset ?? 'Reset'); ?>
          </button>
        </div>
      </form>

    </div>
  </div>
<?php
    }
}
?>

<?php include __DIR__ . '/footer.inc.php'; ?>
