<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Admin Index
 *
 * MIT License
 *
 * Copyright (c) 2026 PowerScripts
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
 */

use PowerPHPBoard\Security;

include __DIR__ . '/header.inc.php';
?>

<table border="0" cellpadding="2" cellspacing="1" width="100%">
<tr><td bgcolor="<?php echo Security::escape($admin_tbl3); ?>">
<b>Administrator functions</b>
</td></tr>
<tr><td bgcolor="<?php echo Security::escape($admin_tbl2); ?>"><br>
Please choose a function:<br>
<br>
<center>
<a href="general.php">General administration</a><br>
<br>
<a href="user.php">User administration</a><br>
<br>
<a href="boards.php">Board administration</a><br>
<br>

</center>
</td></tr>
</table>

<?php include __DIR__ . '/footer.inc.php'; ?>
