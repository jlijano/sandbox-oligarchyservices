import nodemailer from 'nodemailer';

const BRAND_LOGO_CID = 'oligarchy-services-logo@sentinel';
const BRAND_LOGO_BASE64 = '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';

function normalizeRecipients(value, fallback) {
  if (!value) return fallback ? [fallback] : [];
  if (Array.isArray(value)) return value.map(String).map((item) => item.trim()).filter(Boolean);
  return String(value)
    .split(',')
    .map((item) => item.trim())
    .filter(Boolean);
}

function summarizeRecipients(message, defaultTo) {
  return {
    to: normalizeRecipients(message.to, defaultTo),
    cc: normalizeRecipients(message.cc),
    bcc: normalizeRecipients(message.bcc)
  };
}

function escapeHtml(value) {
  return String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#39;');
}

function decodeCommonEntities(value) {
  return String(value)
    .replaceAll('&nbsp;', ' ')
    .replaceAll('&amp;', '&')
    .replaceAll('&lt;', '<')
    .replaceAll('&gt;', '>')
    .replaceAll('&quot;', '"')
    .replaceAll('&#39;', "'");
}

function htmlToText(html) {
  return decodeCommonEntities(html)
    .replace(/<style[\s\S]*?<\/style>/gi, '')
    .replace(/<script[\s\S]*?<\/script>/gi, '')
    .replace(/<\/(p|div|h[1-6]|li|tr)>/gi, '\n')
    .replace(/<br\s*\/?>/gi, '\n')
    .replace(/<[^>]+>/g, '')
    .replace(/\n{3,}/g, '\n\n')
    .trim();
}

function textBlockToHtml(block) {
  const lines = block.split('\n').map((line) => line.trim()).filter(Boolean);
  const isList = lines.length > 1 && lines.every((line) => /^[-*]\s+/.test(line));

  if (isList) {
    const items = lines
      .map((line) => line.replace(/^[-*]\s+/, ''))
      .map((line) => `<li style="margin:0 0 8px 0;">${escapeHtml(line)}</li>`)
      .join('');
    return `<ul style="Margin:0 0 18px 22px; padding:0; color:#263238; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:22px;">${items}</ul>`;
  }

  return `<p style="Margin:0 0 18px 0; color:#263238; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:23px;">${lines.map(escapeHtml).join('<br>')}</p>`;
}

function textToHtml(text) {
  const normalized = String(text || '').replace(/\r\n/g, '\n').trim();
  if (!normalized) return '';
  return normalized.split(/\n{2,}/).map(textBlockToHtml).join('');
}

function extractBodyHtml(html) {
  const bodyMatch = String(html || '').match(/<body[^>]*>([\s\S]*?)<\/body>/i);
  return bodyMatch ? bodyMatch[1].trim() : String(html || '').trim();
}

function buildTextBody(message) {
  if (message.text) return String(message.text);
  if (message.html) return htmlToText(message.html);
  return '';
}

function buildHtmlBody(message) {
  const content = message.html ? extractBodyHtml(message.html) : textToHtml(message.text);
  const safeSubject = escapeHtml(message.subject);
  const preview = escapeHtml(buildTextBody(message).slice(0, 140));
  const sentDate = new Intl.DateTimeFormat('en-US', {
    dateStyle: 'medium',
    timeStyle: 'short',
    timeZone: 'UTC'
  }).format(new Date());

  return `<!doctype html>
<html>
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>${safeSubject}</title>
  </head>
  <body style="Margin:0; padding:0; background-color:#f4f7fb;">
    <div style="display:none; max-height:0; overflow:hidden; opacity:0; color:transparent;">${preview}</div>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f4f7fb; Margin:0; padding:28px 0;">
      <tr>
        <td align="center" style="padding:0 14px;">
          <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:680px; background-color:#ffffff; border:1px solid #dfe7f0; border-radius:8px; overflow:hidden;">
            <tr>
              <td style="background-color:#101820; padding:22px 28px;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                  <tr>
                    <td align="left" style="vertical-align:middle;">
                      <img src="cid:${BRAND_LOGO_CID}" width="257" height="57" alt="Oligarchy Services" style="display:block; width:257px; max-width:100%; height:auto; border:0; outline:none; text-decoration:none;">
                    </td>
                    <td align="right" style="vertical-align:middle; color:#b8c7d6; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:18px;">
                      Sentinel Automation<br>${sentDate} UTC
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
            <tr>
              <td style="padding:30px 30px 8px 30px;">
                <div style="color:#d2222a; font-family:Arial, Helvetica, sans-serif; font-size:12px; font-weight:bold; letter-spacing:0; line-height:18px; text-transform:uppercase;">Repository Operations</div>
                <h1 style="Margin:6px 0 18px 0; color:#101820; font-family:Arial, Helvetica, sans-serif; font-size:24px; line-height:31px; font-weight:700;">${safeSubject}</h1>
              </td>
            </tr>
            <tr>
              <td style="padding:0 30px 28px 30px;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border:1px solid #e6edf4; border-radius:8px; background-color:#fbfdff;">
                  <tr>
                    <td style="padding:24px 24px 10px 24px; color:#263238; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:23px;">
                      ${content}
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
            <tr>
              <td style="background-color:#f7f9fc; border-top:1px solid #e6edf4; padding:18px 30px; color:#5d6b7a; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:18px;">
                Sent by Sentinel for Oligarchy Services. Reply to this email or use the included approval links when action is required.
              </td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
  </body>
</html>`;
}

function buildAttachments(message) {
  const existing = Array.isArray(message.attachments) ? message.attachments : [];
  return [
    ...existing,
    {
      filename: 'oligarchy-services-logo.png',
      content: Buffer.from(BRAND_LOGO_BASE64, 'base64'),
      cid: BRAND_LOGO_CID,
      contentType: 'image/png',
      disposition: 'inline'
    }
  ];
}

export function createMailer(config) {
  const transporter = nodemailer.createTransport(config.smtp);

  return {
    async send(message) {
      const recipients = summarizeRecipients(message, config.defaultTo);
      if (recipients.to.length === 0) throw new Error('At least one recipient is required.');
      if (!message.subject || typeof message.subject !== 'string') throw new Error('Subject is required.');
      if (!message.text && !message.html) throw new Error('Either text or html body is required.');

      const mail = {
        from: config.from,
        to: recipients.to.join(', '),
        cc: recipients.cc.length ? recipients.cc.join(', ') : undefined,
        bcc: recipients.bcc.length ? recipients.bcc.join(', ') : undefined,
        replyTo: message.replyTo || config.from,
        subject: message.subject,
        text: buildTextBody(message),
        html: buildHtmlBody(message),
        attachments: buildAttachments(message)
      };

      if (config.dryRun) {
        return {
          dryRun: true,
          accepted: recipients.to,
          rejected: [],
          messageId: null
        };
      }

      const result = await transporter.sendMail(mail);
      return {
        dryRun: false,
        accepted: result.accepted || [],
        rejected: result.rejected || [],
        messageId: result.messageId || null
      };
    }
  };
}
