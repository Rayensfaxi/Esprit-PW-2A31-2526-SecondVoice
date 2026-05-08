<?php
declare(strict_types=1);

class BrevoMailer
{
    private string $apiKey;
    private string $fromEmail;
    private string $fromName;
    private int $timeout;
    private string $lastError = '';

    public function __construct(array $config)
    {
        $this->apiKey = (string) ($config['api_key'] ?? '');
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

        if ($this->apiKey === '') {
            $this->lastError = 'Brevo API key manquante.';
            return false;
        }
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->lastError = 'Adresse destinataire invalide.';
            return false;
        }
        if (!filter_var($this->fromEmail, FILTER_VALIDATE_EMAIL)) {
            $this->lastError = "Adresse d'expediteur invalide pour Brevo.";
            return false;
        }

        $payload = [
            'sender' => [
                'name' => $this->fromName,
                'email' => $this->fromEmail
            ],
            'to' => [
                ['email' => $to]
            ],
            'subject' => $subject,
            'textContent' => $body
        ];

        $result = function_exists('curl_init')
            ? $this->sendWithCurl($payload)
            : $this->sendWithStream($payload);

        if (!$result['ok']) {
            $this->lastError = $result['error'];
            return false;
        }

        return true;
    }

    private function sendWithCurl(array $payload): array
    {
        $ch = curl_init('https://api.brevo.com/v3/smtp/email');
        if ($ch === false) {
            return ['ok' => false, 'error' => 'Impossible d initialiser CURL.'];
        }

        $headers = [
            'accept: application/json',
            'api-key: ' . $this->apiKey,
            'content-type: application/json'
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => $this->timeout
        ]);

        $responseBody = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($responseBody === false) {
            return ['ok' => false, 'error' => 'Erreur reseau CURL: ' . $curlError];
        }

        if (!in_array($httpCode, [200, 201, 202], true)) {
            return ['ok' => false, 'error' => 'Brevo HTTP ' . $httpCode . ': ' . (string) $responseBody];
        }

        return ['ok' => true, 'error' => ''];
    }

    private function sendWithStream(array $payload): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", [
                    'Accept: application/json',
                    'api-key: ' . $this->apiKey,
                    'Content-Type: application/json'
                ]),
                'content' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'timeout' => $this->timeout,
                'ignore_errors' => true
            ]
        ]);

        $response = @file_get_contents('https://api.brevo.com/v3/smtp/email', false, $context);
        $headers = $http_response_header ?? [];
        $statusLine = $headers[0] ?? '';
        $code = 0;
        if (preg_match('/\s(\d{3})\s/', $statusLine, $m)) {
            $code = (int) $m[1];
        }

        if ($response === false) {
            return ['ok' => false, 'error' => 'Erreur reseau HTTP (stream).'];
        }

        if (!in_array($code, [200, 201, 202], true)) {
            return ['ok' => false, 'error' => 'Brevo HTTP ' . $code . ': ' . (string) $response];
        }

        return ['ok' => true, 'error' => ''];
    }
}
?>
