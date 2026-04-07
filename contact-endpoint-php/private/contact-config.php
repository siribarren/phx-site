<?php

declare(strict_types=1);

return [
    'TURNSTILE_SECRET_KEY' => '0x4AAAAAAC1kSy-oGuJhYGC3C1JsEuT2u3E',
    'SMTP_HOST' => 'mail.phigital.cl',
    'SMTP_PORT' => '587',
    'SMTP_USERNAME' => 'contacto@phigital.cl',
    'SMTP_PASSWORD' => 'Secret0.!',
    'SMTP_FROM_EMAIL' => 'contacto@phigital.cl',
    'SMTP_FROM_NAME' => 'PHX Sitio Web',
    'CONTACT_TO_EMAIL' => 'contacto@phigital.cl',
    'CONTACT_RATE_LIMIT_MAX' => '5',
    'CONTACT_RATE_LIMIT_WINDOW' => '600',
    'CONTACT_LOG_FILE' => __DIR__ . '/contact-endpoint.log',
];
