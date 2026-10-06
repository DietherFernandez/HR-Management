import { Routes, Route, Navigate, NavLink, useLocation } from 'react-router-dom';
import { useAuth } from './auth.jsx';
import Login from './pages/Login.jsx';
import EmployeeDashboard from './pages/EmployeeDashboard.jsx';
import HrDashboard from './pages/HrDashboard.jsx';
import Employees from './pages/Employees.jsx';
import LeaveRequests from './pages/LeaveRequests.jsx';
import Attendance from './pages/Attendance.jsx';
import SheetData from './pages/SheetData.jsx';

function Shell({ children }) {
  const { user, logout, isHR } = useAuth();
  const { pathname } = useLocation();

  const links = isHR
    ? [
        { to: '/hr', label: 'Dashboard', icon: '📊' },
        { to: '/employees', label: 'Employees', icon: '👥' },
        { to: '/leaves', label: 'Leave Requests', icon: '📝' },
        { to: '/attendance', label: 'Attendance', icon: '⏰' },
        { to: '/sheet', label: 'Live Sheet', icon: '📈' },
      ]
    : [
        { to: '/dashboard', label: 'Dashboard', icon: '📊' },
        { to: '/attendance', label: 'Attendance', icon: '⏰' },
        { to: '/leaves', label: 'My Leaves', icon: '📝' },
      ];

  return (
    <div className="layout">
      <aside className="sidebar">
        <div className="brand">
          <span className="brand-icon">HR</span>
          <div>
            <strong>HR Mgmt</strong>
            <small>{user?.department_name || 'Management System'}</small>
          </div>
        </div>
        <nav>
          {links.map((l) => (
            <NavLink key={l.to} to={l.to} className={({ isActive }) => (isActive ? 'active' : '')}>
              <span>{l.icon}</span> {l.label}
            </NavLink>
          ))}
        </nav>
        <div className="sidebar-foot">
          <div className="user-chip">
            <div className="avatar">{(user?.first_name?.[0] || '?')}{(user?.last_name?.[0] || '')}</div>
            <div>
              <strong>{user?.first_name} {user?.last_name}</strong>
              <small className="role-tag">{user?.role}</small>
            </div>
          </div>
          <button className="btn btn-ghost" onClick={logout}>Logout</button>
        </div>
      </aside>
      <main className="main" key={pathname}>{children}</main>
    </div>
  );
}

function Protected({ children, needHR }) {
  const { isAuthed, isHR } = useAuth();
  if (!isAuthed) return <Navigate to="/login" replace />;
  if (needHR && !isHR) return <Navigate to="/dashboard" replace />;
  return <Shell>{children}</Shell>;
}

export default function App() {
  const { isAuthed, isHR } = useAuth();

  return (
    <Routes>
      <Route path="/login" element={isAuthed ? <Navigate to={isHR ? '/hr' : '/dashboard'} replace /> : <Login />} />
      <Route path="/" element={<Navigate to={isHR ? '/hr' : '/dashboard'} replace />} />
      <Route path="/dashboard" element={<Protected><EmployeeDashboard /></Protected>} />
      <Route path="/hr" element={<Protected needHR><HrDashboard /></Protected>} />
      <Route path="/employees" element={<Protected needHR><Employees /></Protected>} />
      <Route path="/leaves" element={<Protected><LeaveRequests /></Protected>} />
      <Route path="/attendance" element={<Protected><Attendance /></Protected>} />
      <Route path="/sheet" element={<Protected needHR><SheetData /></Protected>} />
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}
