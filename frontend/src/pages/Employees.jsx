import { useEffect, useState, useCallback } from 'react';
import { api } from '../api.js';

/** Employee management (HR only): list, search, add, edit */
export default function Employees() {
  const [list, setList] = useState([]);
  const [depts, setDepts] = useState([]);
  const [q, setQ] = useState('');
  const [showForm, setShowForm] = useState(false);
  const [editing, setEditing] = useState(null);
  const [msg, setMsg] = useState(null);
  const [busy, setBusy] = useState(false);
  const empty = {
    first_name: '', last_name: '', email: '', password: '', role: 'employee',
    department_id: '', position: '', phone: '', salary: '', date_hired: new Date().toISOString().slice(0, 10),
  };
  const [form, setForm] = useState(empty);

  const load = useCallback(async () => {
    try {
      const [e, d] = await Promise.all([api(`/employees?q=${encodeURIComponent(q)}`), api('/departments')]);
      setList(e.employees || []);
      setDepts(d.departments || []);
    } catch (ex) { setMsg({ type: 'error', text: ex.message }); }
  }, [q]);

  useEffect(() => {
    const t = setTimeout(load, 300);
    return () => clearTimeout(t);
  }, [load]);

  function openAdd() { setEditing(null); setForm(empty); setShowForm(true); setMsg(null); }
  function openEdit(emp) {
    setEditing(emp);
    setForm({
      first_name: emp.first_name, last_name: emp.last_name, email: emp.email, password: '',
      role: emp.role, department_id: emp.department_id || '', position: emp.position || '',
      phone: emp.phone || '', salary: emp.salary || '', date_hired: emp.date_hired || '',
      status: emp.status,
    });
    setShowForm(true);
  }

  async function submit(e) {
    e.preventDefault();
    setBusy(true);
    setMsg(null);
    try {
      if (editing) {
        const body = { ...form };
        if (!body.password) delete body.password;
        await api(`/employees/${editing.id}`, { method: 'PUT', body });
        setMsg({ type: 'ok', text: `Na-update si ${form.first_name} ${form.last_name}.` });
      } else {
        await api('/employees', { method: 'POST', body: form });
        setMsg({ type: 'ok', text: `Idinagdag si ${form.first_name} ${form.last_name}.` });
      }
      setShowForm(false);
      load();
    } catch (ex) { setMsg({ type: 'error', text: ex.message }); }
    finally { setBusy(false); }
  }

  async function deactivate(emp) {
    if (!confirm(`I-deactivate si ${emp.first_name} ${emp.last_name}? Mawawalan sila ng access.`)) return;
    try {
      await api(`/employees/${emp.id}`, { method: 'DELETE' });
      setMsg({ type: 'ok', text: 'Na-deactivate ang employee.' });
      load();
    } catch (ex) { setMsg({ type: 'error', text: ex.message }); }
  }

  return (
    <div className="page">
      <header className="page-head">
        <div><h1>Employees</h1><p className="muted">{list.length} employee(s) sa system</p></div>
        <button className="btn btn-primary" onClick={openAdd}>+ Employee</button>
      </header>

      {msg && <div className={`alert alert-${msg.type}`}>{msg.text}</div>}

      <div className="card">
        <input className="search" placeholder="🔍 Hanapin (name, email, code)…" value={q} onChange={(e) => setQ(e.target.value)} />

        {showForm && (
          <form className="form-grid form-section" onSubmit={submit}>
            <h3 style={{ gridColumn: '1 / -1' }}>{editing ? 'I-update ang Employee' : 'Bagong Employee'}</h3>
            <div><label>First name *</label>
              <input required value={form.first_name} onChange={(e) => setForm({ ...form, first_name: e.target.value })} /></div>
            <div><label>Last name *</label>
              <input required value={form.last_name} onChange={(e) => setForm({ ...form, last_name: e.target.value })} /></div>
            <div><label>Email *</label>
              <input type="email" required value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} /></div>
            <div><label>{editing ? 'Bagong password (opsyonal)' : 'Password * (min 6)'}</label>
              <input type="text" required={!editing} value={form.password}
                onChange={(e) => setForm({ ...form, password: e.target.value })} /></div>
            <div><label>Role</label>
              <select value={form.role} onChange={(e) => setForm({ ...form, role: e.target.value })}>
                <option value="employee">Employee</option>
                <option value="hr">HR</option>
                <option value="admin">Admin</option>
              </select></div>
            <div><label>Department</label>
              <select value={form.department_id} onChange={(e) => setForm({ ...form, department_id: e.target.value })}>
                <option value="">— None —</option>
                {depts.map((d) => <option key={d.id} value={d.id}>{d.name}</option>)}
              </select></div>
            <div><label>Position</label>
              <input value={form.position} onChange={(e) => setForm({ ...form, position: e.target.value })} /></div>
            <div><label>Phone</label>
              <input value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} /></div>
            <div><label>Salary</label>
              <input type="number" min="0" step="0.01" value={form.salary} onChange={(e) => setForm({ ...form, salary: e.target.value })} /></div>
            <div><label>Date hired</label>
              <input type="date" value={form.date_hired} onChange={(e) => setForm({ ...form, date_hired: e.target.value })} /></div>
            {editing && (
              <div><label>Status</label>
                <select value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value })}>
                  <option value="active">Active</option>
                  <option value="inactive">Inactive</option>
                </select></div>
            )}
            <div className="form-actions">
              <button className="btn btn-primary" disabled={busy}>{editing ? 'I-save' : 'Idagdag'}</button>
              <button type="button" className="btn btn-ghost" onClick={() => setShowForm(false)}>Kanselahin</button>
            </div>
          </form>
        )}

        <table className="table">
          <thead><tr><th>Code</th><th>Name</th><th>Email</th><th>Dept</th><th>Role</th><th>Status</th><th></th></tr></thead>
          <tbody>
            {list.length === 0 && <tr><td colSpan={7} className="muted">Walang employee na tugma.</td></tr>}
            {list.map((e) => (
              <tr key={e.id}>
                <td><code>{e.employee_code}</code></td>
                <td>{e.first_name} {e.last_name}</td>
                <td>{e.email}</td>
                <td>{e.department_name || '—'}</td>
                <td><span className={`badge badge-${e.role}`}>{e.role}</span></td>
                <td><span className={`badge badge-${e.status}`}>{e.status}</span></td>
                <td className="btn-row">
                  <button className="btn btn-sm" onClick={() => openEdit(e)}>✏️</button>
                  {e.status === 'active' && <button className="btn btn-sm btn-danger" onClick={() => deactivate(e)}>🗑</button>}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
