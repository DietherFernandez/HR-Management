import { useEffect, useState, useCallback } from 'react';
import { api } from '../api.js';
import { useAuth } from '../auth.jsx';

/**
 * Attendance page — employee: history + sariling clock-in status.
 * HR: full log + manual mark + biometric import + verify.
 */
export default function Attendance() {
  const { isHR } = useAuth();
  const [rows, setRows] = useState([]);
  const [from, setFrom] = useState(new Date(Date.now() - 6 * 864e5).toISOString().slice(0, 10));
  const [to, setTo] = useState(new Date().toISOString().slice(0, 10));
  const [msg, setMsg] = useState(null);
  const [tab, setTab] = useState('log');

  // HR forms
  const [manual, setManual] = useState({ employee_id: '', date: new Date().toISOString().slice(0, 10), time_in: '08:00', time_out: '', status: 'present', notes: '' });
  const [csvText, setCsvText] = useState('');
  const [photoView, setPhotoView] = useState(null);

  const load = useCallback(async () => {
    try {
      const data = await api(`/attendance?from=${from}&to=${to}`);
      setRows(data.attendance || []);
    } catch (e) { setMsg({ type: 'error', text: e.message }); }
  }, [from, to]);

  useEffect(() => { load(); }, [load]);

  async function submitManual(e) {
    e.preventDefault();
    try {
      const body = {
        employee_id: Number(manual.employee_id),
        date: manual.date,
        time_in: manual.time_in ? `${manual.date} ${manual.time_in}:00` : null,
        time_out: manual.time_out ? `${manual.date} ${manual.time_out}:00` : null,
        status: manual.status,
        notes: manual.notes,
      };
      const res = await api('/attendance/manual-mark', { method: 'POST', body });
      setMsg({ type: 'ok', text: res.message });
      load();
    } catch (ex) { setMsg({ type: 'error', text: ex.message }); }
  }

  async function submitBiometric(e) {
    e.preventDefault();
    try {
      const res = await api('/attendance/biometric-import', { method: 'POST', body: { csv: csvText } });
      setMsg({ type: 'ok', text: res.message + (res.errors?.length ? ` — ${res.errors.length} error(s)` : '') });
      setCsvText('');
      load();
    } catch (ex) { setMsg({ type: 'error', text: ex.message }); }
  }

  async function verifyRow(id) {
    try {
      await api('/attendance/verify', { method: 'POST', body: { attendance_id: id, notes: 'Na-verify sa dashboard' } });
      setMsg({ type: 'ok', text: 'Na-verify ang record.' });
      load();
    } catch (e) { setMsg({ type: 'error', text: e.message }); }
  }

  return (
    <div className="page">
      <header className="page-head">
        <div><h1>Attendance</h1><p className="muted">Selfie + GPS verified · biometric · manual mark</p></div>
        {isHR && (
          <div className="chip-row">
            {['log', 'manual', 'biometric'].map((t) => (
              <button key={t} className={`chip ${tab === t ? 'chip-active' : ''}`} onClick={() => setTab(t)}>
                {t === 'log' ? '📋 Log' : t === 'manual' ? '✍️ Manual Mark' : '📟 Biometric Import'}
              </button>
            ))}
          </div>
        )}
      </header>

      {msg && <div className={`alert alert-${msg.type}`}>{msg.text}</div>}

      <div className="card">
        <div className="filter-row">
          <label>From <input type="date" value={from} onChange={(e) => setFrom(e.target.value)} /></label>
          <label>To <input type="date" value={to} onChange={(e) => setTo(e.target.value)} /></label>
        </div>

        {tab === 'log' && (
          <table className="table">
            <thead>
              <tr><th>Employee</th><th>Date</th><th>In</th><th>Out</th><th>Status</th><th>Method</th><th>Proof</th>{isHR && <th></th>}</tr>
            </thead>
            <tbody>
              {rows.length === 0 && <tr><td colSpan={isHR ? 8 : 7} className="muted">Walang record sa petsang ito.</td></tr>}
              {rows.map((r) => (
                <tr key={r.id}>
                  <td>{r.first_name} {r.last_name}</td>
                  <td>{r.attendance_date}</td>
                  <td>{r.time_in ? new Date(r.time_in).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '—'}</td>
                  <td>{r.time_out ? new Date(r.time_out).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '—'}</td>
                  <td><span className={`badge badge-${r.status}`}>{r.status}</span></td>
                  <td><span className="badge">{r.method}</span></td>
                  <td>
                    {r.photo_url
                      ? <button className="btn btn-sm" onClick={() => setPhotoView(r.photo_url)}>🖼 Selfie</button>
                      : r.location_verified
                        ? <span className="ok-text small">📍✓ GPS</span>
                        : <span className="muted small">—</span>}
                  </td>
                  {isHR && (
                    <td>
                      {!r.verified_by && (
                        <button className="btn btn-sm btn-ok" onClick={() => verifyRow(r.id)}>✓ Verify</button>
                      )}
                      {r.verified_by && <small className="ok-text">verified ✓</small>}
                    </td>
                  )}
                </tr>
              ))}
            </tbody>
          </table>
        )}

        {tab === 'manual' && (
          <form className="form-grid" onSubmit={submitManual}>
            <p className="muted" style={{ gridColumn: '1 / -1' }}>
              Gawin ito kapag walang device o app ang employee — ikaw mismo ang magmamark ng presence.
            </p>
            <div><label>Employee *</label>
              <select required value={manual.employee_id} onChange={(e) => setManual({ ...manual, employee_id: e.target.value })}>
                <option value="">— Piliin —</option>
                {rows.map((r) => <option key={r.employee_id} value={r.employee_id}>{r.first_name} {r.last_name}</option>)}
              </select></div>
            <div><label>Date *</label>
              <input type="date" required value={manual.date} onChange={(e) => setManual({ ...manual, date: e.target.value })} /></div>
            <div><label>Time in</label>
              <input type="time" value={manual.time_in} onChange={(e) => setManual({ ...manual, time_in: e.target.value })} /></div>
            <div><label>Time out</label>
              <input type="time" value={manual.time_out} onChange={(e) => setManual({ ...manual, time_out: e.target.value })} /></div>
            <div><label>Status</label>
              <select value={manual.status} onChange={(e) => setManual({ ...manual, status: e.target.value })}>
                {['present', 'late', 'half_day', 'absent', 'on_leave'].map((s) => <option key={s} value={s}>{s}</option>)}
              </select></div>
            <div style={{ gridColumn: '1 / -1' }}><label>Notes</label>
              <input value={manual.notes} onChange={(e) => setManual({ ...manual, notes: e.target.value })} /></div>
            <button className="btn btn-primary">I-mark ang Attendance</button>
          </form>
        )}

        {tab === 'biometric' && (
          <form className="form-grid" onSubmit={submitBiometric}>
            <p className="muted" style={{ gridColumn: '1 / -1' }}>
              I-paste ang export mula sa fingerprint/face device (ZKTeco, Realand, atbp.).
              Format: <code>code,date,time_in,time_out</code> — kada linya, halimbawa:<br />
              <code>EMP-002,2026-10-06,08:03,17:05</code>
            </p>
            <div style={{ gridColumn: '1 / -1' }}>
              <textarea rows={8} value={csvText} onChange={(e) => setCsvText(e.target.value)}
                placeholder="EMP-002,2026-10-06,08:03,17:05&#10;EMP-003,2026-10-06,08:15,17:02" />
            </div>
            <button className="btn btn-primary" disabled={!csvText.trim()}>I-import ang Records</button>
          </form>
        )}
      </div>

      {photoView && (
        <div className="modal-overlay" onClick={() => setPhotoView(null)}>
          <div className="modal" onClick={(e) => e.stopPropagation()}>
            <img src={photoView} alt="attendance proof" className="modal-img" />
            <button className="btn btn-ghost" onClick={() => setPhotoView(null)}>Isara</button>
          </div>
        </div>
      )}
    </div>
  );
}
