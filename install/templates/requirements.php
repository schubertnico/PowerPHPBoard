<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Web-Installer: Schritt 1 (Systemprüfung)
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

use PowerPHPBoard\CSRF;
use PowerPHPBoard\Installer\Html;
use PowerPHPBoard\Security;

/**
 * @param list<array{id: string, label: string, ok: bool, required: bool, detail: string}> $checks
 */
return static function (array $checks, bool $allOk, string $message): void {
    ?>
<section class="card shadow-sm mb-4">
  <header class="card-header bg-secondary-subtle">
    <h2 class="h5 mb-0"><i class="bi bi-clipboard-check" aria-hidden="true"></i> Voraussetzungen des Servers</h2>
  </header>
  <div class="card-body">
    <p>
      Willkommen! Dieser Assistent richtet PowerPHPBoard in fünf Schritten ein.
      Zuerst prüft er, ob Ihr Server alle Voraussetzungen erfüllt.
    </p>

    <?php echo Html::alert($message); ?>

    <ul class="list-group mb-3" id="requirements-list">
      <?php foreach ($checks as $check): ?>
        <?php
            if ($check['ok']) {
                $icon = 'bi-check-circle-fill text-success';
                $state = 'erfüllt';
            } elseif ($check['required']) {
                $icon = 'bi-x-circle-fill text-danger';
                $state = 'nicht erfüllt';
            } else {
                $icon = 'bi-exclamation-triangle-fill text-warning';
                $state = 'Hinweis';
            }
          ?>
        <li class="list-group-item d-flex gap-3 align-items-start"
            id="check-<?php echo Security::escapeAttr($check['id']); ?>"
            data-ok="<?php echo $check['ok'] ? '1' : '0'; ?>">
          <i class="bi <?php echo $icon; ?> fs-5" aria-hidden="true"></i>
          <div>
            <strong><?php echo Security::escape($check['label']); ?></strong>
            <span class="visually-hidden">(<?php echo $state; ?>)</span>
            <span class="badge <?php echo $check['required'] ? 'text-bg-secondary' : 'text-bg-light border'; ?> ms-1">
              <?php echo $check['required'] ? 'Pflicht' : 'Optional'; ?>
            </span>
            <div class="small text-body-secondary"><?php echo Security::escape($check['detail']); ?></div>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>

    <?php if (!$allOk): ?>
      <div class="alert alert-danger" role="alert" id="requirements-failed">
        <i class="bi bi-x-octagon-fill" aria-hidden="true"></i>
        Bitte beheben Sie die rot markierten Punkte. Danach
        <a class="alert-link" href="index.php?step=1" id="btn-requirements-reload">prüfen Sie erneut</a>.
      </div>
    <?php endif; ?>
  </div>
  <footer class="card-footer d-flex justify-content-end">
    <form method="post" action="index.php?step=1" id="form-requirements">
      <?php echo CSRF::getTokenField(); ?>
      <button type="submit" name="action" value="requirements" class="btn btn-primary"
              id="btn-requirements-next" <?php echo $allOk ? '' : 'disabled'; ?>>
        Weiter zur Datenbank <i class="bi bi-arrow-right" aria-hidden="true"></i>
      </button>
    </form>
  </footer>
</section>
<?php
};
