import { useEffect, useRef, useState } from 'react';
import { getToken, getUser } from './api.js';

const WS_URL = import.meta.env.VITE_WS_URL || 'ws://localhost:8081';

// Kapag naka-host sa ibang domain ang API (hal. XAMPP server), dito i-redirect ang WS.

/**
 * Hook para sa live WebSocket updates.
 * onEvent(event, data) ang tatawagin sa bawat bagong message mula sa server.
 */
export function useWebSocket(onEvent) {
  const [connected, setConnected] = useState(false);
  const cbRef = useRef(onEvent);
  cbRef.current = onEvent;

  useEffect(() => {
    const user = getUser();
    const token = getToken();
    if (!user || !token) return;

    let ws = null;
    let retry = 0;
    let closed = false;

    function connect() {
      const url = `${WS_URL}?employee_id=${user.id}&role=${user.role}&token=${encodeURIComponent(token)}`;
      ws = new WebSocket(url);

      ws.onopen = () => {
        retry = 0;
        setConnected(true);
      };

      ws.onmessage = (e) => {
        try {
          const msg = JSON.parse(e.data);
          if (msg.event && msg.event !== 'connected') {
            cbRef.current?.(msg.event, msg.data || {});
          }
        } catch {
          /* ignore */
        }
      };

      ws.onclose = () => {
        setConnected(false);
        if (closed) return;
        // Exponential backoff — max 15s
        retry++;
        const delay = Math.min(15000, 1000 * 2 ** Math.min(retry, 4));
        setTimeout(connect, delay);
      };

      ws.onerror = () => ws?.close();
    }

    connect();
    return () => {
      closed = true;
      ws?.close();
    };
  }, []);

  return connected;
}
