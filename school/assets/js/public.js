// ============================================================
// public.js — School Website Public JavaScript
// ============================================================
(function () {
  'use strict';

  // ── Hero Slider ─────────────────────────────────────────
  const slides   = document.querySelectorAll('.slide');
  const dots     = document.querySelectorAll('.dot');
  const prevBtn  = document.getElementById('sliderPrev');
  const nextBtn  = document.getElementById('sliderNext');
  let current    = 0;
  let sliderTimer;

  function goSlide(n) {
    slides[current]?.classList.remove('active');
    dots[current]?.classList.remove('active');
    current = (n + slides.length) % slides.length;
    slides[current]?.classList.add('active');
    dots[current]?.classList.add('active');
  }

  function autoSlide() {
    sliderTimer = setInterval(() => goSlide(current + 1), 5000);
  }

  if (slides.length > 1) {
    prevBtn?.addEventListener('click', () => { clearInterval(sliderTimer); goSlide(current - 1); autoSlide(); });
    nextBtn?.addEventListener('click', () => { clearInterval(sliderTimer); goSlide(current + 1); autoSlide(); });
    dots.forEach((d, i) => d.addEventListener('click', () => { clearInterval(sliderTimer); goSlide(i); autoSlide(); }));
    autoSlide();

    // Touch swipe
    let startX = 0;
    const track = document.querySelector('.slider-track');
    track?.addEventListener('touchstart', e => { startX = e.touches[0].clientX; }, { passive: true });
    track?.addEventListener('touchend', e => {
      const diff = startX - e.changedTouches[0].clientX;
      if (Math.abs(diff) > 50) { clearInterval(sliderTimer); goSlide(diff > 0 ? current + 1 : current - 1); autoSlide(); }
    });
  }

  // ── Tabs ────────────────────────────────────────────────
  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function () {
      const tabId = this.dataset.tab;
      document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
      document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
      this.classList.add('active');
      document.getElementById('tab-' + tabId)?.classList.add('active');
    });
  });

  // ── Hamburger Menu ──────────────────────────────────────
  const hamburger = document.getElementById('hamburger');
  const navList   = document.getElementById('navList');

  hamburger?.addEventListener('click', function () {
    const open = navList.classList.toggle('open');
    hamburger.classList.toggle('open', open);
    hamburger.setAttribute('aria-expanded', String(open));
  });

  // Mobile dropdown toggle
  document.querySelectorAll('.nav-item.has-dropdown > a').forEach(link => {
    link.addEventListener('click', function (e) {
      if (window.innerWidth <= 768) {
        e.preventDefault();
        const parent = this.parentElement;
        parent.classList.toggle('open');
      }
    });
  });

  // Close nav on outside click
  document.addEventListener('click', e => {
    if (navList?.classList.contains('open') && !document.getElementById('mainNav')?.contains(e.target)) {
      navList.classList.remove('open');
      hamburger?.classList.remove('open');
      hamburger?.setAttribute('aria-expanded', 'false');
    }
  });

  // ── Gallery Lightbox ────────────────────────────────────
  const galleryItems = Array.from(document.querySelectorAll('.gallery-lb-item'));
  const lightbox     = document.getElementById('lightbox');
  const lbImg        = document.getElementById('lbImg');
  const lbCaption    = document.getElementById('lbCaption');
  const lbClose      = document.getElementById('lbClose');
  const lbPrev       = document.getElementById('lbPrev');
  const lbNext       = document.getElementById('lbNext');
  let lbCurrent      = 0;

  function openLightbox(idx) {
    lbCurrent = idx;
    const item = galleryItems[lbCurrent];
    if (!item) return;
    lbImg.src = item.href;
    lbCaption.textContent = item.dataset.title || '';
    lightbox?.classList.add('open');
    document.body.style.overflow = 'hidden';
  }

  function closeLightbox() {
    lightbox?.classList.remove('open');
    document.body.style.overflow = '';
  }

  galleryItems.forEach((item, idx) => {
    item.addEventListener('click', e => { e.preventDefault(); openLightbox(idx); });
  });

  lbClose?.addEventListener('click', closeLightbox);
  lbPrev?.addEventListener('click',  () => openLightbox((lbCurrent - 1 + galleryItems.length) % galleryItems.length));
  lbNext?.addEventListener('click',  () => openLightbox((lbCurrent + 1) % galleryItems.length));

  lightbox?.addEventListener('click', e => { if (e.target === lightbox) closeLightbox(); });

  document.addEventListener('keydown', e => {
    if (!lightbox?.classList.contains('open')) return;
    if (e.key === 'Escape') closeLightbox();
    if (e.key === 'ArrowLeft')  lbPrev?.click();
    if (e.key === 'ArrowRight') lbNext?.click();
  });

  // ── Scroll to Top ────────────────────────────────────────
  const scrollTop = document.getElementById('scrollTop');
  window.addEventListener('scroll', () => {
    scrollTop?.classList.toggle('visible', window.scrollY > 400);
  }, { passive: true });
  scrollTop?.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));

  // ── Ticker pause on hover ────────────────────────────────
  // (CSS handles this via .ticker-inner:hover)

  // ── Lazy images fallback ─────────────────────────────────
  if ('loading' in HTMLImageElement.prototype) {
    document.querySelectorAll('img[loading="lazy"]').forEach(img => {
      img.src = img.src; // trigger native lazy load
    });
  }

  // ── Active nav highlight ─────────────────────────────────
  const params = new URLSearchParams(window.location.search);
  const activePage = params.get('page') || 'index';
  document.querySelectorAll('.nav-item').forEach(item => {
    const link = item.querySelector('a');
    if (link) {
      const href = link.getAttribute('href') || '';
      const hParams = new URLSearchParams(href.split('?')[1] || '');
      if (hParams.get('page') === activePage) {
        item.classList.add('active');
      }
    }
  });

  // ── Smooth anchor links ──────────────────────────────────
  document.querySelectorAll('a[href^="#"]').forEach(a => {
    a.addEventListener('click', function (e) {
      const target = document.querySelector(this.getAttribute('href'));
      if (target) { e.preventDefault(); target.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    });
  });

  // ── Table responsive wrapper ─────────────────────────────
  document.querySelectorAll('.data-table').forEach(table => {
    if (!table.parentElement.classList.contains('table-wrap')) {
      const wrap = document.createElement('div');
      wrap.style.cssText = 'overflow-x:auto;-webkit-overflow-scrolling:touch;border-radius:8px;';
      table.parentNode.insertBefore(wrap, table);
      wrap.appendChild(table);
    }
  });

})();
