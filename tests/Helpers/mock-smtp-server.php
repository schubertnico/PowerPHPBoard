<?php

declare(strict_types=1);

/**
 * Mock-SMTP-Server fuer Mailer-Tests.
 *
 * Wird via proc_open als Subprozess gestartet. Argumente:
 *   php mock-smtp-server.php <port> <scenario>
 *
 * port = 0 -> OS waehlt freien Port; effektiver Port wird
 * auf STDOUT als erste Zeile ausgegeben.
 *
 * scenario:
 *   ok               - Standard-Konversation, akzeptiert Mail
 *   multiline-220    - sendet 220-Greeting in zwei Zeilen
 *   reject-helo      - antwortet HELO mit 500
 */

$port = (int) ($argv[1] ?? 0);
$scenario = $argv[2] ?? 'ok';

$server = @stream_socket_server("tcp://127.0.0.1:$port", $errno, $errstr);
if ($server === false) {
    fwrite(STDERR, "server failed: $errstr ($errno)\n");
    exit(1);
}

$name = stream_socket_get_name($server, false);
if ($name === false) {
    fwrite(STDERR, "could not read server name\n");
    exit(1);
}
$actualPort = (int) substr($name, (int) strrpos($name, ':') + 1);
echo $actualPort . "\n";

$conn = @stream_socket_accept($server, 5);
if ($conn === false) {
    fwrite(STDERR, "accept failed\n");
    fclose($server);
    exit(2);
}
stream_set_timeout($conn, 5);

$send = static function (string $msg) use ($conn): void {
    fwrite($conn, $msg . "\r\n");
    fflush($conn);
};

$recv = static function () use ($conn): string {
    $line = fgets($conn);
    return $line === false ? '' : $line;
};

if ($scenario === 'multiline-220') {
    $send('220-mock.smtp ESMTP greeting line 1');
    $send('220 mock.smtp greeting line 2');
} else {
    $send('220 mock.smtp ESMTP');
}
$recv(); // HELO

if ($scenario === 'reject-helo') {
    $send('500 Command not recognized');
    fclose($conn);
    fclose($server);
    exit(0);
}

$send('250 Hello');
$recv(); // MAIL FROM
$send('250 OK');
$recv(); // RCPT TO
$send('250 OK');
$recv(); // DATA
$send('354 Start mail input');

while (!feof($conn)) {
    $line = fgets($conn);
    if ($line === false) {
        break;
    }
    if (rtrim($line, "\r\n") === '.') {
        break;
    }
}
$send('250 Message accepted');
$recv(); // QUIT
$send('221 Bye');

fclose($conn);
fclose($server);
exit(0);
