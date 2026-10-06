const API_URL = import.meta.env.VITE_API_URL || '/api';

export function getToken() {
  return localStorage.getItem('hr_token');
}

export function setSession(token, user) {
  localStorage.setItem('hr_token', token);
  localStorage.setItem('hr_user', JSON.stringify(user));
}

export function getUser() {
  try {
    return JSON.parse(localStorage.getItem('hr_user') || 'null');
  } catch {
    return null;
  }
}

export function clearSession() {
  localStorage.removeItem('hr_token');
  localStorage.removeItem('hr_user');
}

/** Generic fetch wrapper — laging nag-throw ng Error(message) kapag may problema */
export async function api(path, { method = 'GET', body } = {}) {
  const token = getToken();
  const res = await fetch(`${API_URL}${path}`, {
    method,
    headers: {
      'Content-Type': 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: body ? JSON.stringify(body) : undefined,
  });

  let data = null;
  const text = await res.text();
  try {
    data = text ? JSON.parse(text) : null;
  } catch {
    data = null;
  }

  if (res.status === 401) {
    clearSession();
    if (window.location.pathname !== '/login') {
      window.location.href = '/login';
    }
    throw new Error((data && data.error) || 'Session expired. Mag-login ulit.');
  }
  if (!res.ok) {
    throw new Error((data && data.error) || `Request failed (${res.status})`);
  }
  return data;
}

export { API_URL };
