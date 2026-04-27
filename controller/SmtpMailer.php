<?php
declare(strict_types=1);

class SmtpMailer
{
    private string $host;
    private int $port;
    private string $encryption;
    private string $username;
    private string $password;
    private string $fromEmail;
    private string $fromName;
    private int $timeout;
    private string $lastError = '';

    public function __construct(array $config)
    {
        $this->host = (string) ($config['host'] ?? '');
        $this->port = (int) ($config['port'] ?? 0);
        $this->encryption = strtolower((string) ($config['encryption'] ?? 'ssl'));
        $this->username = (string) ($config['username'] ?? '');
        $this->password = (string) ($config['password'] ?? '');
        $this->fromEmail = (string) ($config['from_email'] ?? '');
        $this->fromName = (string) ($config['from_name'] ?? 'SecondVoice');
        $this->timeout = max(5, (int) ($config['timeout'] ?? 20));
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }

    public function send(string $to, string $subject, string $body): bool
    {
        $this->lastError = '';
        $to = trim($to);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->lastError = 'Adresse destinataire invalide.';
            return false;
        }

        if ($this->host === '' || $this->port <= 0) {
            $this->lastError = 'Configuration SMTP incomplete (host/port).';
            return false;
        }

        if ($this->username === '' || $this->password === '') {
            $this->lastError = 'Configuration SMTP incomplete (username/password).';
            return false;
        }

        $fromEmail = $this->fromEmail !== '' ? $this->fromEmail : $this->username;
        if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            $this->lastError = "Adresse d'expediteur invalide.";
            return false;
        }

        $remoteHost = $this->encryption === 'ssl'
            ? 'ssl://' . $this->host . ':' . $this->port
            : $this->host . ':' . $this->port;

        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);

        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client(
            $remoteHost,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!is_resource($socket)) {
            $this->lastError = "Connexion SMTP impossible: {$errstr} ({$errno})";
            return false;
        }

        stream_set_timeout($socket, $this->timeout);

        try {
            $this->assertCode($this->readResponse($socket), [220]);
            $this->command($socket, 'EHLO localhost', [250]);

            if ($this->encryption === 'tls') {
                $this->command($socket, 'STARTTLS', [220]);
                $enabled = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                if ($enabled !== true) {
                    throw new RuntimeException('Activation TLS impossible.');
                }
                $this->command($socket, 'EHLO localhost', [250]);
            }

            $this->command($socket, 'AUTH LOGIN', [334]);
            $this->command($socket, base64_encode($this->username), [334]);
            $this->command($socket, base64_encode($this->password), [235]);

            $this->command($socket, 'MAIL FROM:<' . $fromEmail . '>', [250]);
            $this->command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
            $this->command($socket, 'DATA', [354]);

            $message = $this->buildMessage($fromEmail, $to, $subject, $body);
            fwrite($socket, $message . "\r\n.\r\n");
            $this->assertCode($this->readResponse($socket), [250]);

            $this->command($socket, 'QUIT', [221]);
            fclose($socket);
            return true;
        } catch (Throwable $exception) {
            $this->lastError = $exception->getMessage();
            @fwrite($socket, "QUIT\r\n");
            @fclose($socket);
            return false;
        }
    }

    private function command($socket, string $command, array $expectedCodes): void
    {
        fwrite($socket, $command . "\r\n");
        $this->assertCode($this->readResponse($socket), $expectedCodes);
    }

    private function readResponse($socket): array
    {
        $code = 0;
        $lines = [];

        while (!feof($socket)) {
            $line = fgets($socket, 515);
            if ($line === false) {
                break;
            }
            $line = rtrim($line, "\r\n");
            $lines[] = $line;

            if (preg_match('/^(\d{3})([\s-])/', $line, $matches)) {
                $code = (int) $matches[1];
                if ($matches[2] === ' ') {
                    break;
                }
            } else {
                break;
            }
        }

        return ['code' => $code, 'lines' => $lines];
    }

    private function assertCode(array $response, array $expectedCodes): void
    {
        $code = (int) ($response['code'] ?? 0);
        if (!in_array($code, $expectedCodes, true)) {
            $message = implode(' | ', (array) ($response['lines'] ?? []));
            throw new RuntimeException("SMTP error {$code}: {$message}");
        }
    }

    private function buildMessage(string $fromEmail, string $to, string $subject, string $body): string
    {
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $encodedFromName = '=?UTF-8?B?' . base64_encode($this->fromName) . '?=';
        $headers = [];
        $headers[] = 'Date: ' . date('r');
        $headers[] = 'From: ' . $encodedFromName . ' <' . $fromEmail . '>';
        $headers[] = 'To: <' . $to . '>';
        $headers[] = 'Subject: ' . $encodedSubject;
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: 8bit';

        $normalizedBody = str_replace(["\r\n", "\r"], "\n", $body);
        $normalizedBody = str_replace("\n.", "\n..", $normalizedBody);
        $normalizedBody = str_replace("\n", "\r\n", $normalizedBody);

        return implode("\r\n", $headers) . "\r\n\r\n" . $normalizedBody;
    }
}
?>
