<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Web-Installer (Einstieg)
 *
 * Diese Datei verwendet bewusst keine neue PHP-Syntax: Auch ältere
 * PHP-Versionen können sie lesen und zeigen eine verständliche Meldung,
 * statt mit einem Syntaxfehler abzubrechen.
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

$ppbRequiredPhp = '8.4.0';

if (version_compare(PHP_VERSION, $ppbRequiredPhp, '<')) {
    header('Content-Type: text/html; charset=utf-8', true, 500);
    echo '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>PHP-Version zu alt · PowerPHPBoard-Installation</title></head>'
        . '<body style="font-family:sans-serif;max-width:40rem;margin:3rem auto;padding:0 1rem">'
        . '<h1>PHP-Version zu alt</h1>'
        . '<p>PowerPHPBoard benötigt PHP ' . htmlspecialchars($ppbRequiredPhp, ENT_QUOTES, 'UTF-8')
        . ' oder neuer. Auf diesem Server läuft PHP ' . htmlspecialchars(PHP_VERSION, ENT_QUOTES, 'UTF-8') . '.</p>'
        . '<p>Bei den meisten Hostern lässt sich die PHP-Version im Kundenmenü umstellen.</p>'
        . '</body></html>';
    exit;
}

require __DIR__ . '/installer.php';
