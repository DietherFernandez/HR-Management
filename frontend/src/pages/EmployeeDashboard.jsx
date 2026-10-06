import { useEffect, useState, useCallback } from 'react';
import { api } from '../api.js';
import { useAuth } from '../auth.jsx';
import { useWebSocket } from '../ws.js';

/** Employee dashboard: clock in/out with selfie + GPS, my leaves, stats */
export default function EmployeeDashboard() {
  const { user } = useAuth();
  const [stats, setStats] = useState(null);
  const [leaves, setLeaves] = useState([]);
  const [photo, setPhoto] = useState(null);
  const [geo, setGeo] = useState(null);
  const [msg, setMsg] = useState(null);
  const [busy, setBusy] = useState(false);
  const [showLeaveForm, setShowLeaveForm] = useState(false);
  const [form, setForm] = useState({ leave_type: 'vacation', start_date: '', end_date: '', reason: '' });

  const load = useCallback(async () => {
    try {
      const [s, l] = await Promise.all([api('/dashboard/stats'), api('/leave-requests')]);
      setStats(s);
      setLeaves(l.leave_requests || []);
    } catch (e) { setMsg({ type: 'error', text: e.message }); }
  }, []);

  useEffect(() => { load(); }, [load]);

  useWebSocket((event, data) => {
    if (event === 'leave:status' || event === 'leave:new') load();
    if (event === 'leave:status') {
      setMsg({ type: data.status === 'approved' ? 'ok' : 'warn', text: `Leave ${data.status}: ${data.type} (${data.start} → ${data.end})` });
    }
  });

  // --- Selfie capture ---
  function capturePhoto(e) {
    const file = e.target.files?.[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = () => setPhoto(reader.result);
    reader.readAsDataURL(file);
  }

  // --- GPS ---
  function getGeo() {
    if (!navigator.geolocation) {
      setMsg({ type: 'warn', text: 'Hindi supported ang geolocation sa device mo.' });
      return;
    }
    setMsg({ type: 'info', text: 'Kinukuha ang GPS location…' });
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        setGeo({ latitude: pos.coords.latitude, longitude: pos.coords.longitude, accuracy: pos.coords.accuracy });
        setMsg({ type: 'ok', text: `Nakuha ang GPS (±${Math.round(pos.coords.accuracy)}m).` });
      },
      (err) => setMsg({ type: 'error', text: 'Hindi makuha ang GPS: ' + err.message }),
      { enableHighAccuracy: true, timeout: 15000, maximumAge: 30000 }
    );
  }

  async function clockIn() {
    if (!photo) return setMsg({ type: 'error', text: 'Kailangan muna ng selfie photo bago mag-clock-in.' });
    if (!geo) return setMsg({ type: 'error', text: 'Kailangan muna ng GPS location bago mag-clock-in.' });
    setBusy(true);
    try {
      const res = await api('/attendance/clock-in', {
        method: 'POST',
        body: {
          photo,
          latitude: geo.latitude,
          longitude: geo.longitude,
          device_info: navigator.userAgent.slice(0, 200),
        },
      });
      setMsg({ type: 'ok', text: res.message + (res.location_verified ? ' (na-verify ang lokasyon)' : ' (labas ng geofence — ire-review ng HR)') });
      setPhoto(null); setGeo(null);
      load();
    } catch (e) { setMsg({ type: 'error', text: e.message }); }
    finally { setBusy(false); }
  }

  async function clockOut() {
    setBusy(true);
    try {
      const res = await api('/attendance/clock-out', { method: 'POST' });
      setMsg({ type: 'ok', text: res.message });
      load();
    } catch (e) { setMsg({ type: 'error', text: e.message }); }
    finally { setBusy(false); }
  }

  async function submitLeave(e) {
    e.preventDefault();
    setBusy(true);
    try {
      const res = await api('/leave-requests', { method: 'POST', body: form });
      setMsg({ type: 'ok', text: res.message });
      setShowLeaveForm(false);
      setForm({ leave_type: 'vacation', start_date: '', end_date: '', reason: '' });
      load();
    } catch (ex) { setMsg({ type: 'error', text: ex.message }); }
    finally { setBusy(false); }
  }

  const clockedIn = stats?.is_clocked_in;
  const clockedOut = stats?.is_clocked_out;

  return (
    <div className="page">
      <header className="page-head">
        <div>
          <h1>Kumusta, {user?.first_name}! 👋</h1>
          <p className="muted">{user?.position} · {user?.department_name || '—'}</p>
        </div>
      </header>

      {msg && <div className={`alert alert-${msg.type}`}>{msg.text}</div>}

      <div className="grid-4">
        <div className="card stat"><small>Today</small><strong>{stats?.today ? (stats.today.status === 'late' ? 'LATE ⏰' : 'PRESENT ✅') : 'WALA PA'}</strong></div>
        <div className="card stat"><small>Time In</small><strong>{stats?.today?.time_in ? new Date(stats.today.time_in).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '—'}</strong></div>
        <div className="card stat"><small>Days this week</small><strong>{stats?.days_this_week ?? 0}</strong></div>
        <div className="card stat"><small>Pending leaves</small><strong>{stats?.pending_leaves ?? 0}</strong></div>
      </div>

      <div className="card">
        <h2>⏰ Clock In / Out</h2>
        <p className="muted">Para ma-verify ang presence: <strong>selfie photo</strong> + <strong>GPS location</strong> ang kailangan bago mag-clock-in.</p>

        {!clockedIn && (
          <div className="clock-controls">
            <div className="capture-box">
              <label className="capture-label">
                {photo ? <img src={photo} alt="selfie" className="selfie-preview" /> : <span className="capture-placeholder">📸<br/>Kumuha ng selfie</span>}
                <input type="file" accept="image/*" capture="user" onChange={capturePhoto} hidden />
              </label>
              <button type="button" className="btn btn-secondary" onClick={(e) => e.currentTarget.parentNode.querySelector('input').click()}>
                {photo ? 'Ulitin ang photo' : 'Kumuha ng selfie'}
              </button>
            </div>

            <div className="geo-box">
              <button type="button" className="btn btn-secondary" onClick={getGeo}>
                📍 {geo ? 'Na-update ang GPS' : 'Kunin ang GPS location'}
              </button>
              {geo && <small className="muted">±{Math.round(geo.accuracy)}m accuracy</small>}
            </div>

            <button className="btn btn-primary btn-lg" disabled={busy} onClick={clockIn}>
              {busy ? 'Nagpoproseso…' : '🕐 CLOCK IN'}
            </button>
          </div>
        )}

        {clockedIn && !clockedOut && (
          <div className="clock-controls">
            <p className="ok-text">Naka-clock-in ka na sa {new Date(stats.today.time_in).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</p>
            <button className="btn btn-danger btn-lg" disabled={busy} onClick={clockOut}>
              {busy ? '…' : '🏁 CLOCK OUT'}
            </button>
          </div>
        )}

        {clockedIn && clockedOut && (
          <p className="ok-text">Tapos na ang attendance mo ngayong araw. ✅</p>
        )}
      </div>

      <div className="card">
        <div className="card-head">
          <h2>🌴 My Leave Requests</h2>
          <button className="btn btn-primary" onClick={() => setShowLeaveForm(!showLeaveForm)}>
            {showLeaveForm ? 'Isara' : '+ Bagong Request'}
          </button>
        </div>

        {showLeaveForm && (
          <form className="form-grid" onSubmit={submitLeave}>
            <div>
              <label>Type</label>
              <select value={form.leave_type} onChange={(e) => setForm({ ...form, leave_type: e.target.value })}>
                {['vacation', 'sick', 'personal', 'emergency', 'maternity', 'paternity', 'bereavement', 'other'].map((t) => (
                  <option key={t} value={t}>{t}</option>
                ))}
              </select>
            </div>
            <div><label>Start</label>
              <input type="date" value={form.start_date} required min={new Date().toISOString().slice(0, 10)}
                onChange={(e) => setForm({ ...form, start_date: e.target.value })} /></div>
            <div><label>End</label>
              <input type="date" value={form.end_date} required min={form.start_date || undefined}
                onChange={(e) => setForm({ ...form, end_date: e.target.value })} /></div>
            <div style={{ gridColumn: '1 / -1' }}><label>Reason</label>
              <textarea value={form.reason} rows={2} onChange={(e) => setForm({ ...form, reason: e.target.value })} /></div>
            <button className="btn btn-primary" disabled={busy}>I-submit ang Request</button>
          </form>
        )}

        <table className="table">
          <thead><tr><th>Type</th><th>Dates</th><th>Days</th><th>Status</th><th>Notes</th></tr></thead>
          <tbody>
            {leaves.length === 0 && <tr><td colSpan={5} className="muted">Wala pang leave request.</td></tr>}
            {leaves.map((l) => (
              <tr key={l.id}>
                <td><span className="badge">{l.leave_type}</span></td>
                <td>{l.start_date} → {l.end_date}</td>
                <td>{l.days}</td>
                <td><span className={`badge badge-${l.status}`}>{l.status}</span></td>
                <td className="muted">{l.review_notes || '—'}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
