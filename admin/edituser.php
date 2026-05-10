<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Edit User
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

use PowerPHPBoard\CSRF;
use PowerPHPBoard\Security;

include __DIR__ . '/header.inc.php';

$userid = Security::getInt('userid', 'GET', 0);
$edituser = Security::getInt('edituser', 'GET', 0);
$row = $db->fetchOne('SELECT * FROM ppb_users WHERE id = ?', [$userid]);

$saved = false;
$formError = '';

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

    $passwordWillChange = $password1 !== '' || $password2 !== '';

    if ($username === '' || $email1 === '' || $email2 === '') {
        $formError = 'Bitte fülle alle Pflichtfelder aus.';
    } elseif ($email1 !== $email2) {
        $formError = 'Die E-Mail-Adressen stimmen nicht überein.';
    } elseif (!Security::isValidEmail($email1)) {
        $formError = 'Bitte eine gültige E-Mail-Adresse angeben.';
    } elseif ($passwordWillChange && $password1 !== $password2) {
        $formError = 'Die Passwörter stimmen nicht überein.';
    } else {
        $existingUser = $db->fetchOne(
            'SELECT id FROM ppb_users WHERE email = ? AND id != ?',
            [$email1, $row['id']]
        );
        if ($existingUser !== null) {
            $formError = 'Diese E-Mail-Adresse ist bereits einem anderen Nutzer zugeordnet.';
        } else {
            $icqInt = (int) $icq;
            $username = strip_tags($username);
            $biography = strip_tags($biography);
            $finalPassword = $passwordWillChange
                ? Security::hashPassword($password1)
                : $row['password'];

            $allowedStatus = ['Deactivated', 'Normal user', 'Administrator'];
            if (!in_array($status, $allowedStatus, true)) {
                $status = 'Normal user';
            }

            try {
                $db->execute(
                    'UPDATE ppb_users SET username = ?, email = ?, password = ?, homepage = ?, icq = ?, biography = ?, signature = ?, hideemail = ?, logincookie = ?, status = ? WHERE id = ?',
                    [$username, $email1, $finalPassword, $homepage, $icqInt, $biography, $signature, $hideemail, $logincookie, $status, $row['id']]
                );
                CSRF::regenerate();
                $saved = true;
                $row = $db->fetchOne('SELECT * FROM ppb_users WHERE id = ?', [$userid]) ?? $row;
            } catch (Exception) {
                $formError = 'Fehler beim Aktualisieren.';
            }
        }
    }
}
?>

<header class="mb-3">
  <h1 class="h3 mb-0"><i class="bi bi-person-gear" aria-hidden="true"></i> Nutzer bearbeiten</h1>
</header>

<?php if ($row === null): ?>
  <div class="alert alert-warning" role="alert">
    Kein Nutzer mit dieser ID gefunden.
    <a class="alert-link" href="user.php">Zurück zur Suche</a>.
  </div>
<?php else: ?>

  <?php if ($saved): ?>
    <div class="alert alert-success" role="alert">
      <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
      Änderungen gespeichert.
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
        <h2 class="h6 mb-0"><i class="bi bi-asterisk" aria-hidden="true"></i> Grunddaten</h2>
      </header>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label for="username" class="form-label fw-semibold">Benutzername</label>
            <input id="username" name="username" type="text" class="form-control"
                   maxlength="50" required value="<?php echo Security::escape((string) $row['username']); ?>">
          </div>
          <div class="col-md-6">
            <label for="status" class="form-label fw-semibold">Status</label>
            <select id="status" name="status" class="form-select">
              <?php foreach (['Normal user', 'Administrator', 'Deactivated'] as $s): ?>
                <option value="<?php echo $s; ?>" <?php echo $row['status'] === $s ? 'selected' : ''; ?>>
                  <?php echo $s; ?>
                </option>
              <?php endforeach; ?>
            </select>
            <div class="form-text">Achtung: Status "Administrator" erlaubt vollen Zugriff auf den Adminbereich.</div>
          </div>
          <div class="col-md-6">
            <label for="email1" class="form-label fw-semibold">E-Mail</label>
            <input id="email1" name="email1" type="email" class="form-control"
                   maxlength="100" required value="<?php echo Security::escape((string) $row['email']); ?>">
          </div>
          <div class="col-md-6">
            <label for="email2" class="form-label fw-semibold">E-Mail (Bestätigung)</label>
            <input id="email2" name="email2" type="email" class="form-control"
                   maxlength="100" required value="<?php echo Security::escape((string) $row['email']); ?>">
          </div>
          <div class="col-md-6">
            <label for="password1" class="form-label">Neues Passwort</label>
            <input id="password1" name="password1" type="password" class="form-control"
                   minlength="8" maxlength="255" autocomplete="new-password">
            <div class="form-text">Leer lassen, wenn nicht geändert werden soll.</div>
          </div>
          <div class="col-md-6">
            <label for="password2" class="form-label">Neues Passwort (Bestätigung)</label>
            <input id="password2" name="password2" type="password" class="form-control"
                   minlength="8" maxlength="255" autocomplete="new-password">
          </div>
        </div>
      </div>
    </section>

    <section class="card shadow-sm mb-3">
      <header class="card-header bg-secondary-subtle">
        <h2 class="h6 mb-0"><i class="bi bi-person-plus" aria-hidden="true"></i> Profil</h2>
      </header>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-8">
            <label for="homepage" class="form-label">Homepage</label>
            <input id="homepage" name="homepage" type="url" class="form-control" maxlength="150"
                   value="<?php echo Security::escape((string) ($row['homepage'] ?? '')); ?>">
          </div>
          <div class="col-md-4">
            <label for="icq" class="form-label">ICQ</label>
            <input id="icq" name="icq" type="number" class="form-control" maxlength="10" min="0"
                   value="<?php echo Security::escape((string) ($row['icq'] ?? '')); ?>">
          </div>
          <div class="col-12">
            <label for="biography" class="form-label">Biografie</label>
            <textarea id="biography" name="biography" class="form-control" rows="3"><?php echo Security::escape((string) ($row['biography'] ?? '')); ?></textarea>
          </div>
          <div class="col-12">
            <label for="signature" class="form-label">Signatur</label>
            <textarea id="signature" name="signature" class="form-control" rows="3"><?php echo Security::escape((string) ($row['signature'] ?? '')); ?></textarea>
          </div>
          <div class="col-md-6">
            <fieldset>
              <legend class="form-label fw-semibold mb-1 fs-6">E-Mail verbergen</legend>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" id="hideY" name="hideemail" value="YES" <?php echo $row['hideemail'] === 'YES' ? 'checked' : ''; ?>>
                <label class="form-check-label" for="hideY">ja</label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" id="hideN" name="hideemail" value="NO" <?php echo $row['hideemail'] !== 'YES' ? 'checked' : ''; ?>>
                <label class="form-check-label" for="hideN">nein</label>
              </div>
            </fieldset>
          </div>
          <div class="col-md-6">
            <fieldset>
              <legend class="form-label fw-semibold mb-1 fs-6">Login-Cookie</legend>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" id="cookY" name="logincookie" value="YES" <?php echo $row['logincookie'] !== 'NO' ? 'checked' : ''; ?>>
                <label class="form-check-label" for="cookY">ja</label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" id="cookN" name="logincookie" value="NO" <?php echo $row['logincookie'] === 'NO' ? 'checked' : ''; ?>>
                <label class="form-check-label" for="cookN">nein</label>
              </div>
            </fieldset>
          </div>
        </div>
      </div>
    </section>

    <div class="d-flex flex-wrap gap-2 mb-4">
      <button type="submit" class="btn btn-primary">
        <i class="bi bi-save" aria-hidden="true"></i> Speichern
      </button>
      <a class="btn btn-link" href="user.php">Zurück zur Nutzerverwaltung</a>
    </div>
  </form>
<?php endif; ?>

<?php include __DIR__ . '/footer.inc.php'; ?>
