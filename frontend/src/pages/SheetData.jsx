import { useEffect, useState, useCallback } from 'react';
import { api } from '../api.js';
import { useWebSocket } from '../ws.js';

/** Live Google Sheet data viewer (HR only) */
export default function SheetData() {
  const [data, setData] = useState(null);
  const [err, setErr] = useState(null);
  const [auto, setAuto] = useState(true);

  const load = useCallback(async () => {
    try {
      const d = await api('/sheet/live');
      setData(d);
      setErr(null);
    } catch (e) { setErr(e.message); }
  }, []);

  useEffect(() => { load(); }, [load]);

  // Auto-refresh every 30s kapag naka-on
  useEffect(() => {
    if (!auto) return;
    const t = setInterval(load, 30000);
    return () => clearInterval(t);
  }, [auto, load]);

  // Live push mula sa WebSocket
  useWebSocket((event) => {
    if (event === 'sheet:updated') load();
  });

  return (
    <div className="page">
      <header className="page-head">
        <div>
          <h1>📈 Live Spreadsheet Data</h1>
          <p className="muted">Live mula sa Google Sheets — auto-refresh bawat 30s</p>
        </div>
        <div className="chip-row">
          <button className={`chip ${auto ? 'chip-active' : ''}`} onClick={() => setAuto(!auto)}>
            {auto ? '🟢 Auto-refresh ON' : '⏸ Auto-refresh OFF'}
          </button>
          <button className="chip" onClick={load}>🔄 Refresh</button>
        </div>
      </header>

      {err && <div className="alert alert-error">{err}</div>}

      {data && !data.enabled && (
        <div className="alert alert-warn">
          {data.message}
          <br /><br />
          <strong>Paano i-setup (libre, walang API key):</strong>
          <ol>
            <li>Buksan ang Google Sheet → <em>File → Share → Publish to web</em></li>
            <li>I-publish ang sheet bilang <em>CSV</em></li>
            <li>Kopyahin ang URL at i-paste sa <code>backend/config/config.php</code> → <code>SHEET_CSV_URL</code></li>
          </ol>
        </div>
      )}

      {data?.enabled && (
        <div className="card">
          <p className="muted small">Fetched: {new Date(data.fetched_at).toLocaleString()} · {data.rows.length} row(s)</p>
          <div className="table-scroll">
            <table className="table">
              <thead>
                <tr>{data.headers.map((h, i) => <th key={i}>{h || `Col ${i + 1}`}</th>)}</tr>
              </thead>
              <tbody>
                {data.rows.map((row, i) => (
                  <tr key={i}>
                    {row.map((cell, j) => <td key={j}>{cell}</td>)}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </div>
  );
}
