const DEFAULT_PORT = 8787;

function readBoolean(name, fallback = false) {
  const value = process.env[name];
  if (value === undefined || value === '') return fallback;
  return ['1', 'true', 'yes', 'on'].includes(value.toLowerCase());
}

function readInteger(name, fallback) {
  const value = process.env[name];
  if (value === undefined || value === '') return fallback;
  const parsed = Number.parseInt(value, 10);
  if (Number.isNaN(parsed)) throw new Error(`${name} must be a number.`);
  return parsed;
}

function required(name) {
  const value = process.env[name];
  if (!value) throw new Error(`${name} is required.`);
  return value;
}

export function loadConfig() {
  return {
    port: readInteger('PORT', DEFAULT_PORT),
    dryRun: readBoolean('DRY_RUN', true),
    token: required('ORCHESTRATOR_TOKEN'),
    defaultTo: process.env.MAIL_DEFAULT_TO || 'jlijano@gmail.com',
    smtp: {
      host: process.env.SMTP_HOST || 'smtp.hostinger.com',
      port: readInteger('SMTP_PORT', 465),
      secure: readBoolean('SMTP_SECURE', true),
      auth: {
        user: required('MAIL_USERNAME'),
        pass: required('MAIL_PASSWORD')
      }
    },
    from: process.env.MAIL_FROM || required('MAIL_USERNAME')
  };
}
