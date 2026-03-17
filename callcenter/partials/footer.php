
    <!-- /page content -->
  </main>
</div><!-- /.app-layout -->

<!-- Bootstrap JS bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- App JS -->
<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
<!-- Offline JS -->
<script src="<?= BASE_URL ?>/assets/js/offline.js"></script>
<!-- Attendance / Idle timer JS -->
<script src="<?= BASE_URL ?>/assets/js/attendance.js"></script>

<script>
// Pass PHP config to JS
const APP = {
  baseUrl:        "<?= BASE_URL ?>",
  apiUrl:         "<?= BASE_URL ?>/api.php",
  csrfToken:      "<?= csrf_token() ?>",
  sessionTimeout: <?= SESSION_TIMEOUT ?>,
  sessionWarn:    <?= SESSION_WARN ?>,
  userId:         <?= current_user_id() ?>,
  userName:       "<?= h(current_user()['name'] ?? '') ?>",
  userRole:       "<?= current_role() ?>"
};
</script>

<!-- Sidebar desktop toggle -->
<script>
(function() {
  const sidebar = document.getElementById('desktopSidebar');
  const main    = document.getElementById('appMain');
  const toggle  = document.getElementById('sidebarToggle');
  const COLLAPSED = 'sidebar-collapsed';
  const saved = localStorage.getItem('sidebar') === 'collapsed';
  if (saved && sidebar) {
    document.body.classList.add(COLLAPSED);
  }
  if (toggle) {
    toggle.addEventListener('click', function() {
      document.body.classList.toggle(COLLAPSED);
      localStorage.setItem('sidebar',
        document.body.classList.contains(COLLAPSED) ? 'collapsed' : 'expanded');
    });
  }
})();
</script>

</body>
</html>
