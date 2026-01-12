<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Statistics
 *
 * MIT License
 *
 * Copyright (c) 2024 PowerScripts
 */

use PowerPHPBoard\Security;

include __DIR__ . '/header.inc.php';
?>

<table border="0" cellpadding="2" cellspacing="1" width="100%">

<tr><td bgcolor="<?php echo Security::escape($settings['tablebg3'] ?? '#cccccc'); ?>" colspan="2">
<b><?php echo $lang_statistics ?? 'Statistics'; ?></b>
</td></tr>
<?php
$allusersResult = $db->fetchOne('SELECT COUNT(*) as count FROM ppb_users');
$allthreadsResult = $db->fetchOne("SELECT COUNT(*) as count FROM ppb_posts WHERE type = 'Thread'");
$allpostingsResult = $db->fetchOne('SELECT COUNT(*) as count FROM ppb_posts');

$allusers = (int) ($allusersResult['count'] ?? 0);
$allthreads = (int) ($allthreadsResult['count'] ?? 0);
$allpostings = (int) ($allpostingsResult['count'] ?? 0);
?>
<tr><td bgcolor="<?php echo Security::escape($settings['tablebg2'] ?? '#eeeeee'); ?>" valign="top">
<?php echo $lang_numregistered ?? 'Registered users'; ?>
</td><td bgcolor="<?php echo Security::escape($settings['tablebg1'] ?? '#ffffff'); ?>" align="center" width="300">
<?php echo $allusers; ?>
</td></tr>

<tr><td bgcolor="<?php echo Security::escape($settings['tablebg2'] ?? '#eeeeee'); ?>" valign="top">
<?php echo $lang_numthreads ?? 'Threads'; ?>
</td><td bgcolor="<?php echo Security::escape($settings['tablebg1'] ?? '#ffffff'); ?>" align="center" width="300">
<?php echo $allthreads; ?>
</td></tr>

<tr><td bgcolor="<?php echo Security::escape($settings['tablebg2'] ?? '#eeeeee'); ?>" valign="top">
<?php echo $lang_numposts ?? 'Posts'; ?>
</td><td bgcolor="<?php echo Security::escape($settings['tablebg1'] ?? '#ffffff'); ?>" align="center" width="300">
<?php echo $allpostings; ?>
</td></tr>

</table>

<?php include __DIR__ . '/footer.inc.php'; ?>
