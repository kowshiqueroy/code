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
<?php if (!empty($extraJs)): ?>
  <script><?= $extraJs ?></script>
<?php endif ?>
</body>
</html>
