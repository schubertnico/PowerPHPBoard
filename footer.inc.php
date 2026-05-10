<?php declare(strict_types=1);
/**
 * PowerPHPBoard - Footer Include
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */
?>
</main>

<footer class="bg-dark text-light py-3 mt-auto">
  <div class="container-xl text-center">
    <small>
      PowerPHPBoard &copy; 2001-2026
      <a class="link-light" href="https://www.powerscripts.org" target="_blank" rel="noopener">PowerScripts</a>
    </small>
  </div>
</footer>

<?php
// Update last visit time for logged in users
if ($loggedin === 'YES' && isset($ppbuser['id']) && isset($db)) {
    $now = time();
    $db->query('UPDATE ppb_users SET lastvisit = ? WHERE id = ?', [$now, $ppbuser['id']]);
}

// Include custom footer template if set
$footerFile = $settings['footer'] ?? '';
if ($footerFile !== '' && file_exists(__DIR__ . '/inc/' . $footerFile)) {
    include __DIR__ . '/inc/' . $footerFile;
} else {
    include __DIR__ . '/inc/footer.ppb';
}
?>
