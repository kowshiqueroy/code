/**
 * BanglaEdu CMS — Main App JS
 */
(function() {
'use strict';

// ─── Mobile Menu ──────────────────────────────────────────────────────────────
const menuToggle  = document.getElementById('menuToggle');
const menuClose   = document.getElementById('menuClose');
const mobileMenu  = document.getElementById('mobileMenu');
const mobileOverlay = document.getElementById('mobileOverlay');

function openMenu() {
  mobileMenu?.classList.add('open');
  mobileOverlay?.classList.add('open');
  menuToggle?.setAttribute('aria-expanded', 'true');
  document.body.style.overflow = 'hidden';
}
function closeMenu() {
  mobileMenu?.classList.remove('open');
  mobileOverlay?.classList.remove('open');
  menuToggle?.setAttribute('aria-expanded', 'false');
  document.body.style.overflow = '';
}

menuToggle?.addEventListener('click', openMenu);
menuClose?.addEventListener('click', closeMenu);
mobileOverlay?.addEventListener('click', closeMenu);

document.addEventListener('keydown', e => {
  if (e.key === 'Escape') closeMenu();
});

// ─── Hero Slider ──────────────────────────────────────────────────────────────
const sliderTrack = document.querySelector('.slider-track');
const slides      = document.querySelectorAll('.slide');
const dots        = document.querySelectorAll('.slider-dot');
const prevBtn     = document.querySelector('.slider-prev');
const nextBtn     = document.querySelector('.slider-next');

if (sliderTrack && slides.length > 1) {
  let current = 0;
  let autoplay = null;

  function goTo(idx) {
    current = (idx + slides.length) % slides.length;
    sliderTrack.style.transform = `translateX(-${current * 100}%)`;
    dots.forEach((d, i) => d.classList.toggle('active', i === current));
  }

  function startAuto() {
    autoplay = setInterval(() => goTo(current + 1), 5000);
  }
  function stopAuto() {
    clearInterval(autoplay);
  }

  dots.forEach((dot, i) => dot.addEventListener('click', () => { stopAuto(); goTo(i); startAuto(); }));
  prevBtn?.addEventListener('click', () => { stopAuto(); goTo(current - 1); startAuto(); });
  nextBtn?.addEventListener('click', () => { stopAuto(); goTo(current + 1); startAuto(); });

  // Touch swipe
  let touchStartX = 0;
  sliderTrack.addEventListener('touchstart', e => { touchStartX = e.touches[0].clientX; });
  sliderTrack.addEventListener('touchend', e => {
    const diff = touchStartX - e.changedTouches[0].clientX;
    if (Math.abs(diff) > 50) { stopAuto(); goTo(current + (diff > 0 ? 1 : -1)); startAuto(); }
  });

  goTo(0);
  startAuto();
}

// ─── Scroll to Top ────────────────────────────────────────────────────────────
const scrollTop = document.getElementById('scrollTop');
window.addEventListener('scroll', () => {
  scrollTop?.classList.toggle('visible', window.scrollY > 300);
}, { passive: true });
scrollTop?.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));

// ─── Lightbox ─────────────────────────────────────────────────────────────────
const lightbox      = document.getElementById('lightbox');
const lightboxImg   = document.getElementById('lightboxImg');
const lightboxClose = document.getElementById('lightboxClose');

document.querySelectorAll('[data-lightbox]').forEach(el => {
  el.addEventListener('click', e => {
    e.preventDefault();
    const src = el.getAttribute('data-lightbox');
    const alt = el.getAttribute('data-alt') || '';
    if (lightboxImg) { lightboxImg.src = src; lightboxImg.alt = alt; }
    lightbox?.classList.add('active');
    document.body.style.overflow = 'hidden';
  });
});

lightboxClose?.addEventListener('click', closeLightbox);
lightbox?.addEventListener('click', e => { if (e.target === lightbox) closeLightbox(); });
function closeLightbox() {
  lightbox?.classList.remove('active');
  document.body.style.overflow = '';
}

// ─── Lazy Load Images ─────────────────────────────────────────────────────────
if ('IntersectionObserver' in window) {
  const lazyImgs = document.querySelectorAll('img[loading="lazy"]');
  const observer = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const img = entry.target;
        if (img.dataset.src) img.src = img.dataset.src;
        observer.unobserve(img);
      }
    });
  }, { rootMargin: '200px' });
  lazyImgs.forEach(img => observer.observe(img));
}

// ─── Animate on Scroll ────────────────────────────────────────────────────────
if ('IntersectionObserver' in window) {
  const animateEls = document.querySelectorAll('.card, .notice-card, .stat-item');
  const aObserver = new IntersectionObserver(entries => {
    entries.forEach((entry, i) => {
      if (entry.isIntersecting) {
        setTimeout(() => {
          entry.target.style.opacity = '1';
          entry.target.style.transform = 'translateY(0)';
        }, i * 60);
        aObserver.unobserve(entry.target);
      }
    });
  }, { threshold: 0.1 });

  animateEls.forEach(el => {
    el.style.opacity = '0';
    el.style.transform = 'translateY(20px)';
    el.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
    aObserver.observe(el);
  });
}

// ─── Counter Animation ────────────────────────────────────────────────────────
function animateCounter(el) {
  const target = parseInt(el.dataset.count || el.textContent.replace(/\D/g,''), 10);
  const suffix = el.textContent.replace(/[0-9]/g, '');
  let current = 0;
  const step = Math.max(1, Math.floor(target / 60));
  const timer = setInterval(() => {
    current = Math.min(current + step, target);
    el.textContent = current.toLocaleString('en') + suffix;
    if (current >= target) clearInterval(timer);
  }, 20);
}

const counterEls = document.querySelectorAll('.stat-number[data-count]');
if (counterEls.length && 'IntersectionObserver' in window) {
  const cObs = new IntersectionObserver(entries => {
    entries.forEach(e => { if (e.isIntersecting) { animateCounter(e.target); cObs.unobserve(e.target); } });
  });
  counterEls.forEach(el => cObs.observe(el));
}

// ─── Contact Form ─────────────────────────────────────────────────────────────
const contactForm = document.getElementById('contactForm');
contactForm?.addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn = this.querySelector('[type=submit]');
  const original = btn.textContent;
  btn.textContent = 'Sending...';
  btn.disabled = true;

  try {
    const res = await fetch('/modules/contact.php', {
      method: 'POST',
      body: new FormData(this)
    });
    const data = await res.json();
    const alertEl = document.getElementById('contactAlert');
    if (alertEl) {
      alertEl.className = 'alert alert-' + (data.success ? 'success' : 'error');
      alertEl.textContent = data.message;
      alertEl.style.display = 'block';
    }
    if (data.success) this.reset();
  } catch (err) {
    console.error(err);
  }

  btn.textContent = original;
  btn.disabled = false;
});

// ─── Accordion ────────────────────────────────────────────────────────────────
document.querySelectorAll('.accordion-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    const panel = this.nextElementSibling;
    const isOpen = panel?.style.maxHeight;
    document.querySelectorAll('.accordion-panel').forEach(p => p.style.maxHeight = '');
    document.querySelectorAll('.accordion-btn').forEach(b => b.classList.remove('open'));
    if (!isOpen) {
      panel.style.maxHeight = panel.scrollHeight + 'px';
      this.classList.add('open');
    }
  });
});

// ─── Print Page ───────────────────────────────────────────────────────────────
document.querySelectorAll('[data-print]').forEach(el => {
  el.addEventListener('click', () => window.print());
});

})();
