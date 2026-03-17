/**
 * attendance.js — Idle timer + auto-logout + screen time tracking
 * Ovijat Call Center
 */
(function() {
  'use strict';

  var TIMEOUT   = (window.APP && window.APP.sessionTimeout) || 300;  // 300s = 5min
  var WARN_AT   = 60;  // warn 60s before logout
  var timer     = null;
  var warnTimer = null;
  var remaining = TIMEOUT;
  var countdownInterval = null;

  // ── Idle Timer ─────────────────────────────────────────
  function resetTimer() {
    remaining = TIMEOUT;
    clearTimeout(timer);
    clearTimeout(warnTimer);
    clearInterval(countdownInterval);
    hideWarning();

    warnTimer = setTimeout(showWarning, (TIMEOUT - WARN_AT) * 1000);
    timer     = setTimeout(doLogout,    TIMEOUT * 1000);
  }

  function showWarning() {
    var modal = document.getElementById('idleModal');
    if (!modal) return;
    remaining = WARN_AT;
    var bsModal = new (window.bootstrap && bootstrap.Modal)(modal);
    bsModal.show();

    var countEl = document.getElementById('idleCountdown');
    clearInterval(countdownInterval);
    countdownInterval = setInterval(function() {
      remaining--;
      if (countEl) countEl.textContent = remaining;
      if (remaining <= 0) {
        clearInterval(countdownInterval);
        doLogout();
      }
    }, 1000);
    if (countEl) countEl.textContent = remaining;
  }

  function hideWarning() {
    var modal = document.getElementById('idleModal');
    if (!modal) return;
    var instance = bootstrap.Modal.getInstance(modal);
    if (instance) instance.hide();
    clearInterval(countdownInterval);
  }

  function doLogout() {
    var baseUrl = (window.APP && window.APP.baseUrl) || '';
    window.location.href = baseUrl + '/logout.php?reason=idle';
  }

  // "Stay logged in" button resets timer
  var stayBtn = document.getElementById('idleStayBtn');
  if (stayBtn) {
    stayBtn.addEventListener('click', function() {
      resetTimer();
      // Ping server to keep session alive
      fetch((window.APP && window.APP.apiUrl) + '?action=ping', { credentials: 'same-origin' })
        .catch(function() {});
    });
  }

  // Reset on user activity
  ['mousemove', 'keydown', 'mousedown', 'touchstart', 'scroll', 'click'].forEach(function(evt) {
    document.addEventListener(evt, resetTimer, { passive: true });
  });

  // Start timer
  resetTimer();

  // ── Screen Time Tracking ───────────────────────────────
  var module = document.body.dataset.module || 'unknown';
  var sessionStart = Date.now();
  var syncInterval = null;

  function syncScreenTime() {
    var duration = Math.round((Date.now() - sessionStart) / 1000);
    if (duration < 5) return; // don't log trivial durations

    var apiUrl = (window.APP && window.APP.apiUrl) || '/code/callcenter/api.php';
    var csrf   = (window.APP && window.APP.csrfToken) || '';

    fetch(apiUrl + '?action=log_screen_time', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ csrf_token: csrf, module: module, duration: duration }),
      credentials: 'same-origin',
    }).catch(function() {});

    sessionStart = Date.now(); // Reset for next interval
  }

  // Sync every 60s
  syncInterval = setInterval(syncScreenTime, 60000);

  // Sync on page unload
  document.addEventListener('visibilitychange', function() {
    if (document.visibilityState === 'hidden') syncScreenTime();
  });
  window.addEventListener('beforeunload', function() {
    clearInterval(syncInterval);
    syncScreenTime();
  });

  // ── Check-in/out Quick Action ──────────────────────────
  var checkInBtn = document.getElementById('quickCheckIn');
  if (checkInBtn) {
    checkInBtn.addEventListener('click', function() {
      var apiUrl = (window.APP && window.APP.apiUrl) || '/code/callcenter/api.php';
      fetch(apiUrl + '?action=check_in_status').then(r => r.json()).then(function(data) {
        var baseUrl = (window.APP && window.APP.baseUrl) || '';
        window.location.href = baseUrl + '/modules/attendance/index.php';
      }).catch(function() {});
    });
  }

})();
