import nodemailer from 'nodemailer';

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
        text: message.text || undefined,
        html: message.html || undefined
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
