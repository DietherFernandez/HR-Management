/**
 * HR Management — WebSocket Server (libre: Node.js + ws)
 *
 * Ginagamit para sa LIVE DATA:
 *   - bagong attendance (clock-in/out, manual mark, biometric import)
 *   - bagong leave request + approval/rejection
 *   - bagong employee na idinagdag
 *   - bagong Google Sheet row (injected mula sa PHP)
 *
 * Run:  npm install && node server.js
 * Port: 8081 (HTTP notify + WebSocket upgrade sa parehong port)
 *
 * Sa PHP side, tinatawag ang POST /notify ng server na ito kapag may
 * nangyaring event (tingnan ang backend/app/Notifier.php).
 *
 * Libreng deployment options para dito (walang bayad):
 *   - Render.com free web service
 *   - Railway free tier
 *   - Glitch.com
 *   - O kaya mismo sa XAMPP server machine (node + pm2)
 */

const http = require('http');
const { WebSocketServer } = require('ws');

const PORT = process.env.PORT || 8081;
const TOKEN = process.env.WS_TOKEN || ''; // optional shared secret

// ---- Connected clients ----
// Map<ws, { employeeId, role, id }>
const clients = new Map();
let nextId = 1;

const wss = new WebSocketServer({ noServer: true });

const server = http.createServer((req, res) => {
  console.log(`[http] ${req.method} ${req.url}`);
  // POST /notify  { event, data, employee_id, role }
  if (req.method === 'POST' && req.url.split('?')[0] === '/notify') {
    let body = '';
    req.on('data', (c) => (body += c));
    req.on('end', () => {
      let payload;
      try {
        payload = JSON.parse(body || '{}');
      } catch {
        res.writeHead(400, { 'Content-Type': 'application/json' });
        return res.end(JSON.stringify({ error: 'invalid json' }));
      }
      const sent = broadcast(payload);
      res.writeHead(200, { 'Content-Type': 'application/json' });
      res.end(JSON.stringify({ ok: true, sent }));
    });
    return;
  }

  if (req.url.split('?')[0] === '/health') {
    res.writeHead(200, { 'Content-Type': 'application/json' });
    return res.end(JSON.stringify({ status: 'ok', clients: clients.size }));
  }

  res.writeHead(404, { 'Content-Type': 'application/json' });
  res.end(JSON.stringify({ error: 'not found' }));
});

server.on('upgrade', (req, socket, head) => {
  const url = new URL(req.url, 'http://localhost');
  const token = url.searchParams.get('token') || '';
  const employeeId = parseInt(url.searchParams.get('employee_id') || '0', 10) || 0;
  const role = url.searchParams.get('role') || 'employee';

  // Optional shared-secret check
  if (TOKEN && token !== TOKEN) {
    socket.write('HTTP/1.1 401 Unauthorized\r\n\r\n');
    return socket.destroy();
  }

  wss.handleUpgrade(req, socket, head, (ws) => {
    const meta = { employeeId, role, id: nextId++ };
    clients.set(ws, meta);
    console.log(`[ws] connect #${meta.id} emp=${employeeId} role=${role} (total: ${clients.size})`);

    ws.send(JSON.stringify({ event: 'connected', data: { client_id: meta.id } }));

    ws.on('pong', () => (ws.isAlive = true));

    ws.on('message', (raw) => {
      // Keepalive / echo protocol — libreng flexibility para sa frontend
      try {
        const msg = JSON.parse(raw);
        if (msg.type === 'ping') ws.send(JSON.stringify({ type: 'pong', ts: Date.now() }));
        if (msg.type === 'subscribe') {
          meta.channel = String(msg.channel || '');
          ws.send(JSON.stringify({ type: 'subscribed', channel: meta.channel }));
        }
      } catch {
        /* ignore malformed */
      }
    });

    ws.on('close', () => {
      clients.delete(ws);
      console.log(`[ws] close #${meta.id} (total: ${clients.size})`);
    });

    ws.on('error', () => clients.delete(ws));
  });
});

/**
 * Broadcast ng event sa mga tamang client.
 * - employee_id set  → para lang sa employee na iyon
 * - role set         → para sa lahat ng may ganitong role (e.g. "hr")
 * - walang pareho    → broadcast sa LAHAT
 */
function broadcast({ event, data, employee_id, role }) {
  const msg = JSON.stringify({ event, data: data || {}, ts: Date.now() });
  let sent = 0;
  for (const [ws, meta] of clients) {
    if (ws.readyState !== 1) continue; // OPEN
    let ok = true;
    if (employee_id) ok = meta.employeeId === Number(employee_id);
    else if (role) ok = meta.role === role;
    if (ok) {
      ws.send(msg);
      sent++;
    }
  }
  console.log(`[ws] broadcast "${event}" → ${sent} client(s)`);
  return sent;
}

// Heartbeat: tanggalin ang mga patay na connection
setInterval(() => {
  for (const ws of clients) {
    if (ws.isAlive === false) {
      clients.delete(ws);
      ws.terminate();
      continue;
    }
    ws.isAlive = false;
    ws.ping();
  }
}, 30000);

server.listen(PORT, () => {
  console.log(`[ws] listening on http://localhost:${PORT}  (WS upgrade + POST /notify)`);
  console.log(`[ws] libreng tip: i-deploy sa Render/Railway/Glitch para sa production`);
});
