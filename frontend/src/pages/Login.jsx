import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../auth.jsx';

export default function Login() {
  const { login, isHR } = useAuth();
  const nav = useNavigate();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [err, setErr] = useState('');
  const [busy, setBusy] = useState(false);

  async function submit(e) {
    e.preventDefault();
    setErr('');
    setBusy(true);
    try {
      const user = await login(email, password);
      nav(user.role === 'hr' || user.role === 'admin' ? '/hr' : '/dashboard', { replace: true });
    } catch (ex) {
      setErr(ex.message);
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="login-wrap">
      <form className="login-card" onSubmit={submit}>
        <div className="login-logo">HR</div>
        <h1>HR Management</h1>
        <p className="muted">Mag-login gamit ang email at password na binigay ng HR.</p>

        {err && <div className="alert alert-error">{err}</div>}

        <label>Email</label>
        <input type="email" value={email} onChange={(e) => setEmail(e.target.value)}
          placeholder="juan@company.com" required autoFocus />

        <label>Password</label>
        <input type="password" value={password} onChange={(e) => setPassword(e.target.value)}
          placeholder="••••••••" required />

        <button className="btn btn-primary btn-block" disabled={busy}>
          {busy ? 'Naglo-login…' : 'Login'}
        </button>

        <p className="muted small" style={{ marginTop: 16 }}>
          Demo: <code>hr@company.com / admin123</code> (HR) · <code>juan@company.com / emp123</code> (Employee)
        </p>
      </form>
    </div>
  );
}
