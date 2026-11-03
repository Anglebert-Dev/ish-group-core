/*
 * Idle sign-out for staff (Platform settings → Security).
 * Activity in any tab counts (shared through localStorage). While the person is
 * active the server is pinged at most every 30 s; 30 s before the limit a
 * banner counts down, and at the limit the sign-out form is submitted.
 * The server (LogoutIdleStaff) enforces the same limit on its own.
 */
(function () {
    var root = document.getElementById('idle-logout');
    if (!root) return;

    var limit = parseInt(root.dataset.seconds, 10) * 1000;
    var warnAt = Math.max(limit - 30000, limit / 2);
    var pingUrl = root.dataset.pingUrl;
    var form = document.getElementById('idle-logout-form');
    var banner = document.getElementById('idle-logout-banner');
    var countdown = document.getElementById('idle-logout-seconds');
    var key = 'staff-last-activity';
    var lastPing = Date.now();
    var signingOut = false;

    function now() { return Date.now(); }
    function last() {
        var stored = 0;
        try { stored = parseInt(localStorage.getItem(key), 10) || 0; } catch (e) {}
        return Math.max(stored, lastLocal);
    }

    var lastLocal = now();
    function touch() {
        if (signingOut) return;
        lastLocal = now();
        try { localStorage.setItem(key, String(lastLocal)); } catch (e) {}
    }

    ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart', 'wheel'].forEach(function (name) {
        window.addEventListener(name, touch, { passive: true });
    });
    touch();

    document.getElementById('idle-logout-stay').addEventListener('click', function () { touch(); ping(); });

    function ping() {
        lastPing = now();
        var token = form.querySelector('input[name="_token"]');
        fetch(pingUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': token ? token.value : '', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (response) {
            if (response.status === 401 || response.status === 419) window.location.reload();
        }).catch(function () {});
    }

    setInterval(function () {
        var idle = now() - last();

        if (idle >= limit) {
            if (!signingOut) { signingOut = true; form.submit(); }
            return;
        }

        if (idle >= warnAt) {
            banner.hidden = false;
            countdown.textContent = String(Math.ceil((limit - idle) / 1000));
        } else {
            banner.hidden = true;
            // Active since the last ping: tell the server.
            if (last() > lastPing && now() - lastPing >= 30000) ping();
        }
    }, 1000);
})();
