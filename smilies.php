<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Smilies Reference
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

use PowerPHPBoard\Security;
use PowerPHPBoard\TextFormatter;

include __DIR__ . '/header.inc.php';

// Dieselbe Liste, die auch der TextFormatter verwendet
$smilies = [];
foreach (TextFormatter::getSmilies() as $code => $info) {
    $smilies[] = [$code, $info['file'], $info['width'], $info['height']];
}
?>

<section class="card shadow-sm mb-4">
  <header class="card-header bg-secondary-subtle d-flex align-items-center gap-2">
    <i class="bi bi-emoji-smile" aria-hidden="true"></i>
    <h1 class="h5 mb-0"><?php echo Security::escape($lang_smilielist ?? 'Smilies List'); ?></h1>
  </header>
  <div class="table-responsive">
    <table class="table table-striped align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th scope="col" style="width:50%;"><?php echo Security::escape($lang_text ?? 'Text'); ?></th>
          <th scope="col"><?php echo Security::escape($lang_image ?? 'Image'); ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($smilies as [$text, $img, $width, $height]): ?>
          <tr>
            <td><code><?php echo Security::escape($text); ?></code></td>
            <td><img src="images/<?php echo Security::escape($img); ?>" width="<?php echo (int) $width; ?>" height="<?php echo (int) $height; ?>" alt="<?php echo Security::escape($text); ?>"></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<?php include __DIR__ . '/footer.inc.php'; ?>
