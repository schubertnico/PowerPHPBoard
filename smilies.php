<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Smilies Reference
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

use PowerPHPBoard\Security;

include __DIR__ . '/header.inc.php';

$smilies = [
    [':)',         'smile.gif'],
    [';)',         'wink.gif'],
    [':D',         'biggrin.gif'],
    [':P',         'tongue.gif'],
    [':(',         'frown.gif'],
    [':o',         'redface.gif'],
    [':rolleyes:', 'rolleyes.gif'],
    [':cool:',     'cool.gif'],
    [':confused:', 'confused.gif'],
    [':eek:',      'eek.gif'],
    [':mad:',      'mad.gif'],
];
?>

<section class="card shadow-sm mb-4">
  <header class="card-header bg-secondary-subtle d-flex align-items-center gap-2">
    <i class="bi bi-emoji-smile" aria-hidden="true"></i>
    <h1 class="h5 mb-0"><?php echo $lang_smilielist ?? 'Smilies List'; ?></h1>
  </header>
  <div class="table-responsive">
    <table class="table table-striped align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th scope="col" style="width:50%;"><?php echo $lang_text ?? 'Text'; ?></th>
          <th scope="col"><?php echo $lang_image ?? 'Image'; ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($smilies as [$text, $img]): ?>
          <tr>
            <td><code><?php echo Security::escape($text); ?></code></td>
            <td><img src="images/<?php echo Security::escape($img); ?>" width="15" height="15" alt="<?php echo Security::escape($text); ?>"></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<?php include __DIR__ . '/footer.inc.php'; ?>
