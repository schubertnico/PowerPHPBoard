<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - BBCode Reference
 *
 * MIT License
 *
 * Copyright (c) 2024 PowerScripts
 */

use PowerPHPBoard\Security;

include __DIR__ . '/header.inc.php';
?>

<table border="0" cellpadding="2" cellspacing="1" width="100%">

<tr><td colspan="2" bgcolor="<?php echo Security::escape($settings['tablebg3'] ?? '#cccccc'); ?>">
<b><?php echo $lang_bbcommans ?? 'BBCode Commands'; ?></b>
</td></tr>
<tr><td bgcolor="<?php echo Security::escape($settings['tablebg3'] ?? '#cccccc'); ?>" width="50%">
<b><?php echo $lang_command ?? 'Command'; ?></b>
</td><td bgcolor="<?php echo Security::escape($settings['tablebg3'] ?? '#cccccc'); ?>" width="50%">
<b><?php echo $lang_action ?? 'Result'; ?></b>
</td></tr>

<tr><td bgcolor="<?php echo Security::escape($settings['tablebg2'] ?? '#eeeeee'); ?>">
<b>[b]</b>text<b>[/b]</b>
</td><td bgcolor="<?php echo Security::escape($settings['tablebg1'] ?? '#ffffff'); ?>">
<?php echo $lang_bbbold ?? 'Bold text'; ?>
</td></tr>
<tr><td bgcolor="<?php echo Security::escape($settings['tablebg2'] ?? '#eeeeee'); ?>">
<b>[u]</b>text<b>[/u]</b>
</td><td bgcolor="<?php echo Security::escape($settings['tablebg1'] ?? '#ffffff'); ?>">
<?php echo $lang_bbunderlined ?? 'Underlined text'; ?>
</td></tr>
<tr><td bgcolor="<?php echo Security::escape($settings['tablebg2'] ?? '#eeeeee'); ?>">
<b>[i]</b>text<b>[/i]</b>
</td><td bgcolor="<?php echo Security::escape($settings['tablebg1'] ?? '#ffffff'); ?>">
<?php echo $lang_bbitalic ?? 'Italic text'; ?>
</td></tr>

<tr><td bgcolor="<?php echo Security::escape($settings['tablebg2'] ?? '#eeeeee'); ?>">
<b>[url]</b>www.powerscripts.org<b>[/url]</b>
</td><td bgcolor="<?php echo Security::escape($settings['tablebg1'] ?? '#ffffff'); ?>">
<?php echo $lang_bburl ?? 'Link to URL'; ?>
</td></tr>
<tr><td bgcolor="<?php echo Security::escape($settings['tablebg2'] ?? '#eeeeee'); ?>">
<b>[url="http://www.powerscripts.org"]</b>PowerScripts<b>[/url]</b>
</td><td bgcolor="<?php echo Security::escape($settings['tablebg1'] ?? '#ffffff'); ?>">
<?php echo $lang_bburlis ?? 'Link with custom text'; ?>
</td></tr>

<tr><td bgcolor="<?php echo Security::escape($settings['tablebg2'] ?? '#eeeeee'); ?>">
<b>[email]</b>admin@powerscripts.org<b>[/email]</b>
</td><td bgcolor="<?php echo Security::escape($settings['tablebg1'] ?? '#ffffff'); ?>">
<?php echo $lang_bbemail ?? 'Email link'; ?>
</td></tr>

<tr><td bgcolor="<?php echo Security::escape($settings['tablebg2'] ?? '#eeeeee'); ?>">
<b>[quote]</b>Text<b>[/quote]</b>
</td><td bgcolor="<?php echo Security::escape($settings['tablebg1'] ?? '#ffffff'); ?>">
<?php echo $lang_bbquote ?? 'Quote block'; ?>
</td></tr>

</table>

<?php include __DIR__ . '/footer.inc.php'; ?>
