import type { APIRoute } from 'astro';
import nodemailer from 'nodemailer';

export const prerender = false;

const NAME_REGEX = /^[A-Za-zÁÉÍÓÚÜÑáéíóúüñ'’.\-\s]+$/;
const COMPANY_REGEX = /^[A-Za-zÁÉÍÓÚÜÑáéíóúüñ0-9&().,'’\-\s]+$/;
const EMAIL_REGEX = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const PHONE_REGEX = /^\+569\d{8}$/;

const HUMAN_PROMPT = 'PHX';

type ContactPayload = {
  name: string;
  company: string;
  email: string;
  phone: string;
  message: string;
  humanCheck: string;
  website: string;
};

const getTrimmedValue = (value: FormDataEntryValue | null) =>
  typeof value === 'string' ? value.trim() : '';

const escapeHtml = (value: string) =>
  value
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#39;');

const validatePayload = (payload: ContactPayload) => {
  const errors: Record<string, string> = {};

  if (!payload.name) {
    errors.name = 'Ingresa tu nombre.';
  } else if (!NAME_REGEX.test(payload.name)) {
    errors.name = 'El nombre solo puede contener texto y espacios.';
  }

  if (!payload.company) {
    errors.company = 'Ingresa el nombre de tu empresa.';
  } else if (!COMPANY_REGEX.test(payload.company)) {
    errors.company = 'La empresa solo puede contener texto y numeros.';
  }

  if (!payload.email) {
    errors.email = 'Ingresa tu correo corporativo o personal.';
  } else if (!EMAIL_REGEX.test(payload.email)) {
    errors.email = 'Ingresa un correo valido.';
  }

  if (!payload.phone) {
    errors.phone = 'Ingresa tu telefono en formato +569XXXXXXXX.';
  } else if (!PHONE_REGEX.test(payload.phone)) {
    errors.phone = 'El telefono debe comenzar con +569 y tener 8 digitos adicionales.';
  }

  if (!payload.message) {
    errors.message = 'Cuentanos brevemente que necesitas resolver.';
  }

  if (!payload.humanCheck) {
    errors.humanCheck = 'Completa la validacion humana escribiendo PHX.';
  } else if (payload.humanCheck.toUpperCase() !== HUMAN_PROMPT) {
    errors.humanCheck = 'La validacion humana no coincide. Escribe PHX.';
  }

  if (payload.website) {
    errors.form = 'No fue posible validar el envio del formulario.';
  }

  return errors;
};

const renderEmailHtml = (payload: ContactPayload) => {
  const rows = [
    ['Nombre', payload.name],
    ['Empresa', payload.company],
    ['Correo', payload.email],
    ['Telefono', payload.phone],
  ];

  return `<!doctype html>
<html lang="es">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Contacto desde el sitio web de PHX</title>
  </head>
  <body style="margin:0;padding:0;background-color:#f7faff;font-family:Arial,Helvetica,sans-serif;color:#111827;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f7faff;padding:24px 12px;">
      <tr>
        <td align="center">
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:680px;background-color:#ffffff;border-radius:24px;overflow:hidden;border:1px solid #dce7f9;">
            <tr>
              <td style="padding:32px;background:linear-gradient(135deg,#0b5fff 0%,#1e3a8a 100%);color:#ffffff;">
                <p style="margin:0 0 8px;font-size:13px;letter-spacing:0.08em;text-transform:uppercase;opacity:0.8;">PHX</p>
                <h1 style="margin:0;font-size:28px;line-height:1.2;">Contacto desde el sitio web de PHX</h1>
                <p style="margin:12px 0 0;font-size:15px;line-height:1.6;opacity:0.92;">
                  Se recibio una nueva solicitud de contacto y estos son los datos enviados por el prospecto.
                </p>
              </td>
            </tr>
            <tr>
              <td style="padding:28px 32px 16px;">
                <h2 style="margin:0 0 18px;font-size:20px;color:#1e3a8a;">Datos de contacto</h2>
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                  ${rows
                    .map(
                      ([label, value]) => `
                  <tr>
                    <td style="width:160px;padding:14px 0;border-bottom:1px solid #dce7f9;font-size:14px;font-weight:700;color:#1e3a8a;">${escapeHtml(label)}</td>
                    <td style="padding:14px 0;border-bottom:1px solid #dce7f9;font-size:14px;color:#111827;">${escapeHtml(value)}</td>
                  </tr>`,
                    )
                    .join('')}
                </table>
              </td>
            </tr>
            <tr>
              <td style="padding:8px 32px 32px;">
                <div style="background-color:#f7faff;border:1px solid #dce7f9;border-radius:20px;padding:20px;">
                  <p style="margin:0 0 10px;font-size:13px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#0b5fff;">Desafio o mensaje</p>
                  <p style="margin:0;font-size:15px;line-height:1.7;color:#111827;white-space:pre-line;">${escapeHtml(payload.message)}</p>
                </div>
              </td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
  </body>
</html>`;
};

export const POST: APIRoute = async ({ request }) => {
  try {
    const formData = await request.formData();
    const payload: ContactPayload = {
      name: getTrimmedValue(formData.get('name')),
      company: getTrimmedValue(formData.get('company')),
      email: getTrimmedValue(formData.get('email')),
      phone: getTrimmedValue(formData.get('phone')),
      message: getTrimmedValue(formData.get('message')),
      humanCheck: getTrimmedValue(formData.get('humanCheck')),
      website: getTrimmedValue(formData.get('website')),
    };

    const errors = validatePayload(payload);

    if (Object.keys(errors).length > 0) {
      return new Response(
        JSON.stringify({
          ok: false,
          errors,
          message: 'Revisa los campos marcados y completa la informacion solicitada.',
        }),
        {
          status: 400,
          headers: { 'Content-Type': 'application/json' },
        },
      );
    }

    const transporter = nodemailer.createTransport({
      host: import.meta.env.SMTP_HOST,
      port: Number(import.meta.env.SMTP_PORT || 465),
      secure: true,
      auth: {
        user: import.meta.env.SMTP_USER,
        pass: import.meta.env.SMTP_PASS,
      },
    });

    await transporter.sendMail({
      from: `"${import.meta.env.CONTACT_FROM_NAME || 'PHX Contacto'}" <${import.meta.env.CONTACT_FROM_EMAIL}>`,
      to: import.meta.env.CONTACT_TO_EMAILS,
      replyTo: payload.email,
      subject: 'Contacto desde el sitio web de PHX',
      html: renderEmailHtml(payload),
      text: [
        'Contacto desde el sitio web de PHX',
        `Nombre: ${payload.name}`,
        `Empresa: ${payload.company}`,
        `Correo: ${payload.email}`,
        `Telefono: ${payload.phone}`,
        '',
        'Desafio o mensaje:',
        payload.message,
      ].join('\n'),
    });

    return new Response(
      JSON.stringify({
        ok: true,
        message:
          'Tu mensaje fue enviado correctamente. Un ejecutivo humano de PHX te contactara a la brevedad.',
      }),
      {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      },
    );
  } catch (error) {
    console.error('Error sending contact form', error);

    return new Response(
      JSON.stringify({
        ok: false,
        errors: {
          form: 'No pudimos enviar tu solicitud en este momento. Intenta nuevamente en unos minutos.',
        },
      }),
      {
        status: 500,
        headers: { 'Content-Type': 'application/json' },
      },
    );
  }
};
