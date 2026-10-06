import { createContext, useContext, useState } from 'react';
import { api, setSession, clearSession, getUser, getToken } from './api.js';

const AuthCtx = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(getUser());

  async function login(email, password) {
    const data = await api('/auth/login', { method: 'POST', body: { email, password } });
    setSession(data.token, data.user);
    setUser(data.user);
    return data.user;
  }

  function logout() {
    clearSession();
    setUser(null);
    window.location.href = '/login';
  }

  const isHR = user && (user.role === 'hr' || user.role === 'admin');

  return (
    <AuthCtx.Provider value={{ user, login, logout, isHR, isAuthed: !!getToken() }}>
      {children}
    </AuthCtx.Provider>
  );
}

export function useAuth() {
  return useContext(AuthCtx);
}
