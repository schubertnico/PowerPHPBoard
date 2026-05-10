<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - User Profile
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

use PowerPHPBoard\CSRF;
use PowerPHPBoard\Database;
use PowerPHPBoard\Security;
use PowerPHPBoard\Session;
use PowerPHPBoard\Validator;

require_once __DIR__ . '/config.inc.php';

Session::start();

try {
    $db = Database::getInstance($mysql);
} catch (PDOException $e) {
    die('Database connection failed');
}

$settings = $db->fetchOne('SELECT * FROM ppb_config WHERE id = ?', [1]) ?? [];

$langFile = match ($settings['language'] ?? 'English') {
    'Deutsch-Sie' => 'deutsch-sie.inc.php',
    'Deutsch-Du' => 'deutsch-du.inc.php',
    default => 'english.inc.php',
};
require_once __DIR__ . '/' . $langFile;
require_once __DIR__ . '/functions.inc.php';

$ppbuser = [];
$loggedin = 'NO';
$userId = Session::getUserId();

if ($userId !== null) {
    $userRow = $db->fetchOne('SELECT * FROM ppb_users WHERE id = ?', [$userId]);
    if ($userRow !== null) {
        $loggedin = 'YES';
        $ppbuser = $userRow;
    }
}

$logout = Security::getInt('logout');
if ($logout === 1) {
    Session::destroy();
    header('Location: index.php');
    exit;
}

$catid = Security::getInt('catid');
$boardid = Security::getInt('boardid');
$editprofile = Security::getInt('editprofile');

$formError = '';
$updated = false;

if ($loggedin === 'YES' && $_SERVER['REQUEST_METHOD'] === 'POST' && $editprofile === 1) {
    $user = $ppbuser;
    if (!CSRF::validateFromPost()) {
        $formError = 'Security token invalid. Please try again.';
    } else {
        $username = Security::getString('username', 'POST');
        $email1 = Security::getString('email1', 'POST');
        $email2 = Security::getString('email2', 'POST');
        $password1 = Security::getString('password1', 'POST');
        $password2 = Security::getString('password2', 'POST');
        $currentPassword = Security::getString('current_password', 'POST');
        $homepage = Security::getString('homepage', 'POST');
        $icq = Security::getString('icq', 'POST');
        $biography = Security::getString('biography', 'POST');
        $signature = Security::getString('signature', 'POST');
        $hideemail = Security::getString('hideemail', 'POST');
        $logincookie = Security::getString('logincookie', 'POST');

        $passwordWillChange = $password1 !== '' || $password2 !== '';
        $emailWillChange = $email1 !== $user['email'];
        $sensitiveChange = $passwordWillChange || $emailWillChange;

        if ($username === '' || $email1 === '' || $email2 === '') {
            $formError = $lang_insertvaluesforall ?? 'Please fill in all required fields';
        } elseif ($sensitiveChange && ($currentPassword === '' || !Security::verifyPassword($currentPassword, $user['password']))) {
            $formError = $lang_currentpasswordwrong ?? 'Current password is not correct';
        } elseif ($email1 !== $email2) {
            $formError = $lang_emailsdifferent ?? 'Email addresses do not match';
        } elseif (!Security::isValidEmail($email1)) {
            $formError = $lang_emailnotcorrect ?? 'Invalid email address';
        } elseif ($passwordWillChange && $password1 !== $password2) {
            $formError = $lang_pwdsdifferent ?? 'Passwords do not match';
        } elseif ($passwordWillChange && !Validator::isStrongPassword($password1)) {
            $formError = $lang_pwdtooshort ?? 'Password must be at least 8 characters';
        } elseif (!Validator::isValidUsername($username)) {
            $formError = $lang_usernameinvalid ?? 'Username invalid';
        } elseif ($icq !== '' && !ctype_digit($icq)) {
            $formError = $lang_icqnotcorrect ?? 'ICQ number must be numeric';
        } elseif (!Validator::withinLength($biography, Validator::BIOGRAPHY_MAX)
            || !Validator::withinLength($signature, Validator::SIGNATURE_MAX)
            || !Validator::withinLength($homepage, Validator::HOMEPAGE_MAX)) {
            $formError = $lang_inputstoolong ?? 'One or more fields exceed the allowed length';
        } else {
            $existingByUsername = $db->fetchOne(
                'SELECT id FROM ppb_users WHERE username = ? AND id != ?',
                [$username, $user['id']]
            );
            if ($existingByUsername !== null) {
                $formError = $lang_usernametaken ?? 'This username is already taken';
            } else {
                $existingByEmail = $db->fetchOne(
                    'SELECT id FROM ppb_users WHERE email = ? AND id != ?',
                    [$email1, $user['id']]
                );
                if ($existingByEmail !== null) {
                    $formError = $lang_emailalreadyexists ?? 'Email already exists';
                }
            }
        }

        if ($formError === '') {
            $signature = strip_tags($signature, '<b><i><u><strong><em><br><a>');
            $finalPasswordHash = $passwordWillChange
                ? Security::hashPassword($password1)
                : $user['password'];

            try {
                $db->query(
                    'UPDATE ppb_users SET username = ?, email = ?, password = ?, homepage = ?, icq = ?, biography = ?, signature = ?, hideemail = ?, logincookie = ? WHERE id = ?',
                    [
                        $username,
                        $email1,
                        $finalPasswordHash,
                        $homepage,
                        $icq,
                        strip_tags($biography),
                        $signature,
                        $hideemail === 'YES' ? 'YES' : 'NO',
                        $logincookie === 'YES' ? 'YES' : 'NO',
                        $user['id'],
                    ]
                );
                CSRF::regenerate();
                $updated = true;
                // Refresh user row
                $ppbuser = $db->fetchOne('SELECT * FROM ppb_users WHERE id = ?', [$user['id']]) ?? $ppbuser;
            } catch (PDOException) {
                $formError = $lang_errorwhileupdprofile ?? 'Error updating profile';
            }
        }
    }
}

include __DIR__ . '/header.inc.php';
?>

<?php if ($loggedin !== 'YES'): ?>
  <div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
      <section class="card shadow-sm">
        <header class="card-header bg-secondary-subtle">
          <h1 class="h5 mb-0">
            <i class="bi bi-person-circle" aria-hidden="true"></i>
            <?php echo $lang_profile ?? 'Profile'; ?>
          </h1>
        </header>
        <div class="card-body text-center">
          <p class="mb-3"><?php echo $lang_loginfirst ?? 'Please log in first'; ?></p>
          <a href="login.php?catid=<?php echo (int) $catid; ?>&boardid=<?php echo (int) $boardid; ?>"
             class="btn btn-primary">
            <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>
            <?php echo $lang_login ?? 'Login'; ?>
          </a>
        </div>
      </section>
    </div>
  </div>
<?php else:
    $user = $ppbuser;
    $hideEmailValue = $user['hideemail'] === 'YES' ? 'YES' : 'NO';
    $cookieValue = $user['logincookie'] === 'NO' ? 'NO' : 'YES';
    ?>

  <?php if ($updated): ?>
    <div class="alert alert-success" role="alert">
      <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
      <?php echo $lang_changedprofilesuccessfull ?? 'Profile updated successfully'; ?>
    </div>
  <?php endif; ?>

  <?php if ($formError !== ''): ?>
    <div class="alert alert-danger" role="alert">
      <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
      <?php echo Security::escape($formError); ?>
    </div>
  <?php endif; ?>

  <div class="row justify-content-center">
    <div class="col-lg-9">
      <form action="profile.php?login=1&editprofile=1&catid=<?php echo (int) $catid; ?>&boardid=<?php echo (int) $boardid; ?>"
            method="post" class="needs-validation" novalidate>
        <?php echo CSRF::getTokenField(); ?>

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
              </label>
              <input id="username" name="username" type="text" class="form-control"
                     maxlength="50" required minlength="2" pattern="[A-Za-z0-9._\-]{2,50}"
                     value="<?php echo Security::escape((string) $user['username']); ?>">
              <div class="form-text">2-50 Zeichen, erlaubt sind Buchstaben, Ziffern sowie . _ -</div>
              <div class="invalid-feedback">Bitte einen gültigen Benutzernamen angeben.</div>
            </div>

            <div class="row g-3">
              <div class="col-md-6">
                <label for="email1" class="form-label fw-semibold">
                  <?php echo $lang_email ?? 'Email'; ?>
                </label>
                <input id="email1" name="email1" type="email" class="form-control"
                       maxlength="100" required autocomplete="email"
                       value="<?php echo Security::escape((string) $user['email']); ?>">
                <div class="invalid-feedback">Bitte eine gültige E-Mail-Adresse eingeben.</div>
              </div>
              <div class="col-md-6">
                <label for="email2" class="form-label fw-semibold">
                  <?php echo $lang_email ?? 'Email'; ?>
                  <small class="text-body-secondary">(<?php echo $lang_confirmation ?? 'Confirmation'; ?>)</small>
                </label>
                <input id="email2" name="email2" type="email" class="form-control"
                       maxlength="100" required autocomplete="email"
                       value="<?php echo Security::escape((string) $user['email']); ?>">
                <div class="invalid-feedback">Die E-Mail-Adressen muessen übereinstimmen.</div>
              </div>
            </div>

            <div class="mt-4 p-3 bg-body-tertiary rounded border">
              <div class="d-flex align-items-center gap-2 mb-2">
                <i class="bi bi-shield-lock" aria-hidden="true"></i>
                <strong>Sicherheit:</strong>
                <span class="text-body-secondary small">
                  Nur ausfüllen, wenn du E-Mail oder Passwort änderst.
                </span>
              </div>
              <div class="row g-3">
                <div class="col-md-12">
                  <label for="current_password" class="form-label">
                    <?php echo $lang_currentpassword ?? 'Current Password'; ?>
                  </label>
                  <input id="current_password" name="current_password" type="password"
                         class="form-control" maxlength="255" autocomplete="current-password"
                         aria-describedby="currentPwdHelp">
                  <div id="currentPwdHelp" class="form-text">
                    <?php echo $lang_currentpwdnote ?? 'Only required if you change email or password'; ?>
                  </div>
                </div>
                <div class="col-md-6">
                  <label for="password1" class="form-label">
                    <?php echo $lang_newpassword ?? 'New Password'; ?>
                  </label>
                  <input id="password1" name="password1" type="password"
                         class="form-control" minlength="8" maxlength="255" autocomplete="new-password">
                  <div class="form-text">
                    <?php echo $lang_leaveemptynochange ?? 'Leave empty to keep current password'; ?>
                  </div>
                </div>
                <div class="col-md-6">
                  <label for="password2" class="form-label">
                    <?php echo $lang_newpassword ?? 'New Password'; ?>
                    <small class="text-body-secondary">(<?php echo $lang_confirmation ?? 'Confirmation'; ?>)</small>
                  </label>
                  <input id="password2" name="password2" type="password"
                         class="form-control" minlength="8" maxlength="255" autocomplete="new-password">
                </div>
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
                       value="<?php echo Security::escape((string) ($user['homepage'] ?? '')); ?>">
              </div>
              <div class="col-md-4">
                <label for="icq" class="form-label">
                  <?php echo $lang_icq ?? 'ICQ'; ?>
                </label>
                <input id="icq" name="icq" type="number" class="form-control" maxlength="10" min="0"
                       value="<?php echo Security::escape((string) ($user['icq'] ?? '') === '0' ? '' : (string) ($user['icq'] ?? '')); ?>">
              </div>
            </div>
            <div class="mt-3">
              <label for="biography" class="form-label">
                <?php echo $lang_biography ?? 'Biography'; ?>
              </label>
              <textarea id="biography" name="biography" class="form-control" rows="4"
                        maxlength="<?php echo Validator::BIOGRAPHY_MAX; ?>"><?php echo Security::escape((string) ($user['biography'] ?? '')); ?></textarea>
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
                        maxlength="<?php echo Validator::SIGNATURE_MAX; ?>"><?php echo Security::escape((string) ($user['signature'] ?? '')); ?></textarea>
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
              <legend class="form-label fw-semibold mb-1 fs-6"><?php echo $lang_hideemail ?? 'Hide email'; ?></legend>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="hideemail" id="hideemailYes" value="YES"
                       <?php echo $hideEmailValue === 'YES' ? 'checked' : ''; ?>>
                <label class="form-check-label" for="hideemailYes"><?php echo $lang_yes ?? 'ja'; ?></label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="hideemail" id="hideemailNo" value="NO"
                       <?php echo $hideEmailValue !== 'YES' ? 'checked' : ''; ?>>
                <label class="form-check-label" for="hideemailNo"><?php echo $lang_no ?? 'nein'; ?></label>
              </div>
            </fieldset>
            <fieldset>
              <legend class="form-label fw-semibold mb-1 fs-6"><?php echo $lang_saveloginincookie ?? 'Remember login'; ?></legend>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="logincookie" id="cookieYes" value="YES"
                       <?php echo $cookieValue !== 'NO' ? 'checked' : ''; ?>>
                <label class="form-check-label" for="cookieYes"><?php echo $lang_yes ?? 'ja'; ?></label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="logincookie" id="cookieNo" value="NO"
                       <?php echo $cookieValue === 'NO' ? 'checked' : ''; ?>>
                <label class="form-check-label" for="cookieNo"><?php echo $lang_no ?? 'nein'; ?></label>
              </div>
            </fieldset>
          </div>
        </section>

        <div class="d-flex flex-wrap gap-2 mb-4">
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-save" aria-hidden="true"></i>
            <?php echo $lang_send ?? 'Send'; ?>
          </button>
          <button type="reset" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
            <?php echo $lang_reset ?? 'Reset'; ?>
          </button>
          <a class="btn btn-link" href="logout.php">
            <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
            <?php echo $lang_logout ?? 'Logout'; ?>
          </a>
        </div>
      </form>
    </div>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/footer.inc.php'; ?>
