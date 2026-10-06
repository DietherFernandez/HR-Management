import { useEffect, useState, useCallback } from 'react';
import { api } from '../api.js';
import { useAuth } from '../auth.jsx';

/** Leave requests page — employee: sarili lang; HR: lahat + approve/reject */
export default function LeaveRequests() {
  const { isHR } = useAuth();
  const [list, setList] = useState([]);
  const [filter, setFilter] = useState(isHR ? 'all' : '');
  const [notes, setNotes] = useState({});

  const load = useCallback(async () => {
    try {
      const qs = isHR && filter && filter !== 'all' ? `?status=${filter}` : '';
      const data = await api(`/leave-requests${qs}`);
      setList(data.leave_requests || []);
    } catch (e) { console.error(e); }
  }, [isHR, filter]);

  useEffect(() => { load(); }, [load]);

  async function review(id, status) {
    try {
      await api(`/leave-requests/${id}`, { method: 'PATCH', body: { status, review_notes: notes[id] || null } });
      load();
    } catch (e) { alert(e.message); }
  }

  return (
    <div className="page">
      <header className="page-head">
        <div>
          <h1>{isHR ? 'Leave Requests' : 'My Leave Requests'}</h1>
          <p className="muted">{isHR ? 'I-review at i-approve/reject ang mga request' : 'I-track ang status ng iyong mga leave'}</p>
        </div>
        {isHR && (
          <div className="chip-row">
            {['all', 'pending', 'approved', 'rejected'].map((s) => (
              <button key={s} className={`chip ${filter === s ? 'chip-active' : ''}`} onClick={() => setFilter(s)}>{s}</button>
            ))}
          </div>
        )}
      </header>

      <div className="card">
        <table className="table">
          <thead>
            <tr>
              <th>Employee</th><th>Type</th><th>Dates</th><th>Days</th><th>Reason</th><th>Status</th>{isHR && <th>Action</th>}
            </tr>
          </thead>
          <tbody>
            {list.length === 0 && <tr><td colSpan={isHR ? 7 : 6} className="muted">Walang leave request.</td></tr>}
            {list.map((l) => (
              <tr key={l.id}>
                <td>{l.first_name} {l.last_name}<br /><small className="muted">{l.employee_code}</small></td>
                <td><span className="badge">{l.leave_type}</span></td>
                <td>{l.start_date} → {l.end_date}</td>
                <td>{l.days}</td>
                <td className="muted">{l.reason || '—'}</td>
                <td><span className={`badge badge-${l.status}`}>{l.status}</span></td>
                {isHR && (
                  <td>
                    {l.status === 'pending' ? (
                      <div className="review-box">
                        <input placeholder="Notes (opsyonal)" value={notes[l.id] || ''}
                          onChange={(e) => setNotes({ ...notes, [l.id]: e.target.value })} />
                        <div className="btn-row">
                          <button className="btn btn-ok btn-sm" onClick={() => review(l.id, 'approved')}>✓ Approve</button>
                          <button className="btn btn-danger btn-sm" onClick={() => review(l.id, 'rejected')}>✕ Reject</button>
                        </div>
                      </div>
                    ) : (
                      <small className="muted">{l.reviewer_first ? `ni ${l.reviewer_first} ${l.reviewer_last}` : '—'}</small>
                    )}
                  </td>
                )}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
