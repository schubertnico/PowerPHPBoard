<?php

declare(strict_types=1);

/**
 * Mock-SMTP-Server für Mailer-Tests.
 *
 * Wird per proc_open als Unterprozess gestartet:
 *   php mock-smtp-server.php <port> <szenario>
 *
 * port = 0: das Betriebssystem wählt einen freien Port. Die erste Zeile auf
 * STDOUT ist der tatsächliche Port, danach folgt jede empfangene Zeile als
 * "C: <zeile>" (Nachrichtentext nach DATA als "D: <zeile>").
 *
 * Ein Szenario ist eine Liste von Antworten. Die erste ist die Begrüßung,
 * jede weitere wird nach einer empfangenen Zeile gesendet. Mehrzeilige
 * Antworten sind durch "\n" getrennt. Anhängsel:
 *   |data   nach der Antwort den Nachrichtentext bis zur Zeile "." lesen
 *   |close  nach der Antwort die Verbindung schließen
 *   |hold   nichts senden, nur mitlesen, bis der Client auflegt
 *
 * Szenarien:
 *   ok                 Standard-Dialog ohne Erweiterungen, Mail wird angenommen
 *   multiline-220      zweizeilige Begrüßung
 *   reject-helo        erster Befehl wird mit 500 abgelehnt, dann Ende
 *   ehlo-unknown       EHLO mit 502 abgelehnt, HELO angenommen
 *   auth-optional      bietet AUTH an, nimmt die Mail aber auch ohne Anmeldung an
 *   auth-plain         bietet AUTH LOGIN PLAIN an, Anmeldung klappt
 *   auth-login         bietet nur AUTH LOGIN an
 *   auth-legacy        bietet AUTH nur in alter Schreibweise AUTH=LOGIN an
 *   auth-rejected      Anmeldung wird mit 535 abgelehnt
 *   auth-cram          bietet nur CRAM-MD5 an
 *   no-auth            bietet keine Anmeldung an
 *   no-starttls        bietet AUTH, aber kein STARTTLS an
 *   starttls-broken    bestätigt STARTTLS mit 220 und legt dann auf (TLS scheitert)
 *   starttls-refused   lehnt STARTTLS mit 454 ab
 *   no-quit            legt nach angenommener Nachricht ohne 221 auf
 *   silent             nimmt die Verbindung an und schweigt
 */

$port = (int) ($argv[1] ?? 0);
$scenario = $argv[2] ?? 'ok';

$greeting = '220 mock.smtp ESMTP';
$mail = ['250 2.1.0 OK', '250 2.1.5 OK', '354 Start mail input|data', '250 2.0.0 Message accepted', '221 2.0.0 Bye'];
$authExtensions = "250-mock.smtp Hello\n250-AUTH LOGIN PLAIN\n250 8BITMIME";

$scenarios = [
    'ok' => [$greeting, '250 Hello', ...$mail],
    'multiline-220' => ["220-mock.smtp ESMTP greeting line 1\n220 mock.smtp greeting line 2", '250 Hello', ...$mail],
    'reject-helo' => [$greeting, '500 Command not recognized|close'],
    'ehlo-unknown' => [$greeting, '502 5.5.1 Command not implemented', '250 Hello', ...$mail],
    'auth-optional' => [$greeting, $authExtensions, ...$mail],
    'auth-plain' => [$greeting, $authExtensions, '235 2.7.0 Authentication successful', ...$mail],
    'auth-login' => [$greeting, "250-mock.smtp Hello\n250 AUTH LOGIN", '334 VXNlcm5hbWU6', '334 UGFzc3dvcmQ6', '235 2.7.0 Authentication successful', ...$mail],
    'auth-legacy' => [$greeting, "250-mock.smtp Hello\n250 AUTH=LOGIN", '334 VXNlcm5hbWU6', '334 UGFzc3dvcmQ6', '235 2.7.0 Authentication successful', ...$mail],
    'auth-rejected' => [$greeting, $authExtensions, '535 5.7.8 Authentication credentials invalid', '221 2.0.0 Bye|close'],
    'auth-cram' => [$greeting, "250-mock.smtp Hello\n250 AUTH CRAM-MD5", '221 2.0.0 Bye|close'],
    'no-auth' => [$greeting, "250-mock.smtp Hello\n250 8BITMIME", '221 2.0.0 Bye|close'],
    'no-starttls' => [$greeting, $authExtensions, '221 2.0.0 Bye|close'],
    'starttls-broken' => [$greeting, "250-mock.smtp Hello\n250-STARTTLS\n250 AUTH LOGIN PLAIN", '220 2.0.0 Ready to start TLS|close'],
    'starttls-refused' => [$greeting, "250-mock.smtp Hello\n250 STARTTLS", '454 4.7.0 TLS not available due to temporary reason|close'],
    'no-quit' => [$greeting, '250 Hello', '250 2.1.0 OK', '250 2.1.5 OK', '354 Start mail input|data', '250 2.0.0 Message accepted|close'],
    'silent' => ['|hold'],
];

if (!isset($scenarios[$scenario])) {
    fwrite(STDERR, "unknown scenario: $scenario\n");
    exit(1);
}

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
echo (int) substr($name, (int) strrpos($name, ':') + 1) . "\n";
fflush(STDOUT);

$conn = @stream_socket_accept($server, 5);
if ($conn === false) {
    fwrite(STDERR, "accept failed\n");
    fclose($server);
    exit(2);
}
stream_set_timeout($conn, 5);

/** Liest eine Zeile, schreibt sie mit Präfix auf STDOUT; null = Client weg */
$receive = static function (string $prefix) use ($conn): ?string {
    $line = fgets($conn);
    if ($line === false) {
        return null;
    }
    $line = rtrim($line, "\r\n");
    echo $prefix . preg_replace('/[^\x20-\x7E]/', '?', $line) . "\n";
    fflush(STDOUT);

    return $line;
};

// Die Begrüßung und die Antwort auf den Nachrichtentext kommen ohne neuen Befehl
$awaitCommand = false;
foreach ($scenarios[$scenario] as $step) {
    if ($awaitCommand && $receive('C: ') === null) {
        break;
    }
    $awaitCommand = true;

    [$reply, $action] = array_pad(explode('|', $step, 2), 2, '');
    if ($reply !== '') {
        foreach (explode("\n", $reply) as $line) {
            @fwrite($conn, $line . "\r\n");
        }
        fflush($conn);
    }

    if ($action === 'data') {
        while (($line = $receive('D: ')) !== null && $line !== '.') {
            // Nachrichtentext mitlesen bis zur Zeile "."
        }
        $awaitCommand = false;
    } elseif ($action === 'hold') {
        while ($receive('C: ') !== null) {
            // mitlesen, bis der Client auflegt
        }
    }
    if ($action === 'close' || $action === 'hold') {
        break;
    }
}

fclose($conn);
fclose($server);
exit(0);
