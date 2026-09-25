<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Web-Installer: Seitenlayout
 *
 * Liefert eine Funktion; direkt aufgerufen gibt die Datei nichts aus.
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

use PowerPHPBoard\Installer\Wizard;
use PowerPHPBoard\Security;

/**
 * @param int $step aktueller Schritt (0 = ohne Schrittanzeige)
 * @param int $completed höchster vollständig erledigter Schritt
 * @param callable(): void $content
 */
return static function (string $title, int $step, int $completed, callable $content): void {
    $total = count(Wizard::STEPS);
    $progress = $step > 0 ? (int) round(($step - 1) / ($total - 1) * 100) : 100;
    ?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo Security::escape($title); ?> · PowerPHPBoard-Installation</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="../ppb.css">
</head>
<body class="bg-body-tertiary d-flex flex-column min-vh-100">
<nav class="navbar navbar-dark bg-dark" aria-label="Installation">
  <div class="container-lg">
    <span class="navbar-brand fw-semibold">
      <i class="bi bi-chat-square-text-fill" aria-hidden="true"></i>
      PowerPHPBoard
      <span class="badge text-bg-warning ms-1 align-middle">Installation</span>
    </span>
  </div>
</nav>

<main class="container-lg py-4 flex-grow-1" role="main" id="installer">
  <div class="row justify-content-center">
    <div class="col-lg-10 col-xl-8">
      <h1 class="h3 mb-3"><?php echo Security::escape($title); ?></h1>

      <?php if ($step > 0): ?>
        <nav class="mb-4" aria-label="Installationsschritte">
          <div class="progress mb-2" role="progressbar" aria-label="Fortschritt"
               aria-valuenow="<?php echo $progress; ?>" aria-valuemin="0" aria-valuemax="100" style="height: 6px;">
            <div class="progress-bar" style="width: <?php echo $progress; ?>%"></div>
          </div>
          <ol class="list-unstyled d-flex flex-wrap gap-2 mb-0" id="installer-steps">
            <?php foreach (Wizard::STEPS as $number => $label): ?>
              <?php
                  $isCurrent = $number === $step;
                $isReachable = $number <= $completed + 1;
                $class = $isCurrent
                    ? 'text-bg-primary'
                    : ($number <= $completed ? 'text-bg-success' : 'text-bg-light border text-body-secondary');
                $icon = $number <= $completed && !$isCurrent ? 'bi-check-lg' : 'bi-' . $number . '-circle';
                ?>
              <li>
                <?php if ($isReachable && !$isCurrent): ?>
                  <a class="badge rounded-pill text-decoration-none <?php echo $class; ?>"
                     id="step-link-<?php echo $number; ?>" href="index.php?step=<?php echo $number; ?>">
                    <i class="bi <?php echo $icon; ?>" aria-hidden="true"></i>
                    <?php echo Security::escape($label); ?>
                  </a>
                <?php else: ?>
                  <span class="badge rounded-pill <?php echo $class; ?>" id="step-link-<?php echo $number; ?>"
                        <?php echo $isCurrent ? 'aria-current="step"' : ''; ?>>
                    <i class="bi <?php echo $icon; ?>" aria-hidden="true"></i>
                    <?php echo Security::escape($label); ?>
                  </span>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ol>
          <p class="small text-body-secondary mt-2 mb-0">
            Schritt <?php echo $step; ?> von <?php echo $total; ?>
          </p>
        </nav>
      <?php endif; ?>

      <?php $content(); ?>
    </div>
  </div>
</main>

<footer class="bg-dark text-light py-3 mt-auto">
  <div class="container-lg text-center">
    <small>
      PowerPHPBoard &copy; 2001-2026
      <a class="link-light" href="https://www.powerscripts.org" target="_blank" rel="noopener">PowerScripts</a>
    </small>
  </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script>
(function () {
  document.querySelectorAll('form.needs-validation').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (!form.checkValidity()) {
        event.preventDefault();
        event.stopPropagation();
      }
      form.classList.add('was-validated');
    }, false);
  });
})();
</script>
</body>
</html>
<?php
};
