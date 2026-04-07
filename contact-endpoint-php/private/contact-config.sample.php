<?php

declare(strict_types=1);

return [
    'TURNSTILE_SECRET_KEY' => 'your_turnstile_secret',
    'SMTP_HOST' => 'mail.example.com',
    'SMTP_PORT' => '587',
    'SMTP_USERNAME' => 'smtp-user@example.com',
    'SMTP_PASSWORD' => 'replace-with-real-password',
    'SMTP_FROM_EMAIL' => 'no-reply@example.com',
    'SMTP_FROM_NAME' => 'PHX Contact',
    'CONTACT_TO_EMAIL' => 'contacto@phigital.cl',
    'CONTACT_RATE_LIMIT_MAX' => '5',
    'CONTACT_RATE_LIMIT_WINDOW' => '600',
    'CONTACT_LOG_FILE' => __DIR__ . '/contact-endpoint.log',
];
