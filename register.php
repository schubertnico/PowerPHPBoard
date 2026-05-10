<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - User Registration
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

use PowerPHPBoard\CSRF;
use PowerPHPBoard\Mailer;
use PowerPHPBoard\Security;
use PowerPHPBoard\Validator;

include __DIR__ . '/header.inc.php';

$acception = Security::getInt('acception', 'REQUEST');
$register = Security::getInt('register', 'POST');
$registrationDone = false;
$registrationLogin = false;
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
            <?php echo $lang_boardrules ?? 'Board Rules'; ?>
          </h1>
        </header>
        <div class="card-body">
          <p class="mb-4">
            <?php echo $lang_boardrulescontent ?? 'Please read and accept the board rules.'; ?>
          </p>
          <div class="d-flex flex-wrap gap-2">
            <form action="register.php" method="get" class="d-inline">
              <input type="hidden" name="acception" value="1">
              <input type="hidden" name="catid" value="<?php echo (int) $catid; ?>">
              <input type="hidden" name="boardid" value="<?php echo (int) $boardid; ?>">
              <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-circle" aria-hidden="true"></i>
                <?php echo $lang_agree ?? 'I Agree'; ?>
              </button>
            </form>
            <form action="index.php" method="get" class="d-inline">
              <button type="submit" class="btn btn-outline-secondary">
                <i class="bi bi-x-circle" aria-hidden="true"></i>
                <?php echo $lang_disagree ?? 'I Disagree'; ?>
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
            $formError = 'Security token invalid. Please try again.';
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
            $logincookie = Security::getString('logincookie', 'POST');

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
                        $homepage = filter_var($homepage, FILTER_VALIDATE_URL) ? $homepage : '';
                        $hideemail = in_array($hideemail, ['YES', 'NO'], true) ? $hideemail : 'NO';
                        $logincookie = in_array($logincookie, ['YES', 'NO'], true) ? $logincookie : 'YES';

                        $now = time();

                        try {
                            $db->query(
                                "INSERT INTO ppb_users
                                 (username, email, password, homepage, icq, biography, signature, hideemail, logincookie, status, registered)
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Normal user', ?)",
                                [$username, $email1, $passwordHash, $homepage, $icqNum, $biography, $signature, $hideemail, $logincookie, $now]
                            );

                            $subject = ($settings['boardtitle'] ?? 'PowerPHPBoard') . ' ' . ($lang_registration ?? 'Registration');
                            $message = ($lang_hello ?? 'Hello') . " $username,\n\n" .
                                ($lang_youregisteredsuccessfull ?? 'You have registered successfully at') . ' ' . ($settings['boardurl'] ?? '') . "\n\n" .
                                ($lang_hereisyourlogininformation ?? 'Your login information:') . "\n\n" .
                                '     ' . ($lang_username ?? 'Username') . ":  $username\n" .
                                '     ' . ($lang_email ?? 'Email') . ":  $email1\n\n" .
                                ($lang_youcanloginhere ?? 'Login here') . ': ' . ($settings['boardurl'] ?? '') . "/login.php\n\n" .
                                ($lang_donotanswertoautomail ?? 'This is an automated message, please do not reply.');

                            $mailer = new Mailer(
                                (string) ($mail['host'] ?? 'mailpit'),
                                (int) ($mail['port'] ?? 1025)
                            );
                            $fromAddress = (string) ($settings['adminemail'] ?? '');
                            if ($fromAddress === '' || !Security::isValidEmail($fromAddress)) {
                                $fromAddress = (string) ($mail['from'] ?? 'noreply@powerphpboard.local');
                            }
                            $mailer->send($email1, $fromAddress, $subject, $message);

                            $registrationDone = true;
                            $registrationLogin = ($logincookie === 'YES');
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
            <?php echo $lang_statusmessage ?? 'Status'; ?>
          </h2>
        </header>
        <div class="card-body">
          <p class="mb-3">
            <?php echo $lang_registrationsuccessfull ?? 'Registration successful!'; ?>
          </p>
          <?php if ($registrationLogin): ?>
            <a href="login.php?catid=<?php echo (int) $catid; ?>&boardid=<?php echo (int) $boardid; ?>"
               class="btn btn-primary">
              <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>
              <?php echo $lang_login ?? 'Login'; ?>
            </a>
          <?php else: ?>
            <a href="index.php" class="btn btn-primary">
              <i class="bi bi-house-door" aria-hidden="true"></i> Home
            </a>
          <?php endif; ?>
        </div>
      </section>
    </div>
  </div>
<?php
    } else {
        // Repopulate fields with submitted values when validation failed
        $oldUsername = ($_SERVER['REQUEST_METHOD'] === 'POST') ? Security::getString('username', 'POST') : '';
        $oldEmail1 = ($_SERVER['REQUEST_METHOD'] === 'POST') ? Security::getString('email1', 'POST') : '';
        $oldHomepage = ($_SERVER['REQUEST_METHOD'] === 'POST') ? Security::getString('homepage', 'POST') : 'https://';
        $oldIcq = ($_SERVER['REQUEST_METHOD'] === 'POST') ? Security::getString('icq', 'POST') : '';
        $oldBio = ($_SERVER['REQUEST_METHOD'] === 'POST') ? Security::getString('biography', 'POST') : '';
        $oldSig = ($_SERVER['REQUEST_METHOD'] === 'POST') ? Security::getString('signature', 'POST') : '';
        $oldHide = ($_SERVER['REQUEST_METHOD'] === 'POST') ? Security::getString('hideemail', 'POST') : 'NO';
        $oldCookie = ($_SERVER['REQUEST_METHOD'] === 'POST') ? Security::getString('logincookie', 'POST') : 'YES';
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
              <?php echo $lang_requiredinfo ?? 'Required Information'; ?>
            </h2>
          </header>
          <div class="card-body">
            <div class="mb-3">
              <label for="username" class="form-label fw-semibold">
                <?php echo $lang_username ?? 'Username'; ?>
                <span class="text-danger" aria-hidden="true">*</span>
              </label>
              <input id="username" name="username" type="text" class="form-control"
                     maxlength="50" required minlength="2"
                     pattern="[A-Za-z0-9._\-]{2,50}"
                     value="<?php echo Security::escape($oldUsername); ?>"
                     aria-describedby="usernameHelp">
              <div id="usernameHelp" class="form-text">
                2-50 Zeichen, erlaubt sind Buchstaben, Ziffern sowie . _ -
              </div>
              <div class="invalid-feedback">
                <?php echo $lang_usernameinvalid ?? 'Username must be 2-50 chars and only contain letters, digits, . _ -'; ?>
              </div>
            </div>

            <div class="row g-3">
              <div class="col-md-6">
                <label for="email1" class="form-label fw-semibold">
                  <?php echo $lang_email ?? 'Email'; ?>
                  <span class="text-danger" aria-hidden="true">*</span>
                </label>
                <input id="email1" name="email1" type="email" class="form-control"
                       maxlength="100" required autocomplete="email"
                       value="<?php echo Security::escape($oldEmail1); ?>">
                <div class="invalid-feedback">Bitte eine gültige E-Mail-Adresse eingeben.</div>
              </div>
              <div class="col-md-6">
                <label for="email2" class="form-label fw-semibold">
                  <?php echo $lang_email ?? 'Email'; ?>
                  <small class="text-body-secondary">(<?php echo $lang_confirmation ?? 'Confirmation'; ?>)</small>
                </label>
                <input id="email2" name="email2" type="email" class="form-control"
                       maxlength="100" required autocomplete="email"
                       aria-describedby="email2Help">
                <div id="email2Help" class="form-text">Bitte zur Bestätigung wiederholen.</div>
                <div class="invalid-feedback">Die E-Mail-Adressen muessen übereinstimmen.</div>
              </div>
            </div>

            <div class="row g-3 mt-1">
              <div class="col-md-6">
                <label for="password1" class="form-label fw-semibold">
                  <?php echo $lang_password ?? 'Password'; ?>
                  <span class="text-danger" aria-hidden="true">*</span>
                </label>
                <input id="password1" name="password1" type="password" class="form-control"
                       minlength="8" maxlength="255" required autocomplete="new-password"
                       aria-describedby="password1Help">
                <div id="password1Help" class="form-text">Mindestens 8 Zeichen.</div>
                <div class="invalid-feedback">Mindestens 8 Zeichen erforderlich.</div>
              </div>
              <div class="col-md-6">
                <label for="password2" class="form-label fw-semibold">
                  <?php echo $lang_password ?? 'Password'; ?>
                  <small class="text-body-secondary">(<?php echo $lang_confirmation ?? 'Confirmation'; ?>)</small>
                </label>
                <input id="password2" name="password2" type="password" class="form-control"
                       minlength="8" maxlength="255" required autocomplete="new-password">
                <div class="invalid-feedback">Die Passwörter müssen übereinstimmen.</div>
              </div>
            </div>
          </div>
        </section>

        <section class="card shadow-sm mb-3">
          <header class="card-header bg-secondary-subtle">
            <h2 class="h6 mb-0">
              <i class="bi bi-person-plus" aria-hidden="true"></i>
              <?php echo $lang_optionalinfo ?? 'Optional Information'; ?>
            </h2>
          </header>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-8">
                <label for="homepage" class="form-label">
                  <?php echo $lang_homepage ?? 'Homepage'; ?>
                </label>
                <input id="homepage" name="homepage" type="url" class="form-control"
                       maxlength="150"
                       value="<?php echo Security::escape($oldHomepage !== '' ? $oldHomepage : 'https://'); ?>">
                <div class="form-text">Optional, beginnt mit https:// oder http://.</div>
              </div>
              <div class="col-md-4">
                <label for="icq" class="form-label">
                  <?php echo $lang_icq ?? 'ICQ'; ?>
                </label>
                <input id="icq" name="icq" type="number" class="form-control"
                       maxlength="10" min="0"
                       value="<?php echo Security::escape($oldIcq); ?>">
              </div>
            </div>
            <div class="mt-3">
              <label for="biography" class="form-label">
                <?php echo $lang_biography ?? 'Biography'; ?>
                <small class="text-body-secondary">(<?php echo $lang_writesomethingaboutyou ?? 'Tell us about yourself'; ?>)</small>
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
              <?php echo $lang_othersettings ?? 'Other Settings'; ?>
            </h2>
          </header>
          <div class="card-body">
            <div class="mb-3">
              <label for="signature" class="form-label">
                <?php echo $lang_signature ?? 'Signature'; ?>
              </label>
              <textarea id="signature" name="signature" class="form-control" rows="3"
                        maxlength="<?php echo Validator::SIGNATURE_MAX; ?>"><?php echo Security::escape($oldSig); ?></textarea>
              <div class="form-text">
                <?php echo $lang_htmlcodeis ?? 'HTML ist'; ?>
                <strong><?php echo ppb_onoff_label($settings['htmlcode'] ?? 'OFF'); ?></strong>,
                <a href="bbcode.php" target="_blank" rel="noopener">
                  <?php echo $lang_bbcodeis ?? 'BBCode ist'; ?>
                  <strong><?php echo ppb_onoff_label($settings['bbcode'] ?? 'ON'); ?></strong>
                </a>,
                <a href="smilies.php" target="_blank" rel="noopener">
                  <?php echo $lang_smiliesare ?? 'Smilies sind'; ?>
                  <strong><?php echo ppb_onoff_label($settings['smilies'] ?? 'ON'); ?></strong>
                </a>.
              </div>
            </div>

            <fieldset class="mb-3">
              <legend class="form-label fw-semibold mb-1 fs-6"><?php echo $lang_hideemail ?? 'Hide Email'; ?></legend>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="hideemail" id="hideemailYes" value="YES" <?php echo $oldHide === 'YES' ? 'checked' : ''; ?>>
                <label class="form-check-label" for="hideemailYes"><?php echo $lang_yes ?? 'ja'; ?></label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="hideemail" id="hideemailNo" value="NO" <?php echo $oldHide !== 'YES' ? 'checked' : ''; ?>>
                <label class="form-check-label" for="hideemailNo"><?php echo $lang_no ?? 'nein'; ?></label>
              </div>
              <div class="form-text">Wenn aktiviert, ist deine E-Mail-Adresse für andere Nutzer nicht sichtbar.</div>
            </fieldset>

            <fieldset class="mb-1">
              <legend class="form-label fw-semibold mb-1 fs-6"><?php echo $lang_saveloginincookie ?? 'Remember Login'; ?></legend>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="logincookie" id="cookieYes" value="YES" <?php echo $oldCookie !== 'NO' ? 'checked' : ''; ?>>
                <label class="form-check-label" for="cookieYes"><?php echo $lang_yes ?? 'ja'; ?></label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="logincookie" id="cookieNo" value="NO" <?php echo $oldCookie === 'NO' ? 'checked' : ''; ?>>
                <label class="form-check-label" for="cookieNo"><?php echo $lang_no ?? 'nein'; ?></label>
              </div>
            </fieldset>
          </div>
        </section>

        <div class="d-flex flex-wrap gap-2 mb-4">
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-person-plus" aria-hidden="true"></i>
            <?php echo $lang_send ?? 'Submit'; ?>
          </button>
          <button type="reset" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
            <?php echo $lang_reset ?? 'Reset'; ?>
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
