// Presence means an authenticated person is recently active in a foreground tab.
export function startPresence() {
  let lastActivity = Date.now();
  let lastHeartbeat = 0;
  let inFlight = false;
  let stopped = false;
  async function heartbeat() {
    if (stopped || inFlight || document.hidden || !document.hasFocus() || Date.now() - lastActivity > 120000 || Date.now() - lastHeartbeat < 25000) return;
    inFlight = true;
    lastHeartbeat = Date.now();
    try {
      const response = await fetch('/presence/heartbeat', {
        method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' }
      });
      if ([401, 419].includes(response.status)) stopped = true;
    } catch { /* A failed heartbeat expires naturally. */ }
    finally { inFlight = false; }
  }
  for (const event of ['pointerdown', 'keydown', 'pointermove', 'scroll']) {
    window.addEventListener(event, () => { lastActivity = Date.now(); }, { passive: true });
  }
  window.addEventListener('focus', () => { lastActivity = Date.now(); heartbeat(); });
  document.addEventListener('visibilitychange', () => { if (!document.hidden) { lastActivity = Date.now(); heartbeat(); } });
  heartbeat();
  setInterval(heartbeat, 30000);
}
