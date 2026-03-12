<?php
// ============================================================
// includes/footer.php — Common page footer
// ============================================================
?>
</main>

<!-- ── Variant Selector Modal ──────────────────────────────── -->
<div class="modal-backdrop" id="variantModal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Select Variant</span>
      <button class="modal-close">✕</button>
    </div>
    <div class="modal-body" id="variantModalBody"></div>
  </div>
</div>

<!-- ── Footer ──────────────────────────────────────────────── -->
<footer class="app-footer no-print">
  <?= APP_NAME ?> v<?= APP_VERSION ?> &nbsp;·&nbsp; <?= date('Y') ?>
</footer>

<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
<script>
// Auto-redirect POS to offline page if network is lost
window.addEventListener('offline', function() {
  const page = new URLSearchParams(location.search).get('page');
  if (page === 'pos') {
    if (confirm('You appear to be offline. Switch to Offline POS?')) {
      location.href = '<?= BASE_URL ?>/offline.php';
    }
  }
});
// Show offline banner on any page
window.addEventListener('offline', function() {
  const bar = document.createElement('div');
  bar.id = 'offlineBanner';
  bar.style.cssText = 'position:fixed;top:56px;left:0;right:0;z-index:999;background:#ef4444;color:#fff;text-align:center;padding:6px;font-size:.85rem;font-weight:700';
  bar.innerHTML = '⚠️ You are offline — <a href="<?= BASE_URL ?>/offline.php" style="color:#fff;text-decoration:underline">Switch to Offline POS</a>';
  document.body.appendChild(bar);
});
window.addEventListener('online', function() {
  document.getElementById('offlineBanner')?.remove();
});
</script>
<?php if (!empty($extraJs)): ?>
  <script><?= $extraJs ?></script>
<?php endif ?>
</body>
</html>
