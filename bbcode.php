<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - BBCode Reference
 *
 * Die Beispiele werden mit demselben TextFormatter dargestellt wie Beiträge,
 * die Hilfe zeigt also genau das, was das Forum unterstützt.
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

use PowerPHPBoard\Security;
use PowerPHPBoard\TextFormatter;

include __DIR__ . '/header.inc.php';

$labels = ['quote' => $lang_quote ?? 'Quote:', 'image' => $lang_image ?? 'Image'];

// [Beispiel, Beschreibung, Ergebnis anzeigen?]
$rows = [
    ['[b]' . ($lang_bbexampletext ?? 'Text') . '[/b]', $lang_bbbold ?? 'Bold text', true],
    ['[i]' . ($lang_bbexampletext ?? 'Text') . '[/i]', $lang_bbitalic ?? 'Italic text', true],
    ['[u]' . ($lang_bbexampletext ?? 'Text') . '[/u]', $lang_bbunderlined ?? 'Underlined text', true],
    ['[s]' . ($lang_bbexampletext ?? 'Text') . '[/s]', $lang_bbstrike ?? 'Strikethrough text', true],
    ['[quote]' . ($lang_bbexampletext ?? 'Text') . '[/quote]', $lang_bbquote ?? 'Quote', true],
    ["[code]echo 'Hallo';[/code]", $lang_bbcodeblock ?? 'Code block (shown as written)', true],
    ['[url]https://www.powerscripts.org[/url]', $lang_bburl ?? 'Link to a web address', true],
    ['[url=https://www.powerscripts.org]PowerScripts[/url]', $lang_bburlis ?? 'Link with its own text', true],
    ['[img]https://www.example.org/image.png[/img]', $lang_bbimg ?? 'Image from a web address', false],
    ['https://www.powerscripts.org admin@powerscripts.org', $lang_bbautolink ?? 'Web and email addresses are linked automatically', true],
];
?>

<section class="card shadow-sm mb-4">
  <header class="card-header bg-secondary-subtle d-flex align-items-center gap-2">
    <i class="bi bi-code-slash" aria-hidden="true"></i>
    <h1 class="h5 mb-0"><?php echo Security::escape($lang_bbcommans ?? 'BBCode commands'); ?></h1>
  </header>
  <div class="card-body pb-0">
    <p class="text-body-secondary small mb-3">
      <?php echo Security::escape($lang_bbintro ?? 'Addresses in [url] and [img] must start with http:// or https://; www. addresses are completed automatically.'); ?>
    </p>
  </div>
  <div class="table-responsive">
    <table class="table table-striped align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th scope="col" style="width:40%;"><?php echo Security::escape($lang_command ?? 'Command'); ?></th>
          <th scope="col" style="width:30%;"><?php echo Security::escape($lang_description ?? 'Description'); ?></th>
          <th scope="col"><?php echo Security::escape($lang_action ?? 'Result'); ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as [$example, $description, $showResult]): ?>
          <tr>
            <td><code><?php echo Security::escape($example); ?></code></td>
            <td><?php echo Security::escape($description); ?></td>
            <td class="post-content">
              <?php if ($showResult): ?>
                <?php echo TextFormatter::formatPost($example, 'ON', 'OFF', 'OFF', $labels); ?>
              <?php else: ?>
                <span class="text-body-secondary"><?php echo Security::escape($lang_bbimgresult ?? 'The image is displayed.'); ?></span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if (strtoupper((string) ($settings['htmlcode'] ?? 'OFF')) === 'ON'): ?>
    <footer class="card-footer bg-light small">
      <?php echo Security::escape($lang_htmlallowedtags ?? 'HTML is enabled. These tags are allowed, without attributes:'); ?>
      <code><?php echo Security::escape('<' . implode('> <', TextFormatter::ALLOWED_HTML_TAGS) . '>'); ?></code>
    </footer>
  <?php endif; ?>
</section>

<?php include __DIR__ . '/footer.inc.php'; ?>
