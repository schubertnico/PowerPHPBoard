<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Edit User
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

use PowerPHPBoard\Auth;
use PowerPHPBoard\CSRF;
use PowerPHPBoard\Security;
use PowerPHPBoard\Validator;

include __DIR__ . '/header.inc.php';

$userid = Security::getInt('userid', 'GET', 0);
$edituser = Security::getInt('edituser', 'GET', 0);
$row = $db->fetchOne('SELECT * FROM ppb_users WHERE id = ?', [$userid]);

$saved = false;
$formError = '';

// Sichtbare Bezeichnungen der Status (die Werte in der Datenbank bleiben englisch)
$statusLabels = [
    Auth::STATUS_NORMAL => $lang_adm_status_normal ?? 'Normal user',
    Auth::STATUS_ADMIN => $lang_administrator ?? 'Administrator',
    Auth::STATUS_DEACTIVATED => $lang_deactivated ?? 'Deactivated',
];

if ($row !== null && $edituser === 1 && $_SERVER['REQUEST_METHOD'] === 'POST') {
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
    $status = Security::getString('status', 'POST', 'Normal user');
    if (!array_key_exists($status, $statusLabels)) {
        $status = Auth::STATUS_NORMAL;
    }
    $hideemail = $hideemail === 'YES' ? 'YES' : 'NO';
    $logincookie = $logincookie === 'NO' ? 'NO' : 'YES';

    $passwordWillChange = $password1 !== '' || $password2 !== '';
    $adminCount = (int) ($db->fetchOne("SELECT COUNT(*) c FROM ppb_users WHERE status = 'Administrator'")['c'] ?? 0);
    $statusError = Auth::statusChangeError($ppbuser, $row, $status, $adminCount);

    if ($username === '' || $email1 === '' || $email2 === '') {
        $formError = $lang_adm_fillrequired ?? 'Please fill in all required fields.';
    } elseif ($statusError === 'self') {
        $formError = $lang_adm_ownstatus ?? 'You cannot change your own status, otherwise the administration would no longer be accessible. Only another administrator can do this.';
    } elseif ($statusError === 'lastadmin') {
        $formError = $lang_adm_lastadmin ?? 'The last administrator can neither be downgraded nor deactivated.';
    } elseif (!Validator::isValidUsername($username)) {
        $formError = $lang_usernameinvalid ?? 'The username must be 2 to 50 characters long and may only contain letters, digits and . _ -';
    } elseif ($email1 !== $email2) {
        $formError = $lang_adm_emailsdifferent ?? 'The email addresses do not match.';
    } elseif (!Security::isValidEmail($email1)) {
        $formError = $lang_insertvalidemail ?? 'Please enter a valid email address.';
    } elseif ($passwordWillChange && $password1 !== $password2) {
        $formError = $lang_adm_pwdsdifferent ?? 'The passwords do not match.';
    } elseif ($passwordWillChange && !Validator::isStrongPassword($password1)) {
        $formError = $lang_pwdtooshort ?? 'The password must be at least 8 characters long.';
    } elseif (Validator::normalizeHomepage($homepage) === null) {
        $formError = $lang_homepageinvalid ?? 'Please enter a valid homepage address starting with http:// or https://.';
    } elseif ($db->fetchOne('SELECT id FROM ppb_users WHERE username = ? AND id != ?', [$username, $row['id']]) !== null) {
        $formError = $lang_usernametaken ?? 'This username is already taken.';
    } else {
        $homepage = (string) Validator::normalizeHomepage($homepage);
        $existingUser = $db->fetchOne(
            'SELECT id FROM ppb_users WHERE email = ? AND id != ?',
            [$email1, $row['id']]
        );
        if ($existingUser !== null) {
            $formError = $lang_adm_emailtaken ?? 'This email address already belongs to another user.';
        } else {
            $icqInt = (int) $icq;
            $biography = strip_tags($biography);
            $finalPassword = $passwordWillChange
                ? Security::hashPassword($password1)
                : $row['password'];

            try {
                $db->execute(
                    'UPDATE ppb_users SET username = ?, email = ?, password = ?, homepage = ?, icq = ?, biography = ?, signature = ?, hideemail = ?, logincookie = ?, status = ? WHERE id = ?',
                    [$username, $email1, $finalPassword, $homepage, $icqInt, $biography, $signature, $hideemail, $logincookie, $status, $row['id']]
                );
                CSRF::regenerate();
                $saved = true;
                $row = $db->fetchOne('SELECT * FROM ppb_users WHERE id = ?', [$userid]) ?? $row;
            } catch (Exception) {
                $formError = $lang_adm_userupdatefailed ?? 'The changes could not be saved.';
            }
        }
    }

    // Nach einem Fehler die Eingaben (außer Passwörtern) zeigen; ein
    // abgelehnter Status wird nicht übernommen
    if ($formError !== '') {
        if ($statusError !== null) {
            $status = (string) $row['status'];
        }
        $row = array_merge($row, compact('username', 'homepage', 'icq', 'biography', 'signature', 'hideemail', 'logincookie', 'status'));
        $row['email'] = $email1;
    }
}
$email2Value = $row !== null ? (string) ($formError !== '' ? Security::getString('email2', 'POST') : $row['email']) : '';
?>

<header class="mb-3">
  <h1 class="h3 mb-0"><i class="bi bi-person-gear" aria-hidden="true"></i> <?php echo Security::escape($lang_adm_edituser ?? 'Edit user'); ?></h1>
</header>

<?php if ($row === null): ?>
  <div class="alert alert-warning" role="alert">
    <?php echo Security::escape($lang_adm_usernotfound ?? 'There is no user with this ID.'); ?>
    <a class="alert-link" href="user.php"><?php echo Security::escape($lang_adm_backtousermanagement ?? 'Back to user management'); ?></a>
  </div>
<?php else: ?>

  <?php if ($saved): ?>
    <div class="alert alert-success" role="alert">
      <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
      <?php echo Security::escape($lang_adm_changessaved ?? 'The changes have been saved.'); ?>
    </div>
  <?php endif; ?>
  <?php if ($formError !== ''): ?>
    <div class="alert alert-danger" role="alert"><?php echo Security::escape($formError); ?></div>
  <?php endif; ?>

  <form action="edituser.php?edituser=1&userid=<?php echo (int) $row['id']; ?>"
        method="post" class="needs-validation" novalidate>
    <?php echo CSRF::getTokenField(); ?>

    <section class="card shadow-sm mb-3">
      <header class="card-header bg-secondary-subtle">
        <h2 class="h6 mb-0"><i class="bi bi-asterisk" aria-hidden="true"></i> <?php echo Security::escape($lang_adm_basicdata ?? 'Basic data'); ?></h2>
      </header>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label for="username" class="form-label fw-semibold"><?php echo Security::escape($lang_username ?? 'Username'); ?></label>
            <input id="username" name="username" type="text" class="form-control"
                   maxlength="50" required value="<?php echo Security::escape((string) $row['username']); ?>">
            <div class="invalid-feedback"><?php echo Security::escape($lang_adm_insertusername ?? 'Please enter a username.'); ?></div>
          </div>
          <div class="col-md-6">
            <label for="status" class="form-label fw-semibold"><?php echo Security::escape($lang_status ?? 'Status'); ?></label>
            <select id="status" name="status" class="form-select" aria-describedby="statusHelp">
              <?php foreach ($statusLabels as $value => $label): ?>
                <option value="<?php echo Security::escape($value); ?>" <?php echo $row['status'] === $value ? 'selected' : ''; ?>>
                  <?php echo Security::escape($label); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <div id="statusHelp" class="form-text"><?php echo Security::escape($lang_adm_statusadminwarning ?? 'Note: the status "Administrator" grants full access to the administration.'); ?></div>
          </div>
          <div class="col-md-6">
            <label for="email1" class="form-label fw-semibold"><?php echo Security::escape($lang_email ?? 'Email'); ?></label>
            <input id="email1" name="email1" type="email" class="form-control"
                   maxlength="100" required value="<?php echo Security::escape((string) $row['email']); ?>">
            <div class="invalid-feedback"><?php echo Security::escape($lang_insertvalidemail ?? 'Please enter a valid email address.'); ?></div>
          </div>
          <div class="col-md-6">
            <label for="email2" class="form-label fw-semibold"><?php echo Security::escape($lang_email ?? 'Email'); ?> <small class="text-body-secondary">(<?php echo Security::escape($lang_confirmation ?? 'Confirmation'); ?>)</small></label>
            <input id="email2" name="email2" type="email" class="form-control"
                   maxlength="100" required value="<?php echo Security::escape($email2Value); ?>">
            <div class="invalid-feedback"><?php echo Security::escape($lang_insertvalidemail ?? 'Please enter a valid email address.'); ?></div>
          </div>
          <div class="col-md-6">
            <label for="password1" class="form-label"><?php echo Security::escape($lang_newpassword ?? 'New password'); ?></label>
            <input id="password1" name="password1" type="password" class="form-control"
                   minlength="8" maxlength="255" autocomplete="new-password" aria-describedby="password1Help">
            <div id="password1Help" class="form-text"><?php echo Security::escape($lang_leaveemptynochange ?? 'Leave empty to keep the current password.'); ?></div>
            <div class="invalid-feedback"><?php echo Security::escape($lang_pwdtooshort ?? 'The password must be at least 8 characters long.'); ?></div>
          </div>
          <div class="col-md-6">
            <label for="password2" class="form-label"><?php echo Security::escape($lang_newpassword ?? 'New password'); ?> <small class="text-body-secondary">(<?php echo Security::escape($lang_confirmation ?? 'Confirmation'); ?>)</small></label>
            <input id="password2" name="password2" type="password" class="form-control"
                   minlength="8" maxlength="255" autocomplete="new-password">
            <div class="invalid-feedback"><?php echo Security::escape($lang_pwdtooshort ?? 'The password must be at least 8 characters long.'); ?></div>
          </div>
        </div>
      </div>
    </section>

    <section class="card shadow-sm mb-3">
      <header class="card-header bg-secondary-subtle">
        <h2 class="h6 mb-0"><i class="bi bi-person-plus" aria-hidden="true"></i> <?php echo Security::escape($lang_profile ?? 'Profile'); ?></h2>
      </header>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-8">
            <label for="homepage" class="form-label"><?php echo Security::escape($lang_homepage ?? 'Homepage'); ?></label>
            <input id="homepage" name="homepage" type="text" inputmode="url" class="form-control" maxlength="150"
                   placeholder="https://example.org"
                   value="<?php echo Security::escape((string) ($row['homepage'] ?? '')); ?>" aria-describedby="homepageHelp">
            <div id="homepageHelp" class="form-text"><?php echo Security::escape($lang_homepagehelp ?? 'Optional. https:// is added automatically if missing.'); ?></div>
          </div>
          <div class="col-md-4">
            <label for="icq" class="form-label"><?php echo Security::escape($lang_icq ?? 'ICQ number'); ?></label>
            <input id="icq" name="icq" type="number" class="form-control" maxlength="10" min="0"
                   value="<?php echo Security::escape((string) ($row['icq'] ?? '') === '0' ? '' : (string) ($row['icq'] ?? '')); ?>">
          </div>
          <div class="col-12">
            <label for="biography" class="form-label"><?php echo Security::escape($lang_biography ?? 'Biography'); ?></label>
            <textarea id="biography" name="biography" class="form-control" rows="3"><?php echo Security::escape((string) ($row['biography'] ?? '')); ?></textarea>
          </div>
          <div class="col-12">
            <label for="signature" class="form-label"><?php echo Security::escape($lang_signature ?? 'Signature'); ?></label>
            <textarea id="signature" name="signature" class="form-control" rows="3"><?php echo Security::escape((string) ($row['signature'] ?? '')); ?></textarea>
          </div>
          <div class="col-md-6">
            <fieldset>
              <legend class="form-label fw-semibold mb-1 fs-6"><?php echo Security::escape($lang_hideemail ?? 'Hide email address?'); ?></legend>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" id="hideY" name="hideemail" value="YES" <?php echo $row['hideemail'] === 'YES' ? 'checked' : ''; ?>>
                <label class="form-check-label" for="hideY"><?php echo Security::escape($lang_yes ?? 'yes'); ?></label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" id="hideN" name="hideemail" value="NO" <?php echo $row['hideemail'] !== 'YES' ? 'checked' : ''; ?>>
                <label class="form-check-label" for="hideN"><?php echo Security::escape($lang_no ?? 'no'); ?></label>
              </div>
            </fieldset>
          </div>
          <div class="col-md-6">
            <fieldset>
              <legend class="form-label fw-semibold mb-1 fs-6"><?php echo Security::escape($lang_saveloginincookie ?? 'Remember login?'); ?></legend>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" id="cookY" name="logincookie" value="YES" <?php echo $row['logincookie'] !== 'NO' ? 'checked' : ''; ?>>
                <label class="form-check-label" for="cookY"><?php echo Security::escape($lang_yes ?? 'yes'); ?></label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" id="cookN" name="logincookie" value="NO" <?php echo $row['logincookie'] === 'NO' ? 'checked' : ''; ?>>
                <label class="form-check-label" for="cookN"><?php echo Security::escape($lang_no ?? 'no'); ?></label>
              </div>
            </fieldset>
          </div>
        </div>
      </div>
    </section>

    <div class="d-flex flex-wrap gap-2 mb-4">
      <button type="submit" class="btn btn-primary">
        <i class="bi bi-save" aria-hidden="true"></i> <?php echo Security::escape($lang_adm_save ?? 'Save'); ?>
      </button>
      <a class="btn btn-link" href="user.php"><?php echo Security::escape($lang_adm_backtousermanagement ?? 'Back to user management'); ?></a>
    </div>
  </form>
<?php endif; ?>

<?php include __DIR__ . '/footer.inc.php'; ?>
