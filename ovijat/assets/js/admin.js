/**
 * OVIJAT GROUP — assets/js/admin.js
 * Admin panel JavaScript: sidebar toggle, confirmations, misc UX.
 */
(function () {
  'use strict';

  // ─── Sidebar Toggle (mobile) ──────────────────────────────
  const sidebar       = document.getElementById('adminSidebar');
  const sidebarToggle = document.getElementById('sidebarToggle');
  const sidebarClose  = document.getElementById('sidebarClose');

  function openSidebar() {
    if (sidebar) {
      sidebar.classList.add('open');
      document.body.style.overflow = 'hidden';
    }
  }

  function closeSidebar() {
    if (sidebar) {
      sidebar.classList.remove('open');
      document.body.style.overflow = '';
    }
  }

  if (sidebarToggle) sidebarToggle.addEventListener('click', openSidebar);
  if (sidebarClose)  sidebarClose.addEventListener('click', closeSidebar);

  // Close sidebar when clicking backdrop (outside sidebar on mobile)
  document.addEventListener('click', function (e) {
    if (
      sidebar &&
      sidebar.classList.contains('open') &&
      !sidebar.contains(e.target) &&
      e.target !== sidebarToggle
    ) {
      closeSidebar();
    }
  });

  // ─── Auto-dismiss flash messages ─────────────────────────
  const flashMsg = document.querySelector('.flash-msg');
  if (flashMsg) {
    setTimeout(function () {
      flashMsg.style.transition = 'opacity 0.5s ease, max-height 0.5s ease';
      flashMsg.style.opacity    = '0';
      flashMsg.style.maxHeight  = '0';
      flashMsg.style.overflow   = 'hidden';
      flashMsg.style.padding    = '0';
      flashMsg.style.margin     = '0';
    }, 4000);
  }

  // ─── Image Preview on File Input ─────────────────────────
  document.querySelectorAll('input[type="file"][accept*="image"]').forEach(function (input) {
    input.addEventListener('change', function () {
      const file = this.files[0];
      if (!file) return;

      // Find or create preview element next to input
      let preview = this.parentElement.querySelector('.img-preview');
      if (!preview) {
        preview = document.createElement('img');
        preview.className = 'img-preview';
        preview.style.cssText = 'max-height:80px;margin-top:8px;border-radius:6px;border:1px solid #e0e0e0;';
        this.parentElement.appendChild(preview);
      }

      const reader = new FileReader();
      reader.onload = function (e) { preview.src = e.target.result; };
      reader.readAsDataURL(file);
    });
  });

  // ─── Confirm dangerous actions ────────────────────────────
  // Already handled inline with onclick="return confirm(...)"
  // This adds extra protection for any data-confirm attributes
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      if (!confirm(this.dataset.confirm)) {
        e.preventDefault();
        e.stopPropagation();
      }
    });
  });

  // ─── Collapsible Add Form ─────────────────────────────────
  // When ?action != edit, the add form starts collapsed on product page
  const productForm = document.getElementById('productForm');
  if (productForm && productForm.classList.contains('collapsible')) {
    const title = productForm.querySelector('.admin-section-title');
    const body  = productForm.querySelector('form');
    if (body) {
      // Check if we should start collapsed (no edit param)
      const url = new URL(window.location.href);
      if (!url.searchParams.get('action')) {
        body.style.display = 'none';
        productForm.classList.add('collapsed');
      }
      if (title) {
        title.style.cursor = 'pointer';
        title.title = 'Click to expand/collapse';
        title.addEventListener('click', function () {
          const isCollapsed = body.style.display === 'none';
          body.style.display = isCollapsed ? '' : 'none';
          productForm.classList.toggle('collapsed', !isCollapsed);
        });
      }
    }
  }

  // ─── Table row click to view ──────────────────────────────
  document.querySelectorAll('[data-row-href]').forEach(function (row) {
    row.style.cursor = 'pointer';
    row.addEventListener('click', function (e) {
      if (e.target.tagName === 'A' || e.target.tagName === 'BUTTON') return;
      window.location.href = this.dataset.rowHref;
    });
  });

  // ─── Character counter for textareas ─────────────────────
  document.querySelectorAll('textarea[maxlength]').forEach(function (ta) {
    const max = parseInt(ta.getAttribute('maxlength'));
    const counter = document.createElement('small');
    counter.style.cssText = 'display:block;text-align:right;color:#aaa;font-size:0.72rem;margin-top:2px;';
    ta.parentElement.appendChild(counter);
    function updateCount() {
      const rem = max - ta.value.length;
      counter.textContent = rem + ' characters remaining';
      counter.style.color = rem < 50 ? '#e67e22' : '#aaa';
    }
    ta.addEventListener('input', updateCount);
    updateCount();
  });

  // ─── Sort order hint ─────────────────────────────────────
  document.querySelectorAll('input[name="sort_order"]').forEach(function (inp) {
    inp.title = 'Lower number = appears first. Use 0, 1, 2, 3 ... to order items.';
  });

})();
