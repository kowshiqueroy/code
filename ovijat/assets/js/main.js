/**
 * OVIJAT GROUP — main.js v2.0
 * Public JS: loader, hero slider, nav, scroll animations, dropdown
 */
(function () {
  'use strict';

  /* ── Loading Screen ───────────────────────────────── */
  const loader = document.getElementById('loading-screen');
  if (loader) {
    window.addEventListener('load', function () {
      setTimeout(function () {
        loader.classList.add('hidden');
        loader.addEventListener('transitionend', function () { loader.remove(); }, { once: true });
      }, 1800);
    });
  }

  /* ── Hero Slider ──────────────────────────────────── */
  const slider = document.getElementById('heroSlider');
  if (slider) {
    const slides = slider.querySelectorAll('.hero-slide');
    const dots   = slider.querySelectorAll('.hero-dot');
    const prev   = slider.querySelector('.hero-prev');
    const next   = slider.querySelector('.hero-next');
    let current  = 0, timer = null;
    const total  = slides.length;

    function goTo(idx) {
      slides[current].classList.remove('active');
      if (dots[current]) dots[current].classList.remove('active');
      current = (idx + total) % total;
      slides[current].classList.add('active');
      if (dots[current]) dots[current].classList.add('active');
    }
    function startAuto() { timer = setInterval(function () { goTo(current + 1); }, 5200); }
    function stopAuto()  { clearInterval(timer); }

    if (total > 1) {
      if (prev) prev.addEventListener('click', function () { stopAuto(); goTo(current - 1); startAuto(); });
      if (next) next.addEventListener('click', function () { stopAuto(); goTo(current + 1); startAuto(); });
      dots.forEach(function (d) {
        d.addEventListener('click', function () { stopAuto(); goTo(parseInt(d.dataset.target)); startAuto(); });
      });
      slider.addEventListener('mouseenter', stopAuto);
      slider.addEventListener('mouseleave', startAuto);
      let tx = 0;
      slider.addEventListener('touchstart', function (e) { tx = e.touches[0].clientX; }, { passive: true });
      slider.addEventListener('touchend',   function (e) {
        const dx = e.changedTouches[0].clientX - tx;
        if (Math.abs(dx) > 48) { stopAuto(); goTo(current + (dx < 0 ? 1 : -1)); startAuto(); }
      }, { passive: true });
      startAuto();
    }
  }

  /* ── Sticky Header ────────────────────────────────── */
  const siteHeader = document.getElementById('siteHeader');
  if (siteHeader) {
    window.addEventListener('scroll', function () {
      siteHeader.classList.toggle('scrolled', window.scrollY > 8);
    }, { passive: true });
  }

  /* ── Hamburger / Mobile Nav ────────────────────────── */
  const hamburger = document.getElementById('hamburger');
  const mobileNav = document.getElementById('mobileNav');
  if (hamburger && mobileNav) {
    hamburger.addEventListener('click', function () {
      const open = mobileNav.classList.toggle('open');
      hamburger.setAttribute('aria-expanded', open);
      document.body.style.overflow = open ? 'hidden' : '';
      const s = hamburger.querySelectorAll('span');
      if (open) {
        s[0].style.transform = 'rotate(45deg) translate(5px,5px)';
        s[1].style.opacity   = '0';
        s[2].style.transform = 'rotate(-45deg) translate(5px,-5px)';
      } else {
        s[0].style.transform = s[2].style.transform = '';
        s[1].style.opacity = '';
      }
    });
    mobileNav.querySelectorAll('.mobile-nav-link,.mobile-sub-link').forEach(function (l) {
      l.addEventListener('click', function () {
        mobileNav.classList.remove('open');
        document.body.style.overflow = '';
        const s = hamburger.querySelectorAll('span');
        s[0].style.transform = s[2].style.transform = '';
        s[1].style.opacity = '';
      });
    });
  }

  /* ── Scroll Reveal Animations ─────────────────────── */
  function initReveal() {
    const els = document.querySelectorAll('.reveal,.reveal-left,.reveal-right,.reveal-scale,.stagger-children');
    if (!els.length) return;
    const io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('visible');
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });
    els.forEach(function (el) { io.observe(el); });
  }
  if ('IntersectionObserver' in window) {
    initReveal();
  } else {
    // Fallback: show all immediately
    document.querySelectorAll('.reveal,.reveal-left,.reveal-right,.reveal-scale,.stagger-children').forEach(function(el){el.classList.add('visible');});
  }

  /* ── Image Error Fallback ─────────────────────────── */
  document.querySelectorAll('img').forEach(function (img) {
    img.addEventListener('error', function () {
      if (!this.dataset.errored) {
        this.dataset.errored = '1';
        this.src = 'data:image/svg+xml;base64,' + btoa(
          '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="300"><rect width="400" height="300" fill="#d8f3dc"/><text x="200" y="155" text-anchor="middle" font-family="sans-serif" font-size="14" fill="#40916c">Ovijat Group</text></svg>'
        );
      }
    });
  });

})();

document.addEventListener("DOMContentLoaded", () => {
  const ticker = document.querySelector('.ticker-inner');
  const container = document.querySelector('.ticker-content');
  
  if (ticker && container) {
    // 1. Save the original news items
    const originalHTML = ticker.innerHTML;
    
    // 2. If the text is super short (e.g., 20 chars), 
    // keep duplicating it until it is wider than the user's screen.
    while (ticker.offsetWidth < container.offsetWidth) {
      ticker.innerHTML += originalHTML;
    }
    
    // 3. Now that it safely covers the screen, duplicate the ENTIRE chunk 
    // one last time. This is what makes the -50% CSS loop invisible.
    ticker.innerHTML += ticker.innerHTML;
    
    // 4. Set your exact TV scroll speed (pixels per second)
    const speed = 60; 
    
    // 5. Calculate the distance (exactly half of the massive new width)
    const distanceToTravel = ticker.offsetWidth / 2;
    
    // 6. Apply the dynamic duration (Distance / Speed = Time)
    const duration = distanceToTravel / speed;
    ticker.style.animationDuration = `${duration}s`;
  }
});
