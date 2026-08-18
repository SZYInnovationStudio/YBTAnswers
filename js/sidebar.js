'use strict';

document.addEventListener('DOMContentLoaded', () => {
  const sidebar = document.getElementById('sidebar');
  const backdrop = document.getElementById('sidebarBackdrop');
  const toggleBtn = document.getElementById('sidebarToggle');
  const closeBtn = document.getElementById('sidebarClose');

  if (!sidebar) {
    return;
  }

  const isMobile = () => window.matchMedia('(max-width: 768px)').matches;

  const setToggleState = (expanded) => {
    if (toggleBtn) {
      toggleBtn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    }
  };

  if (toggleBtn) {
    toggleBtn.addEventListener('click', () => {
      if (isMobile()) {
        const open = document.body.classList.toggle('sidebar-mobile-open');
        if (backdrop) {
          backdrop.hidden = !open;
          requestAnimationFrame(() => backdrop.classList.toggle('is-visible', open));
        }
        setToggleState(open);
      } else {
        const collapsed = document.body.classList.toggle('sidebar-collapsed');
        setToggleState(!collapsed);
        try {
          localStorage.setItem('ybt_sidebar_collapsed', collapsed ? '1' : '0');
        } catch (e) { /* ignore */ }
      }
    });
  }

  const closeMobile = () => {
    document.body.classList.remove('sidebar-mobile-open');
    if (backdrop) {
      backdrop.classList.remove('is-visible');
      setTimeout(() => { backdrop.hidden = true; }, 200);
    }
    setToggleState(false);
  };

  if (closeBtn) {
    closeBtn.addEventListener('click', closeMobile);
  }
  if (backdrop) {
    backdrop.addEventListener('click', closeMobile);
  }

  try {
    if (localStorage.getItem('ybt_sidebar_collapsed') === '1' && !isMobile()) {
      document.body.classList.add('sidebar-collapsed');
      setToggleState(false);
    }
  } catch (e) { /* ignore */ }

  sidebar.addEventListener('click', (event) => {
    const toggle = event.target.closest('.tree__toggle');
    if (toggle) {
      event.preventDefault();
      const part = toggle.closest('.tree__part');
      if (!part) {
        return;
      }
      const open = part.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      part.setAttribute('aria-expanded', open ? 'true' : 'false');
      return;
    }
    const subToggle = event.target.closest('.tree__subtoggle');
    if (subToggle) {
      event.preventDefault();
      const leaf = subToggle.closest('.tree__leaf');
      if (!leaf) {
        return;
      }
      const open = leaf.classList.toggle('is-open');
      subToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      leaf.setAttribute('aria-expanded', open ? 'true' : 'false');
      return;
    }
    const chapterToggle = event.target.closest('.tree__chapter-toggle');
    if (chapterToggle) {
      event.preventDefault();
      const leaf = chapterToggle.closest('.tree__leaf');
      if (!leaf) {
        return;
      }
      const open = leaf.classList.toggle('is-open');
      chapterToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      leaf.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
  });

  const active = sidebar.querySelector('.tree__problem--active, .tree__chapter--active');
  if (active) {
    let node = active.closest('.tree__part');
    if (node) {
      node.classList.add('is-open');
      const btn = node.querySelector('.tree__toggle');
      if (btn) {
        btn.setAttribute('aria-expanded', 'true');
      }
    }
    const chapterLeaf = active.closest('.tree__leaf');
    if (chapterLeaf && active.classList.contains('tree__problem--active')) {
      chapterLeaf.classList.add('is-open');
    }
    setTimeout(() => {
      active.scrollIntoView({ block: 'center' });
    }, 150);
  }
});
