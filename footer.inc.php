<?php declare(strict_types=1);
/**
 * PowerPHPBoard - Footer Include
 *
 * MIT License - Copyright (c) 2024 PowerScripts
 */

use PowerPHPBoard\Security;
?>
  </td></tr>
  </table>
</td></tr>
<tr><td align="center" colspan="2"><br>
<small><a href="https://github.com/schubertnico/PowerPHPBoard">PowerPHPBoard</a> &copy; Copyright 2001-2024 by PowerScripts | MIT License</small>
</td></tr>
</table>

</center>
<?php
// Update last visit time for logged in users
if ($loggedin === 'YES' && isset($ppbuser['id'])) {
    $now = time();
    $db->query("UPDATE ppb_users SET lastvisit = ? WHERE id = ?", [$now, $ppbuser['id']]);
}

// Include custom footer template if set
$footerFile = $settings['footer'] ?? '';
if ($footerFile !== '' && file_exists(__DIR__ . '/inc/' . $footerFile)) {
    include __DIR__ . '/inc/' . $footerFile;
} else {
    include __DIR__ . '/inc/footer.ppb';
}
?>
