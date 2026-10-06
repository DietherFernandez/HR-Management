import { useEffect, useState, useCallback } from 'react';
import { api } from '../api.js';
import { useWebSocket } from '../ws.js';

/** HR dashboard: stats + recent attendance feed + pending leaves quick actions */
export default function HrDashboard() {
  const [stats, setStats] = useState(null);
  const [attendance, setAttendance] = useState([]);
  const [pending, setPending] = useState([]);

  const load = useCallback(async () => {
    try {
      const [s, a, l] = await Promise.all([
        api('/dashboard/stats'),
        api(`/attendance?from=${new Date(Date.now() - 6 * 864e5).toISOString().slice(0, 10)}&to=${new Date().toISOString().slice(0, 10)}`),
        api('/leave-requests?status=pending'),
      ]);
      setStats(s);
      setAttendance((a.attendance || []).slice(0, 15));
      setPending(l.leave_requests || []);
    } catch (e) { console.error(e); }
  }, []);

  useEffect(() => { load(); }, [load]);

  useWebSocket((event) => {
    if (['attendance:in', 'attendance:out', 'attendance:manual', 'attendance:biometric-import', 'leave:new', 'employee:added'].includes(event)) {
      load();
    }
  });

  async function review(id, status) {
    try {
      await api(`/leave-requests/${id}`, { method: 'PATCH', body: { status } });
      load();
    } catch (e) { alert(e.message); }
  }

  return (
    <div className="page">
      <header className="page-head">
        <div><h1>HR Dashboard</h1><p className="muted">Live overview ng attendance at leave requests</p></div>
        <span className="live-dot">🟢 LIVE</span>
      </header>

      <div className="grid-4">
        <div className="card stat"><small>Total Employees</small><strong>{stats?.total_employees ?? '—'}</strong></div>
        <div className="card stat"><small>Present Today</small><strong className="ok-text">{stats?.present_today ?? 0}</strong></div>
        <div className="card stat"><small>Late Today</small><strong className="warn-text">{stats?.late_today ?? 0}</strong></div>
        <div className="card stat"><small>Pending Leaves</small><strong className="warn-text">{stats?.pending_leaves ?? 0}</strong></div>
      </div>

      <div className="grid-2">
        <div className="card">
          <h2>⏰ Recent Attendance</h2>
          <table className="table">
            <thead><tr><th>Employee</th><th>In</th><th>Status</th><th>Method</th></tr></thead>
            <tbody>
              {attendance.length === 0 && <tr><td colSpan={4} className="muted">Wala pang attendance today.</td></tr>}
              {attendance.map((a) => (
                <tr key={a.id}>
                  <td>{a.first_name} {a.last_name}<br /><small className="muted">{a.employee_code}</small></td>
                  <td>{a.time_in ? new Date(a.time_in).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '—'}</td>
                  <td><span className={`badge badge-${a.status}`}>{a.status}</span></td>
                  <td><span className="badge">{a.method}</span></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        <div className="card">
          <h2>📝 Pending Leave Requests</h2>
          {pending.length === 0 && <p className="muted">Walang pending request. 🎉</p>}
          {pending.map((l) => (
            <div className="leave-item" key={l.id}>
              <div>
                <strong>{l.first_name} {l.last_name}</strong>
                <br /><small>{l.leave_type} · {l.start_date} → {l.end_date} ({l.days}d)</small>
                {l.reason && <p className="muted small">{l.reason}</p>}
              </div>
              <div className="btn-row">
                <button className="btn btn-ok" onClick={() => review(l.id, 'approved')}>✓</button>
                <button className="btn btn-danger" onClick={() => review(l.id, 'rejected')}>✕</button>
              </div>
            </div>
          ))}
        </div>
      </div>

      <div className="card">
        <h2>📊 Attendance by Method (Today)</h2>
        <div className="method-bars">
          {['app', 'biometric', 'manual'].map((m) => {
            const v = stats?.attendance_by_method?.[m] ?? 0;
            const max = Math.max(1, ...Object.values(stats?.attendance_by_method || { app: 1 }));
            return (
              <div key={m} className="method-row">
                <span className="badge">{m}</span>
                <div className="bar-track"><div className="bar-fill" style={{ width: `${(v / max) * 100}%` }} /></div>
                <strong>{v}</strong>
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
}
