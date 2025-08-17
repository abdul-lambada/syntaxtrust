// Smooth scroll for internal anchors
(function() {
  const links = document.querySelectorAll('a[href^="#"]');
  links.forEach(link => {
    link.addEventListener('click', (e) => {
      const href = link.getAttribute('href');
      if (href && href.length > 1) {
        const target = document.querySelector(href);
        if (target) {
          e.preventDefault();
          target.scrollIntoView({ behavior: 'smooth', block: 'start' });
          history.pushState(null, '', href);
        }
      }
    });
  });
})();

// Back to top visibility
(function() {
  const btn = document.getElementById('toTop');
  if (!btn) return;
  const toggle = () => {
    const y = window.scrollY || document.documentElement.scrollTop;
    if (y > 400) btn.classList.remove('hidden'); else btn.classList.add('hidden');
  };
  window.addEventListener('scroll', toggle);
  toggle();
  btn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
})();

// Theme initialization and toggle
(function() {
  const root = document.documentElement;
  const apply = (mode) => {
    const dark = mode === 'dark';
    root.classList.toggle('dark', dark);
    try { localStorage.setItem('theme', mode); } catch(e) {}
  };

  // Initialize: respect saved or system
  try {
    const saved = localStorage.getItem('theme');
    if (!saved) {
      const prefersDark = window.matchMedia('(prefers-color-scheme: dark)');
      root.classList.toggle('dark', prefersDark.matches);
      prefersDark.addEventListener('change', (e) => {
        const current = localStorage.getItem('theme');
        if (!current) root.classList.toggle('dark', e.matches);
      });
    }
  } catch (e) {}

  const toggle = () => {
    const isDark = root.classList.contains('dark');
    apply(isDark ? 'light' : 'dark');
  };

  const btn = document.getElementById('themeToggle');
  const btnMobile = document.getElementById('themeToggleMobile');
  if (btn) btn.addEventListener('click', toggle);
  if (btnMobile) btnMobile.addEventListener('click', toggle);
})();

// Reveal on scroll for elements with data-reveal
(function() {
  const els = Array.from(document.querySelectorAll('[data-reveal]'));
  if (!els.length || !('IntersectionObserver' in window)) return;
  els.forEach(el => {
    el.style.transition = 'opacity .6s ease, transform .6s ease';
    el.style.opacity = '0';
    const type = el.getAttribute('data-reveal') || 'up';
    const map = { up: 'translateY(20px)', down: 'translateY(-20px)', left: 'translateX(20px)', right: 'translateX(-20px)' };
    el.style.transform = map[type] || 'translateY(20px)';
  });

  const io = new IntersectionObserver((entries, obs) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const el = entry.target;
        el.style.opacity = '1';
        el.style.transform = 'none';
        obs.unobserve(el);
      }
    });
  }, { threshold: 0.2 });

  els.forEach(el => io.observe(el));
})();

// Lazy-load images that don't specify loading attribute
(function(){
  const imgs = document.querySelectorAll('img:not([loading])');
  imgs.forEach(img => { img.setAttribute('loading', 'lazy'); });
})();

// Secure external target=_blank links
(function(){
  const links = document.querySelectorAll('a[target="_blank"]:not([rel])');
  links.forEach(a => a.setAttribute('rel', 'noopener'));
})();

// Prevent double submission on forms and add minimal UX feedback
(function(){
  document.addEventListener('submit', function(e){
    const form = e.target;
    if (!(form instanceof HTMLFormElement)) return;
    const btn = form.querySelector('button[type="submit"], input[type="submit"]');
    if (btn && !btn.disabled) {
      btn.disabled = true;
      btn.dataset.originalText = btn.innerHTML || btn.value || '';
      if (btn.tagName === 'BUTTON') btn.innerHTML = '<span class="inline-flex items-center gap-2"><svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v3M12 18v3M4.22 4.22l2.12 2.12M17.66 17.66l2.12 2.12M3 12h3M18 12h3M4.22 19.78l2.12-2.12M17.66 6.34l2.12-2.12"/></svg><span>Mengirim...</span></span>';
    }
    // Safety: re-enable after 15s if not navigated
    setTimeout(() => {
      if (btn && btn.disabled) {
        btn.disabled = false;
        if (btn.tagName === 'BUTTON' && btn.dataset.originalText !== undefined) btn.innerHTML = btn.dataset.originalText;
      }
    }, 15000);
  }, true);
})();

// Simple toast notifications using Tailwind classes
(function(){
  function showToast(message, type='info'){
    const wrap = document.getElementById('toast-wrap') || (function(){
      const w = document.createElement('div');
      w.id = 'toast-wrap';
      w.style.position = 'fixed';
      w.style.top = '1rem';
      w.style.right = '1rem';
      w.style.zIndex = '9999';
      w.style.display = 'flex';
      w.style.flexDirection = 'column';
      w.style.gap = '.5rem';
      document.body.appendChild(w);
      return w;
    })();
    const colors = {
      success: 'bg-emerald-600',
      error: 'bg-red-600',
      info: 'bg-slate-900'
    };
    const div = document.createElement('div');
    div.className = `${colors[type]||colors.info} text-white px-4 py-2 rounded-lg shadow-soft`;
    div.textContent = message;
    wrap.appendChild(div);
    setTimeout(()=>{ div.remove(); }, 4000);
  }

  // Trigger from URL params (e.g., ?sent=1 or ?error=Message)
  try {
    const url = new URL(window.location.href);
    const sent = url.searchParams.get('sent');
    const err = url.searchParams.get('error');
    if (sent === '1') showToast('Berhasil: pesan Anda telah terkirim.', 'success');
    if (err) showToast(err, 'error');
  } catch(e) {}

  // Expose for manual use if needed
  window.SyntaxToast = { show: showToast };
})();
