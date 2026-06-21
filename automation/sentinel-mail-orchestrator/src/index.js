import http from 'node:http';
import { loadConfig } from './config.js';
import { createMailer } from './mailer.js';

const MAX_BODY_BYTES = 128 * 1024;

function sendJson(response, statusCode, payload) {
  const body = JSON.stringify(payload);
  response.writeHead(statusCode, {
    'Content-Type': 'application/json; charset=utf-8',
    'Cache-Control': 'no-store'
  });
  response.end(body);
}

function isAuthorized(request, token) {
  const header = request.headers.authorization || '';
  return header === `Bearer ${token}`;
}

function readBody(request) {
  return new Promise((resolve, reject) => {
    let body = '';
    request.setEncoding('utf8');
    request.on('data', (chunk) => {
      body += chunk;
      if (Buffer.byteLength(body, 'utf8') > MAX_BODY_BYTES) {
        reject(new Error('Request body is too large.'));
        request.destroy();
      }
    });
    request.on('end', () => {
      try {
        resolve(body ? JSON.parse(body) : {});
      } catch {
        reject(new Error('Request body must be valid JSON.'));
      }
    });
    request.on('error', reject);
  });
}

function publicMessage(error) {
  if (error instanceof Error) return error.message;
  return 'Unexpected error.';
}

const config = loadConfig();
const mailer = createMailer(config);

const server = http.createServer(async (request, response) => {
  try {
    const url = new URL(request.url || '/', `http://${request.headers.host || 'localhost'}`);

    if (request.method === 'GET' && url.pathname === '/health') {
      sendJson(response, 200, {
        ok: true,
        service: 'sentinel-mail-orchestrator',
        dryRun: config.dryRun
      });
      return;
    }

    if (request.method === 'POST' && url.pathname === '/send-email') {
      if (!isAuthorized(request, config.token)) {
        sendJson(response, 401, { ok: false, error: 'Unauthorized.' });
        return;
      }

      const payload = await readBody(request);
      const result = await mailer.send(payload);
      sendJson(response, 200, { ok: true, ...result });
      return;
    }

    sendJson(response, 404, { ok: false, error: 'Not found.' });
  } catch (error) {
    sendJson(response, 400, { ok: false, error: publicMessage(error) });
  }
});

server.listen(config.port, () => {
  console.log(`Sentinel mail orchestrator listening on port ${config.port}.`);
  console.log(`Dry run mode: ${config.dryRun ? 'enabled' : 'disabled'}.`);
});
