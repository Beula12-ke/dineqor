// Dineqor — motion layer for public pages: parallax hero, scroll reveals, 3D tilt, magnetic buttons.
// Purely cosmetic: if this file fails to load, the site works exactly as before.
(() => {
  if (matchMedia('(prefers-reduced-motion: reduce)').matches) return;   // respect the visitor's setting

  const root = document.documentElement;
  const fine = matchMedia('(hover:hover) and (pointer:fine)').matches;   // mouse / trackpad
  const $ = (s, c = document) => c.querySelector(s);
  const $$ = (s, c = document) => [...c.querySelectorAll(s)];
  const clamp = (n, a, b) => Math.min(b, Math.max(a, n));
  const hero = $('.home-hero');
  const heroImg = hero ? getComputedStyle(hero).backgroundImage : 'none';   // read BEFORE html.mo hides it
  root.classList.add('mo');

  /* ---------- scroll progress bar ---------- */
  const bar = document.createElement('div');
  bar.className = 'mo-progress';
  bar.setAttribute('aria-hidden', 'true');
  document.body.appendChild(bar);

  /* ---------- hero: parallax background + cursor glow ---------- */
  let heroBg = null, glow = null;
  if (hero) {
    const src = heroImg && heroImg !== 'none' ? heroImg : '';
    heroBg = document.createElement('div');
    heroBg.className = 'mo-hero-bg';
    heroBg.setAttribute('aria-hidden', 'true');
    if (src) heroBg.style.backgroundImage = src;
    glow = document.createElement('div');
    glow.className = 'mo-hero-glow';
    glow.setAttribute('aria-hidden', 'true');
    hero.prepend(glow);
    hero.prepend(heroBg);
  }

  // smooth pointer-follow (lerped so it feels weighty, like a car configurator)
  let tx = 0, ty = 0, cx = 0, cy = 0, heroVisible = true, loop = 0;
  const tick = () => {
    cx += (tx - cx) * 0.08;
    cy += (ty - cy) * 0.08;
    hero.style.setProperty('--mx', cx.toFixed(4));
    hero.style.setProperty('--my', cy.toFixed(4));
    if (Math.abs(tx - cx) > 0.001 || Math.abs(ty - cy) > 0.001) loop = requestAnimationFrame(tick); else loop = 0;
  };
  if (hero && fine) {
    hero.addEventListener('pointermove', e => {
      const r = hero.getBoundingClientRect();
      tx = clamp(((e.clientX - r.left) / r.width - 0.5) * 2, -1, 1);
      ty = clamp(((e.clientY - r.top) / r.height - 0.5) * 2, -1, 1);
      hero.style.setProperty('--gx', e.clientX - r.left + 'px');
      hero.style.setProperty('--gy', e.clientY - r.top + 'px');
      if (!loop && heroVisible) loop = requestAnimationFrame(tick);
    });
    hero.addEventListener('pointerleave', () => { tx = ty = 0; if (!loop) loop = requestAnimationFrame(tick); });
    new IntersectionObserver(([en]) => { heroVisible = en.isIntersecting; }).observe(hero);
  }

  /* ---------- scroll-linked values (one rAF-throttled handler) ---------- */
  const nav = $('.home-nav');
  const band = $('.partner-band');
  let ticking = false;
  const onScroll = () => {
    ticking = false;
    const y = window.scrollY, max = document.documentElement.scrollHeight - innerHeight;
    bar.style.setProperty('--p', max > 0 ? clamp(y / max, 0, 1).toFixed(4) : 0);
    if (nav) nav.classList.toggle('is-scrolled', y > 24);
    if (max > 0 && y >= max - 4) $$('[data-reveal]:not(.is-in)').forEach(el => { el.classList.add('is-in'); io.unobserve(el); });
    if (hero) {
      const h = hero.offsetHeight || 1;
      if (y < h * 1.2) {
        hero.style.setProperty('--sy', y.toFixed(1));
        hero.style.setProperty('--hp', clamp(y / h, 0, 1).toFixed(3));
      }
    }
    if (band) {
      const r = band.getBoundingClientRect();
      band.style.setProperty('--sp', clamp(1 - (r.top + r.height / 2) / (innerHeight + r.height / 2), 0, 1).toFixed(3));
    }
  };
  addEventListener('scroll', () => { if (!ticking) { ticking = true; requestAnimationFrame(onScroll); } }, { passive: true });
  addEventListener('resize', onScroll);

  /* ---------- scroll reveal ---------- */
  const io = new IntersectionObserver(entries => {
    entries.forEach(en => {
      if (!en.isIntersecting) return;
      en.target.classList.add('is-in');
      io.unobserve(en.target);
    });
  }, { threshold: 0.1 });

  const reveal = (el, i = 0, kind = '') => {
    if (el.hasAttribute('data-reveal')) return;
    el.setAttribute('data-reveal', kind);
    el.style.setProperty('--d', Math.min(i, 8));
    io.observe(el);
  };

  // static blocks
  $$('.section-heading, .how-section h2, .how-section .eyebrow, .results-caption, .chips, .about-story, .about-content > *, .content-page-hero .wrap > *, .content-page-body > *, .partner-inner > div, .partner-inner > a')
    .forEach((el, i) => reveal(el, i % 4));
  $$('.steps-grid').forEach(g => $$('.step-card', g).forEach((c, i) => reveal(c, i)));

  // restaurant cards are rendered by home.js, and re-rendered on every search/filter
  const grid = $('#grid');
  if (grid) {
    const sweep = () => $$('.rcard, .skel, .empty', grid).forEach((c, i) => reveal(c, i % 6));
    new MutationObserver(sweep).observe(grid, { childList: true });
    sweep();
  }

  onScroll();   // initial pass (after `io` exists)

  /* ---------- 3D tilt (delegated, so it also works for cards that are added later) ---------- */
  if (fine) {
    const SEL = '.rcard, .step-card, .about-story, .feature-card';
    let cur = null;
    const reset = el => {
      if (!el) return;
      el.classList.add('is-leaving');
      el.style.setProperty('--rx', '0deg');
      el.style.setProperty('--ry', '0deg');
      el.style.setProperty('--lift', '0px');
    };
    document.addEventListener('pointermove', e => {
      const el = e.target.closest ? e.target.closest(SEL) : null;
      if (el !== cur) { reset(cur); cur = el; }
      if (!el) return;
      el.classList.add('mo-tilt');
      el.classList.remove('is-leaving');
      const r = el.getBoundingClientRect();
      const px = (e.clientX - r.left) / r.width, py = (e.clientY - r.top) / r.height;
      const max = el.classList.contains('rcard') ? 7 : 9;
      el.style.setProperty('--ry', ((px - 0.5) * 2 * max).toFixed(2) + 'deg');
      el.style.setProperty('--rx', ((0.5 - py) * 2 * max).toFixed(2) + 'deg');
      el.style.setProperty('--gx', (px * 100).toFixed(1) + '%');
      el.style.setProperty('--gy', (py * 100).toFixed(1) + '%');
    }, { passive: true });
    document.addEventListener('pointerleave', () => { reset(cur); cur = null; });
    addEventListener('blur', () => { reset(cur); cur = null; });

    /* ---------- magnetic buttons ---------- */
    $$('.partner-button, .home-login, .content-page-hero .partner-button').forEach(btn => {
      const pull = 0.28;
      btn.addEventListener('pointermove', e => {
        const r = btn.getBoundingClientRect();
        const dx = e.clientX - (r.left + r.width / 2), dy = e.clientY - (r.top + r.height / 2);
        btn.style.transition = 'transform .12s ease-out, background-color .2s, color .2s';
        btn.style.transform = `translate(${(dx * pull).toFixed(1)}px, ${(dy * pull).toFixed(1)}px)`;
      });
      btn.addEventListener('pointerleave', () => {
        btn.style.transition = 'transform .6s cubic-bezier(.2,.8,.2,1), background-color .2s, color .2s';
        btn.style.transform = '';
      });
    });
  }
})();
