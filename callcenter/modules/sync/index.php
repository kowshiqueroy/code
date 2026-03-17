<?php
require_once dirname(__DIR__, 2) . '/config.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/helpers.php';
require_login();

$page_title  = 'Sync Queue';
$active_page = 'sync';

require ROOT . '/partials/header.php';
?>

<div class="page-header no-print">
  <i class="bi bi-cloud-upload-fill text-info"></i>
  <h5>Offline Sync Queue</h5>
  <div class="ms-auto">
    <span id="onlineIndicator" class="badge bg-secondary">Checking…</span>
  </div>
</div>

<div class="page-body">

  <div class="card mb-3">
    <div class="card-body py-2 d-flex align-items-center gap-3">
      <div>
        <span class="fw-semibold" id="pendingCount">0</span>
        <span class="text-muted small"> items pending sync</span>
      </div>
      <button id="syncAllBtn" class="btn btn-sm btn-primary ms-auto" disabled>
        <i class="bi bi-cloud-upload me-1"></i>Sync All
      </button>
      <button id="refreshBtn" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-clockwise me-1"></i>Refresh
      </button>
    </div>
  </div>

  <div id="syncItems">
    <div class="text-center text-muted py-5">
      <i class="bi bi-inbox display-4 d-block mb-2"></i>
      Loading queue…
    </div>
  </div>

</div>

<script>
(function() {
  var items = [];

  function typeLabel(type) {
    var labels = {
      call: 'Call Log', sms: 'SMS', task: 'Task',
      attendance: 'Attendance', contact: 'Contact',
    };
    return labels[type] || type;
  }

  function typeIcon(type) {
    var icons = {
      call: 'telephone', sms: 'chat-dots', task: 'check2-square',
      attendance: 'clock', contact: 'person',
    };
    return icons[type] || 'file';
  }

  function formatData(item) {
    var d = item.data;
    if (item.type === 'call') {
      return 'Contact: ' + (d.contact_name || d.contact_id || '—') +
             ' | ' + (d.direction || '') + ' | ' + (d.started_at || '');
    }
    if (item.type === 'sms') {
      return 'To: ' + (d.phone_to || '—') + ' — ' + (d.message || '').substring(0, 50);
    }
    if (item.type === 'attendance') {
      return (d.action || '') + ' — ' + (d.date || '') + ' ' + (d.mode || '');
    }
    if (item.type === 'task') {
      return (d.title || '—');
    }
    return JSON.stringify(d).substring(0, 80);
  }

  function renderItems(allItems) {
    var container = document.getElementById('syncItems');
    var pending = allItems.filter(i => i.status === 'pending');

    document.getElementById('pendingCount').textContent = pending.length;
    document.getElementById('syncAllBtn').disabled = pending.length === 0 || !navigator.onLine;

    if (allItems.length === 0) {
      container.innerHTML = '<div class="text-center text-muted py-5">' +
        '<i class="bi bi-check-circle display-4 d-block mb-2 text-success"></i>' +
        '<p>Queue is empty. All items have been synced.</p></div>';
      return;
    }

    var html = '<div class="list-group">';
    allItems.forEach(function(item) {
      var statusBadge = {
        'pending':  '<span class="badge bg-warning text-dark">Pending</span>',
        'synced':   '<span class="badge bg-success">Synced</span>',
        'failed':   '<span class="badge bg-danger">Failed</span>',
        'skipped':  '<span class="badge bg-secondary">Skipped</span>',
        'conflict': '<span class="badge bg-danger">Conflict</span>',
      }[item.status] || '<span class="badge bg-secondary">' + item.status + '</span>';

      html += '<div class="list-group-item" id="item-' + item.local_id + '">';
      html += '<div class="d-flex align-items-start gap-3">';
      html += '<i class="bi bi-' + typeIcon(item.type) + ' text-primary mt-1 fs-5"></i>';
      html += '<div class="flex-grow-1">';
      html += '<div class="d-flex align-items-center gap-2 mb-1">';
      html += '<strong>' + typeLabel(item.type) + '</strong>';
      html += statusBadge;
      html += '<span class="text-muted small ms-auto">' + (item.created_at || '').replace('T', ' ').substring(0, 16) + '</span>';
      html += '</div>';
      html += '<div class="text-muted small">' + formatData(item) + '</div>';

      if (item.conflict) {
        html += '<div class="alert alert-warning py-1 px-2 mt-2 small"><strong>Conflict:</strong> ' +
          item.conflict + '</div>';
      }

      html += '</div>';

      // Action buttons
      html += '<div class="d-flex gap-1 flex-shrink-0">';
      if (item.status === 'pending' || item.status === 'failed') {
        html += '<button class="btn btn-sm btn-outline-primary" onclick="syncOne(' + item.local_id + ')">Sync</button>';
        html += '<button class="btn btn-sm btn-outline-secondary" onclick="skipOne(' + item.local_id + ')">Skip</button>';
      }
      html += '<button class="btn btn-sm btn-outline-danger" onclick="removeOne(' + item.local_id + ')">Remove</button>';
      html += '</div>';

      html += '</div></div>';
    });
    html += '</div>';
    container.innerHTML = html;
  }

  function loadItems() {
    if (!window.OfflineQueue) return;
    OfflineQueue.getAll().then(renderItems).catch(console.error);
  }

  window.syncOne = function(local_id) {
    if (!navigator.onLine) return alert('You are offline.');
    OfflineQueue.getAll().then(function(all) {
      var item = all.find(i => i.local_id === local_id);
      if (!item) return;
      var btn = document.querySelector('#item-' + local_id + ' .btn-outline-primary');
      if (btn) { btn.disabled = true; btn.textContent = 'Syncing…'; }

      OfflineQueue.sync(item).then(function(res) {
        if (res.success) {
          OfflineQueue.updateStatus(local_id, 'synced', res.server_id).then(loadItems);
        } else if (res.conflict) {
          OfflineQueue.updateStatus(local_id, 'conflict', null, res.conflict_detail).then(loadItems);
        } else {
          OfflineQueue.updateStatus(local_id, 'failed').then(loadItems);
          if (window.showToast) showToast('Sync failed: ' + (res.message || 'unknown error'), 'danger');
        }
      }).catch(function() {
        OfflineQueue.updateStatus(local_id, 'failed').then(loadItems);
      });
    });
  };

  window.skipOne = function(local_id) {
    OfflineQueue.updateStatus(local_id, 'skipped').then(loadItems);
  };

  window.removeOne = function(local_id) {
    if (!confirm('Remove this item from queue?')) return;
    OfflineQueue.remove(local_id).then(loadItems);
  };

  document.getElementById('syncAllBtn').addEventListener('click', function() {
    if (!navigator.onLine) return alert('You are offline.');
    OfflineQueue.getPending().then(function(pending) {
      var queue = Promise.resolve();
      pending.forEach(function(item) {
        queue = queue.then(() => {
          return OfflineQueue.sync(item).then(function(res) {
            var status = res.success ? 'synced' : (res.conflict ? 'conflict' : 'failed');
            return OfflineQueue.updateStatus(item.local_id, status, res.server_id || null, res.conflict_detail || null);
          }).catch(() => OfflineQueue.updateStatus(item.local_id, 'failed'));
        });
      });
      return queue;
    }).then(loadItems).catch(console.error);
  });

  document.getElementById('refreshBtn').addEventListener('click', loadItems);

  // Online indicator
  function updateIndicator() {
    var el = document.getElementById('onlineIndicator');
    if (!el) return;
    if (navigator.onLine) {
      el.className = 'badge bg-success';
      el.textContent = 'Online';
      document.getElementById('syncAllBtn').disabled = false;
    } else {
      el.className = 'badge bg-danger';
      el.textContent = 'Offline';
      document.getElementById('syncAllBtn').disabled = true;
    }
  }
  window.addEventListener('online',  updateIndicator);
  window.addEventListener('offline', updateIndicator);
  updateIndicator();

  // Initial load
  document.addEventListener('DOMContentLoaded', loadItems);
})();
</script>

<?php require ROOT . '/partials/footer.php'; ?>
