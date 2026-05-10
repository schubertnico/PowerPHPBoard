<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - BBCode Reference
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

use PowerPHPBoard\Security;

include __DIR__ . '/header.inc.php';

$rows = [
    ['[b]text[/b]',                                         $lang_bbbold ?? 'Bold text'],
    ['[u]text[/u]',                                         $lang_bbunderlined ?? 'Underlined text'],
    ['[i]text[/i]',                                         $lang_bbitalic ?? 'Italic text'],
    ['[url]www.powerscripts.org[/url]',                     $lang_bburl ?? 'Link to URL'],
    ['[url="http://www.powerscripts.org"]PowerScripts[/url]', $lang_bburlis ?? 'Link with custom text'],
    ['[email]admin@powerscripts.org[/email]',               $lang_bbemail ?? 'Email link'],
    ['[quote]Text[/quote]',                                 $lang_bbquote ?? 'Quote block'],
];
?>

<section class="card shadow-sm mb-4">
  <header class="card-header bg-secondary-subtle d-flex align-items-center gap-2">
    <i class="bi bi-code-slash" aria-hidden="true"></i>
    <h1 class="h5 mb-0"><?php echo $lang_bbcommans ?? 'BBCode Commands'; ?></h1>
  </header>
  <div class="table-responsive">
    <table class="table table-striped align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th scope="col" style="width:50%;"><?php echo $lang_command ?? 'Command'; ?></th>
          <th scope="col"><?php echo $lang_action ?? 'Result'; ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as [$cmd, $desc]): ?>
          <tr>
            <td><code><?php echo Security::escape($cmd); ?></code></td>
            <td><?php echo Security::escape($desc); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<?php include __DIR__ . '/footer.inc.php'; ?>
