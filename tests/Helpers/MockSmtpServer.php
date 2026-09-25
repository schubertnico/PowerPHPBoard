<?php

declare(strict_types=1);

namespace PowerPHPBoard\Tests\Helpers;

/**
 * Startet mock-smtp-server.php für ein Szenario und liefert neben dem
 * Ergebnis den Mitschnitt der vom Client gesendeten Zeilen.
 */
trait MockSmtpServer
{
    /**
     * @template T
     *
     * @param callable(int): T $send erhält den Port des Mock-Servers
     *
     * @return array{0: T, 1: string} Ergebnis von $send und Mitschnitt
     */
    private function converse(string $scenario, callable $send): array
    {
        $proc = proc_open(
            [PHP_BINARY, __DIR__ . '/mock-smtp-server.php', '0', $scenario],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes
        );
        if (!is_resource($proc)) {
            $this->fail('Konnte Mock-SMTP-Server nicht starten');
        }

        try {
            // Die erste Zeile enthält den Port. fgets blockiert, bis der Server
            // gebunden hat – beim Verbinden lauscht er also garantiert.
            $portLine = fgets($pipes[1]);
            $port = $portLine === false ? 0 : (int) trim($portLine);
            if ($port <= 0) {
                $this->fail('Mock-Server lieferte keinen Port. STDERR: ' . (string) stream_get_contents($pipes[2]));
            }

            $result = $send($port);

            // Der Server beendet sich nach dem Gespräch selbst (spätestens nach 5 s ohne Daten).
            $transcript = (string) stream_get_contents($pipes[1]);
        } finally {
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
            if (proc_get_status($proc)['running']) {
                proc_terminate($proc);
            }
            proc_close($proc);
        }

        return [$result, $transcript];
    }

    /**
     * Befehle aus dem Mitschnitt; EHLO/HELO ohne Namen.
     *
     * @return list<string>
     */
    private function commands(string $transcript): array
    {
        preg_match_all('/^C: (.*)$/m', $transcript, $matches);

        return array_map(
            static fn (string $line): string => (string) preg_replace('/^(EHLO|HELO) .*/', '$1', $line),
            $matches[1]
        );
    }
}
