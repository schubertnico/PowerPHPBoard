<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Smilies Reference
 *
 * MIT License
 *
 * Copyright (c) 2026 PowerScripts
 */

use PowerPHPBoard\Security;

include __DIR__ . '/header.inc.php';
?>

<table border="0" cellpadding="2" cellspacing="1" width="100%">

<tr><td colspan="2" bgcolor="<?php echo Security::escape($settings['tablebg3'] ?? '#cccccc'); ?>">
<b><?php echo $lang_smilielist ?? 'Smilies List'; ?></b>
</td></tr>
<tr><td bgcolor="<?php echo Security::escape($settings['tablebg3'] ?? '#cccccc'); ?>" width="50%">
<b><?php echo $lang_text ?? 'Text'; ?></b>
</td><td bgcolor="<?php echo Security::escape($settings['tablebg3'] ?? '#cccccc'); ?>" width="50%">
<b><?php echo $lang_image ?? 'Image'; ?></b>
</td></tr>

<tr><td bgcolor="<?php echo Security::escape($settings['tablebg2'] ?? '#eeeeee'); ?>" align="center">
<b>:)</b>
</td><td bgcolor="<?php echo Security::escape($settings['tablebg1'] ?? '#ffffff'); ?>" align="center">
<img src="images/smile.gif" width="15" height="15" border="0" alt="">
</td></tr>
<tr><td bgcolor="<?php echo Security::escape($settings['tablebg2'] ?? '#eeeeee'); ?>" align="center">
<b>;)</b>
</td><td bgcolor="<?php echo Security::escape($settings['tablebg1'] ?? '#ffffff'); ?>" align="center">
<img src="images/wink.gif" width="15" height="15" border="0" alt="">
</td></tr>
<tr><td bgcolor="<?php echo Security::escape($settings['tablebg2'] ?? '#eeeeee'); ?>" align="center">
<b>:D</b>
</td><td bgcolor="<?php echo Security::escape($settings['tablebg1'] ?? '#ffffff'); ?>" align="center">
<img src="images/biggrin.gif" width="15" height="15" border="0" alt="">
</td></tr>
<tr><td bgcolor="<?php echo Security::escape($settings['tablebg2'] ?? '#eeeeee'); ?>" align="center">
<b>:P</b>
</td><td bgcolor="<?php echo Security::escape($settings['tablebg1'] ?? '#ffffff'); ?>" align="center">
<img src="images/tongue.gif" width="15" height="15" border="0" alt="">
</td></tr>
<tr><td bgcolor="<?php echo Security::escape($settings['tablebg2'] ?? '#eeeeee'); ?>" align="center">
<b>:(</b>
</td><td bgcolor="<?php echo Security::escape($settings['tablebg1'] ?? '#ffffff'); ?>" align="center">
<img src="images/frown.gif" width="15" height="15" border="0" alt="">
</td></tr>
<tr><td bgcolor="<?php echo Security::escape($settings['tablebg2'] ?? '#eeeeee'); ?>" align="center">
<b>:o</b>
</td><td bgcolor="<?php echo Security::escape($settings['tablebg1'] ?? '#ffffff'); ?>" align="center">
<img src="images/redface.gif" width="15" height="15" border="0" alt="">
</td></tr>
<tr><td bgcolor="<?php echo Security::escape($settings['tablebg2'] ?? '#eeeeee'); ?>" align="center">
<b>:rolleyes:</b>
</td><td bgcolor="<?php echo Security::escape($settings['tablebg1'] ?? '#ffffff'); ?>" align="center">
<img src="images/rolleyes.gif" width="15" height="15" border="0" alt="">
</td></tr>
<tr><td bgcolor="<?php echo Security::escape($settings['tablebg2'] ?? '#eeeeee'); ?>" align="center">
<b>:cool:</b>
</td><td bgcolor="<?php echo Security::escape($settings['tablebg1'] ?? '#ffffff'); ?>" align="center">
<img src="images/cool.gif" width="15" height="15" border="0" alt="">
</td></tr>
<tr><td bgcolor="<?php echo Security::escape($settings['tablebg2'] ?? '#eeeeee'); ?>" align="center">
<b>:confused:</b>
</td><td bgcolor="<?php echo Security::escape($settings['tablebg1'] ?? '#ffffff'); ?>" align="center">
<img src="images/confused.gif" width="15" height="20" border="0" alt="">
</td></tr>
<tr><td bgcolor="<?php echo Security::escape($settings['tablebg2'] ?? '#eeeeee'); ?>" align="center">
<b>:eek:</b>
</td><td bgcolor="<?php echo Security::escape($settings['tablebg1'] ?? '#ffffff'); ?>" align="center">
<img src="images/eek.gif" width="15" height="15" border="0" alt="">
</td></tr>
<tr><td bgcolor="<?php echo Security::escape($settings['tablebg2'] ?? '#eeeeee'); ?>" align="center">
<b>:mad:</b>
</td><td bgcolor="<?php echo Security::escape($settings['tablebg1'] ?? '#ffffff'); ?>" align="center">
<img src="images/mad.gif" width="15" height="15" border="0" alt="">
</td></tr>

</table>

<?php include __DIR__ . '/footer.inc.php'; ?>
