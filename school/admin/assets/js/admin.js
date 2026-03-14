// admin.js
(function(){
  'use strict';

  // Sidebar toggle (mobile)
  const toggle   = document.getElementById('sidebarToggle');
  const sidebar  = document.getElementById('adminSidebar');
  toggle?.addEventListener('click', () => sidebar?.classList.toggle('open'));
  document.addEventListener('click', e => {
    if (sidebar?.classList.contains('open') && !sidebar.contains(e.target) && e.target !== toggle) {
      sidebar.classList.remove('open');
    }
  });

  // Admin tabs
  document.querySelectorAll('.atab-btn').forEach(btn => {
    btn.addEventListener('click', function(){
      const group = this.closest('[data-tabs]') || this.parentElement.closest('.atabs')?.parentElement;
      const tab   = this.dataset.tab;
      this.closest('.atabs')?.querySelectorAll('.atab-btn').forEach(b=>b.classList.remove('active'));
      this.classList.add('active');
      (group || document).querySelectorAll('.atab-content').forEach(c=>c.classList.remove('active'));
      (group || document).querySelector('#atab-'+tab)?.classList.add('active');
    });
  });

  // Delete confirm
  document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', function(e){
      if (!confirm(this.dataset.confirm || 'Are you sure?')) e.preventDefault();
    });
  });

  // Simple rich text toolbar
  document.querySelectorAll('[data-richtext]').forEach(area => {
    const toolbar = area.previousElementSibling;
    if (!toolbar?.classList.contains('richtext-toolbar')) return;
    toolbar.querySelectorAll('button[data-cmd]').forEach(btn => {
      btn.addEventListener('click', e => {
        e.preventDefault();
        const cmd = btn.dataset.cmd;
        const val = btn.dataset.val || null;
        area.focus();
        document.execCommand(cmd, false, val);
      });
    });
    area.addEventListener('input', () => {
      const hidden = document.getElementById(area.id + '_hidden');
      if (hidden) hidden.value = area.innerHTML;
    });
  });

  // Image preview on file select
  document.querySelectorAll('input[type=file][data-preview]').forEach(input => {
    input.addEventListener('change', function(){
      const prev = document.getElementById(this.dataset.preview);
      if (prev && this.files[0]) {
        prev.src = URL.createObjectURL(this.files[0]);
        prev.style.display = 'block';
      }
    });
  });

  // Modal
  document.querySelectorAll('[data-modal-open]').forEach(btn => {
    btn.addEventListener('click', () => {
      document.getElementById(btn.dataset.modalOpen)?.classList.add('open');
    });
  });
  document.querySelectorAll('.modal-close, [data-modal-close]').forEach(btn => {
    btn.addEventListener('click', () => {
      btn.closest('.modal-overlay')?.classList.remove('open');
    });
  });
  document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', e => { if (e.target === overlay) overlay.classList.remove('open'); });
  });

  // Media library picker
  document.querySelectorAll('.img-grid-item').forEach(item => {
    item.addEventListener('click', function(){
      const group = this.closest('.img-grid');
      group?.querySelectorAll('.img-grid-item').forEach(i=>i.classList.remove('selected'));
      this.classList.add('selected');
      const target = group?.dataset.target;
      if (target) {
        document.getElementById(target + '_val').value = this.dataset.file;
        const prev = document.getElementById(target + '_preview');
        if (prev) { prev.src = this.querySelector('img').src; prev.style.display='block'; }
      }
    });
  });

  // Auto-dismiss flash after 4s
  const flash = document.querySelector('.flash-msg');
  if (flash) setTimeout(() => flash.remove(), 4000);

  // Confirm status toggle
  document.querySelectorAll('.status-toggle').forEach(chk => {
    chk.addEventListener('change', function(){
      const form = this.closest('form');
      form?.submit();
    });
  });

})();
