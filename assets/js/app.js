/**
 * Mitra Business Suite — Application Scripts
 * @author    d991d
 * @copyright 2024 d991d. All rights reserved.
 */

/* ── TOAST NOTIFICATIONS ─────────────────────────────── */
(function(){
  const COLORS = {
    success: { border:'rgba(34,197,94,0.3)',  icon:'✓', fg:'#4ade80' },
    error:   { border:'rgba(239,68,68,0.3)',  icon:'✕', fg:'#f87171' },
    warning: { border:'rgba(245,158,11,0.3)', icon:'!', fg:'#fbbf24' },
    info:    { border:'rgba(56,189,248,0.3)', icon:'i', fg:'#38bdf8' },
  };

  function createHost() {
    const el = document.createElement('div');
    el.id = 'toast-host';
    Object.assign(el.style, {
      position:'fixed', top:'18px', right:'18px', zIndex:'9999',
      display:'flex', flexDirection:'column', gap:'8px',
      pointerEvents:'none',
    });
    document.body.appendChild(el);
    return el;
  }

  window.toast = function(message, type = 'info', duration = 4000) {
    const host = document.getElementById('toast-host') || createHost();
    const c    = COLORS[type] || COLORS.info;

    const el = document.createElement('div');
    Object.assign(el.style, {
      background:     'rgba(17,17,20,0.92)',
      backdropFilter: 'blur(16px)',
      border:         '1px solid ' + c.border,
      borderRadius:   '10px',
      padding:        '11px 14px',
      display:        'flex',
      alignItems:     'center',
      gap:            '10px',
      minWidth:       '240px',
      maxWidth:       '340px',
      fontSize:       '0.82rem',
      color:          '#f1f1f3',
      pointerEvents:  'auto',
      cursor:         'pointer',
      transform:      'translateX(20px)',
      opacity:        '0',
      transition:     'transform 0.28s cubic-bezier(0.16,1,0.3,1), opacity 0.28s ease',
      boxShadow:      '0 8px 32px rgba(0,0,0,0.5)',
    });

    const icon = document.createElement('span');
    Object.assign(icon.style, {
      width:'18px', height:'18px', borderRadius:'50%',
      background: c.border,
      color: c.fg,
      fontSize:'0.7rem', fontWeight:'700',
      display:'flex', alignItems:'center', justifyContent:'center',
      flexShrink:'0',
    });
    icon.textContent = c.icon;

    const msg = document.createElement('span');
    msg.textContent = message;
    msg.style.flex = '1';

    const close = document.createElement('span');
    close.textContent = '×';
    Object.assign(close.style, { color:'#4f4f5a', fontSize:'1rem', marginLeft:'4px', lineHeight:'1' });

    el.appendChild(icon);
    el.appendChild(msg);
    el.appendChild(close);
    host.appendChild(el);

    // Animate in
    requestAnimationFrame(() => {
      el.style.transform = 'translateX(0)';
      el.style.opacity   = '1';
    });

    const dismiss = () => {
      el.style.transform = 'translateX(16px)';
      el.style.opacity   = '0';
      setTimeout(() => el.remove(), 300);
    };

    el.addEventListener('click', dismiss);
    const timer = setTimeout(dismiss, duration);
    el.addEventListener('mouseenter', () => clearTimeout(timer));
    el.addEventListener('mouseleave', () => setTimeout(dismiss, 1200));
  };

  // Auto-show flash alerts as toasts and remove the inline ones
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.alert').forEach(a => {
      const type = ['success','error','warning','info'].find(t => a.classList.contains('alert-' + t)) || 'info';
      toast(a.textContent.trim(), type);
      a.remove();
    });
  });
})();

/* ── MOBILE SIDEBAR ──────────────────────────────────── */
(function(){
  document.addEventListener('DOMContentLoaded', () => {
    const sidebar  = document.querySelector('.sidebar');
    const overlay  = document.createElement('div');
    overlay.className = 'sidebar-overlay';
    document.body.appendChild(overlay);

    // Inject hamburger into topbar
    const topbar = document.querySelector('.topbar');
    if (topbar && sidebar) {
      const btn = document.createElement('button');
      btn.className   = 'sidebar-toggle';
      btn.innerHTML   = '&#9776;';
      btn.title       = 'Toggle menu';
      btn.setAttribute('aria-label', 'Toggle sidebar');
      topbar.prepend(btn);

      const open  = () => { sidebar.classList.add('open');  overlay.classList.add('show'); };
      const close = () => { sidebar.classList.remove('open'); overlay.classList.remove('show'); };

      btn.addEventListener('click', () => sidebar.classList.contains('open') ? close() : open());
      overlay.addEventListener('click', close);

      // Close on nav link tap (mobile)
      sidebar.querySelectorAll('.nav-item').forEach(l => l.addEventListener('click', () => {
        if (window.innerWidth <= 700) close();
      }));
    }
  });
})();

/* ── DROPDOWNS ───────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-dropdown]').forEach(trigger => {
    trigger.addEventListener('click', e => {
      e.stopPropagation();
      const menu = document.getElementById(trigger.dataset.dropdown);
      if (!menu) return;
      const open = menu.classList.contains('show');
      document.querySelectorAll('.dropdown-menu.show').forEach(m => m.classList.remove('show'));
      if (!open) menu.classList.add('show');
    });
  });
  document.addEventListener('click', () => {
    document.querySelectorAll('.dropdown-menu.show').forEach(m => m.classList.remove('show'));
  });
});

/* ── TABS ────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-tabs]').forEach(group => {
    group.querySelectorAll('.tab-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        group.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        group.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        btn.classList.add('active');
        const target = document.getElementById(btn.dataset.tab);
        if (target) target.classList.add('active');
      });
    });
  });
});

/* ── CONFIRM DIALOGS ─────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', e => {
      if (!confirm(el.dataset.confirm || 'Are you sure?')) e.preventDefault();
    });
  });
});

/* ── SMART SELECT — show current value nicely ────────── */
document.addEventListener('DOMContentLoaded', () => {
  // Keyboard shortcut: / to focus search
  document.addEventListener('keydown', e => {
    if (e.key === '/' && document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
      e.preventDefault();
      const search = document.querySelector('.topbar-search');
      if (search) search.focus();
    }
    // Escape to blur search
    if (e.key === 'Escape') {
      const search = document.querySelector('.topbar-search');
      if (search && document.activeElement === search) search.blur();
    }
  });
});

/* ── CHAR COUNTER ────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('textarea[data-max]').forEach(ta => {
    const max     = parseInt(ta.dataset.max);
    const counter = document.createElement('div');
    counter.className = 'text-sm text-muted';
    counter.style.marginTop = '3px';
    ta.parentNode.insertBefore(counter, ta.nextSibling);
    const update = () => {
      const n = ta.value.length;
      counter.textContent = n + ' / ' + max;
      counter.style.color = n > max ? 'var(--err)' : 'var(--t3)';
    };
    ta.addEventListener('input', update);
    update();
  });
});

/* ── RIPPLE EFFECT ON BUTTONS ────────────────────────── */
document.addEventListener('click', e => {
  const btn = e.target.closest('.btn');
  if (!btn || btn.disabled) return;

  const rect   = btn.getBoundingClientRect();
  const ripple = document.createElement('span');
  const size   = Math.max(rect.width, rect.height);
  const x      = e.clientX - rect.left - size / 2;
  const y      = e.clientY - rect.top  - size / 2;

  Object.assign(ripple.style, {
    position:     'absolute',
    width:        size + 'px',
    height:       size + 'px',
    left:         x + 'px',
    top:          y + 'px',
    borderRadius: '50%',
    background:   'rgba(255,255,255,0.15)',
    transform:    'scale(0)',
    animation:    'ripple 0.5s ease-out',
    pointerEvents:'none',
  });

  if (!document.getElementById('ripple-style')) {
    const style = document.createElement('style');
    style.id = 'ripple-style';
    style.textContent = '@keyframes ripple{to{transform:scale(2.5);opacity:0}}';
    document.head.appendChild(style);
  }

  const prevOverflow = btn.style.overflow;
  btn.style.overflow = 'hidden';
  btn.style.position = btn.style.position || 'relative';
  btn.appendChild(ripple);

  ripple.addEventListener('animationend', () => {
    ripple.remove();
    btn.style.overflow = prevOverflow;
  });
});

/* ── TABLE ROW CLICK ─────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('tr[data-href]').forEach(row => {
    row.style.cursor = 'pointer';
    row.addEventListener('click', () => window.location = row.dataset.href);
  });
});

/* ── SEARCH ICON SVG ─────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.search-icon').forEach(el => {
    el.innerHTML = '<svg width="13" height="13" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="9" cy="9" r="6"/><line x1="13.5" y1="13.5" x2="18" y2="18"/></svg>';
  });
});
