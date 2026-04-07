# PHX Contact Endpoint

Endpoint PHP para alojar por separado en cPanel y procesar el formulario del sitio.

## Requisitos

- PHP 8.1+
- Composer
- Extensión `curl` habilitada
- SMTP saliente configurado

## Instalación

1. Ejecuta `composer install --no-dev --optimize-autoloader`.
2. Crea la carpeta `api` dentro de `public_html` para exponer la URL `https://www.phigital.cl/api/contact.php`.
3. Copia `contact.php` a `public_html/api/contact.php`.
4. Deja `vendor/` junto a `contact.php`, por ejemplo en `public_html/api/vendor/`.
5. Configura las variables de entorno en cPanel o carga los secretos desde un archivo fuera del webroot.
6. Si usarás archivo privado, toma `private/contact-config.sample.php` como base y crea `contact-config.php` fuera de `public_html`.

## Estructura sugerida en cPanel

```text
public_html/
  api/
    contact.php
    vendor/

private/
  contact-config.php
```

Con esa estructura, el endpoint de producción queda en:

```text
https://www.phigital.cl/api/contact.php
```

El endpoint buscará config privada en este orden:

1. Ruta definida en `CONTACT_CONFIG_FILE`
2. `../private/contact-config.php` respecto de `contact.php`
3. `private/contact-config.php` en el directorio padre del endpoint

## Variables requeridas

- `TURNSTILE_SECRET_KEY`
- `SMTP_HOST`
- `SMTP_PORT`
- `SMTP_USERNAME`
- `SMTP_PASSWORD`
- `SMTP_FROM_EMAIL`
- `SMTP_FROM_NAME`
- `CONTACT_TO_EMAIL`

## Variables opcionales

- `CONTACT_RATE_LIMIT_MAX`
- `CONTACT_RATE_LIMIT_WINDOW`
- `CONTACT_LOG_FILE`

## Config privada fuera de public_html

Puedes guardar secretos en un archivo PHP retornando un array. Usa `private/contact-config.sample.php` como plantilla.

Ejemplo de despliegue:

```text
/home/usuario/public_html/api/contact.php
/home/usuario/public_html/api/vendor/
/home/usuario/private/contact-config.php
```

En ese caso, `contact.php` cargará el archivo privado automáticamente sin exponer secretos al navegador.

## Frontend Astro

La variable pública del sitio debe apuntar al endpoint productivo:

```env
PUBLIC_TURNSTILE_SITE_KEY=tu_site_key_publica
PUBLIC_CONTACT_ENDPOINT_URL=https://www.phigital.cl/api/contact.php
```

## Request esperado

```json
{
  "name": "Jane Doe",
  "company": "Acme",
  "email": "jane@example.com",
  "phone": "+56 9 1234 5678",
  "message": "Necesitamos automatizar parte de la operación.",
  "source": "contacto",
  "website": "",
  "cf-turnstile-response": "token"
}
```

El backend también acepta `turnstileToken` como alias de compatibilidad.

## Respuestas

- `200 { "ok": true }`
- `400 { "ok": false, "error": "validation" }`
- `403 { "ok": false, "error": "captcha" }`
- `429 { "ok": false, "error": "rate_limit" }`
- `500 { "ok": false, "error": "server" }`

## Checklist de despliegue

1. Frontend:
   - Configura `PUBLIC_TURNSTILE_SITE_KEY`.
   - Configura `PUBLIC_CONTACT_ENDPOINT_URL=https://www.phigital.cl/api/contact.php`.
   - Genera y publica el build estático del sitio.
2. Backend PHP:
   - Ejecuta `composer install --no-dev --optimize-autoloader`.
   - Sube `contact.php` y `vendor/` a `public_html/api/`.
   - Configura `TURNSTILE_SECRET_KEY`.
   - Configura `SMTP_HOST`, `SMTP_PORT`, `SMTP_USERNAME`, `SMTP_PASSWORD`, `SMTP_FROM_EMAIL`, `SMTP_FROM_NAME` y `CONTACT_TO_EMAIL`.
   - Opcionalmente configura `CONTACT_RATE_LIMIT_MAX`, `CONTACT_RATE_LIMIT_WINDOW` y `CONTACT_LOG_FILE`.
3. Validación final:
   - Verifica que `https://www.phigital.cl/api/contact.php` responda.
   - Envía una prueba real con Turnstile válido.
   - Confirma recepción del correo.
   - Revisa logs si el endpoint responde `403`, `429` o `500`.
