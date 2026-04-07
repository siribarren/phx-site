<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\Exception as MailerException;
use PHPMailer\PHPMailer\PHPMailer;

require __DIR__ . '/vendor/autoload.php';

load_private_config();

header('Content-Type: application/json; charset=utf-8');

const MAX_NAME_LENGTH = 120;
const MAX_COMPANY_LENGTH = 120;
const MAX_EMAIL_LENGTH = 160;
const MAX_PHONE_LENGTH = 32;
const MAX_MESSAGE_LENGTH = 2000;
const MIN_MESSAGE_LENGTH = 10;
const MIN_NAME_LENGTH = 2;

function load_private_config(): void
{
    $candidates = [
        getenv('CONTACT_CONFIG_FILE') ?: null,
        __DIR__ . '/../private/contact-config.php',
        dirname(__DIR__) . '/private/contact-config.php',
    ];

    foreach ($candidates as $candidate) {
        if (!is_string($candidate) || $candidate === '') {
            continue;
        }

        if (!is_file($candidate)) {
            continue;
        }

        $config = require $candidate;
        if (!is_array($config)) {
            throw new RuntimeException(sprintf('Invalid contact config file: %s', $candidate));
        }

        foreach ($config as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }

            if (!is_scalar($value) && $value !== null) {
                continue;
            }

            if (getenv($key) !== false) {
                continue;
            }

            $normalized = $value === null ? '' : (string)$value;
            putenv(sprintf('%s=%s', $key, $normalized));
            $_ENV[$key] = $normalized;
            $_SERVER[$key] = $normalized;
        }

        return;
    }
}

function respond(int $statusCode, array $payload): never
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function log_event(string $level, string $message, array $context = []): void
{
    $entry = [
        'timestamp' => gmdate('c'),
        'level' => $level,
        'message' => $message,
        'context' => $context,
    ];

    $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $logFile = getenv('CONTACT_LOG_FILE') ?: null;

    if (is_string($logFile) && $logFile !== '') {
        error_log($line . PHP_EOL, 3, $logFile);
        return;
    }

    error_log($line);
}

function get_env_or_fail(string $name): string
{
    $value = getenv($name);

    if (!is_string($value) || trim($value) === '') {
        throw new RuntimeException(sprintf('Missing required environment variable: %s', $name));
    }

    return trim($value);
}

function get_client_ip(): string
{
    $candidates = [
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null,
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
        $_SERVER['REMOTE_ADDR'] ?? null,
    ];

    foreach ($candidates as $candidate) {
        if (!is_string($candidate) || $candidate === '') {
            continue;
        }

        $parts = explode(',', $candidate);
        $ip = trim($parts[0]);

        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }

    return 'unknown';
}

function sanitize_text(string $value): string
{
    $value = trim($value);
    $value = strip_tags($value);
    $value = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $value) ?? $value;
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

    return trim($value);
}

function normalize_phone(string $value): string
{
    $value = sanitize_text($value);
    return preg_replace('/[^0-9+\-\s()]/', '', $value) ?? $value;
}

function load_payload(): array
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        respond(400, ['ok' => false, 'error' => 'validation']);
    }

    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') === false) {
        respond(400, ['ok' => false, 'error' => 'validation']);
    }

    $rawBody = file_get_contents('php://input');
    if (!is_string($rawBody) || $rawBody === '') {
        respond(400, ['ok' => false, 'error' => 'validation']);
    }

    $payload = json_decode($rawBody, true);
    if (!is_array($payload)) {
        respond(400, ['ok' => false, 'error' => 'validation']);
    }

    return $payload;
}

function validate_payload(array $payload): array
{
    $name = sanitize_text((string)($payload['name'] ?? ''));
    $company = sanitize_text((string)($payload['company'] ?? ''));
    $email = strtolower(trim((string)($payload['email'] ?? '')));
    $phone = normalize_phone((string)($payload['phone'] ?? ''));
    $message = sanitize_text((string)($payload['message'] ?? ''));
    $source = sanitize_text((string)($payload['source'] ?? ''));
    $website = sanitize_text((string)($payload['website'] ?? ''));
    $token = trim((string)($payload['cf-turnstile-response'] ?? $payload['turnstileToken'] ?? ''));

    if ($website !== '') {
        respond(400, ['ok' => false, 'error' => 'validation']);
    }

    if (strlen($name) < MIN_NAME_LENGTH || strlen($name) > MAX_NAME_LENGTH) {
        respond(400, ['ok' => false, 'error' => 'validation']);
    }

    if ($company !== '' && strlen($company) > MAX_COMPANY_LENGTH) {
        respond(400, ['ok' => false, 'error' => 'validation']);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > MAX_EMAIL_LENGTH) {
        respond(400, ['ok' => false, 'error' => 'validation']);
    }

    if ($phone !== '' && strlen($phone) > MAX_PHONE_LENGTH) {
        respond(400, ['ok' => false, 'error' => 'validation']);
    }

    if (strlen($message) < MIN_MESSAGE_LENGTH || strlen($message) > MAX_MESSAGE_LENGTH) {
        respond(400, ['ok' => false, 'error' => 'validation']);
    }

    if (!in_array($source, ['footer', 'contacto'], true)) {
        respond(400, ['ok' => false, 'error' => 'validation']);
    }

    if ($token === '') {
        respond(403, ['ok' => false, 'error' => 'captcha']);
    }

    return [
        'name' => $name,
        'company' => $company,
        'email' => $email,
        'phone' => $phone,
        'message' => $message,
        'source' => $source,
        'token' => $token,
    ];
}

function enforce_rate_limit(string $ipAddress): void
{
    $maxAttempts = max(1, (int)(getenv('CONTACT_RATE_LIMIT_MAX') ?: 5));
    $windowSeconds = max(60, (int)(getenv('CONTACT_RATE_LIMIT_WINDOW') ?: 600));
    $directory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'phx_contact_rate_limit';

    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create rate limit directory.');
    }

    $bucketFile = $directory . DIRECTORY_SEPARATOR . hash('sha256', $ipAddress) . '.json';
    $now = time();
    $entries = [];

    if (is_file($bucketFile)) {
        $raw = file_get_contents($bucketFile);
        $decoded = json_decode($raw ?: '[]', true);

        if (is_array($decoded)) {
            $entries = array_values(array_filter($decoded, static fn ($timestamp): bool => is_int($timestamp) && ($now - $timestamp) < $windowSeconds));
        }
    }

    if (count($entries) >= $maxAttempts) {
        respond(429, ['ok' => false, 'error' => 'rate_limit']);
    }

    $entries[] = $now;
    file_put_contents($bucketFile, json_encode($entries), LOCK_EX);
}

function validate_turnstile(string $token, string $ipAddress): bool
{
    $secretKey = get_env_or_fail('TURNSTILE_SECRET_KEY');
    $postFields = [
        'secret' => $secretKey,
        'response' => $token,
    ];

    if ($ipAddress !== 'unknown') {
        $postFields['remoteip'] = $ipAddress;
    }

    $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($postFields),
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);

    $rawResponse = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if (!is_string($rawResponse) || $httpCode !== 200) {
        log_event('error', 'Turnstile verification request failed.', [
            'ip' => $ipAddress,
            'http_code' => $httpCode,
            'curl_error' => $curlError,
        ]);
        return false;
    }

    $decoded = json_decode($rawResponse, true);
    if (!is_array($decoded) || !($decoded['success'] ?? false)) {
        log_event('warning', 'Turnstile rejected the token.', [
            'ip' => $ipAddress,
            'errors' => $decoded['error-codes'] ?? [],
        ]);
        return false;
    }

    return true;
}

function send_contact_email(array $payload): void
{
    $mailer = new PHPMailer(true);
    $mailer->isSMTP();
    $mailer->Host = get_env_or_fail('SMTP_HOST');
    $mailer->Port = (int)get_env_or_fail('SMTP_PORT');
    $mailer->SMTPAuth = true;
    $mailer->Username = get_env_or_fail('SMTP_USERNAME');
    $mailer->Password = get_env_or_fail('SMTP_PASSWORD');
    $mailer->CharSet = 'UTF-8';
    $mailer->Timeout = 10;
    $mailer->SMTPSecure = $mailer->Port === 465
        ? PHPMailer::ENCRYPTION_SMTPS
        : PHPMailer::ENCRYPTION_STARTTLS;

    $fromEmail = get_env_or_fail('SMTP_FROM_EMAIL');
    $fromName = get_env_or_fail('SMTP_FROM_NAME');
    $toEmail = get_env_or_fail('CONTACT_TO_EMAIL');

    $mailer->setFrom($fromEmail, $fromName);
    $mailer->addAddress($toEmail);
    $mailer->addReplyTo($payload['email'], $payload['name']);
    $mailer->Subject = sprintf('Nuevo lead web PHX - %s', $payload['source']);

    $lines = [
        'Nuevo lead desde phx-site',
        '',
        'Origen: ' . $payload['source'],
        'Nombre: ' . $payload['name'],
        'Empresa: ' . ($payload['company'] !== '' ? $payload['company'] : '-'),
        'Correo: ' . $payload['email'],
        'Teléfono: ' . ($payload['phone'] !== '' ? $payload['phone'] : '-'),
        '',
        'Mensaje:',
        $payload['message'],
    ];

    $mailer->isHTML(false);
    $mailer->Body = implode(PHP_EOL, $lines);
    $mailer->send();
}

try {
    $ipAddress = get_client_ip();
    $payload = load_payload();
    $validatedPayload = validate_payload($payload);

    enforce_rate_limit($ipAddress);

    if (!validate_turnstile($validatedPayload['token'], $ipAddress)) {
        respond(403, ['ok' => false, 'error' => 'captcha']);
    }

    send_contact_email($validatedPayload);

    log_event('info', 'Contact form processed successfully.', [
        'ip' => $ipAddress,
        'source' => $validatedPayload['source'],
        'email' => $validatedPayload['email'],
    ]);

    respond(200, ['ok' => true]);
} catch (MailerException $exception) {
    log_event('error', 'SMTP delivery failed.', [
        'ip' => get_client_ip(),
        'error' => $exception->getMessage(),
    ]);
    respond(500, ['ok' => false, 'error' => 'server']);
} catch (Throwable $exception) {
    log_event('error', 'Unhandled contact endpoint failure.', [
        'ip' => get_client_ip(),
        'error' => $exception->getMessage(),
    ]);
    respond(500, ['ok' => false, 'error' => 'server']);
}
