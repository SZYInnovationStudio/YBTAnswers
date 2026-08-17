'use strict';

window.YBT = window.YBT || {};

YBT.toast = function (message, type) {
  const container = document.getElementById('toastContainer');
  if (!container) {
    return;
  }
  const toast = document.createElement('div');
  toast.className = 'toast' + (type && type !== 'success' ? ' toast--' + type : '');
  toast.setAttribute('role', 'status');
  toast.textContent = message;
  container.appendChild(toast);
  setTimeout(() => {
    toast.classList.add('is-leaving');
    setTimeout(() => toast.remove(), 220);
  }, 3200);
};

YBT.copyText = async function (text) {
  try {
    await navigator.clipboard.writeText(text);
    return true;
  } catch (err) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    let ok = false;
    try {
      ok = document.execCommand('copy');
    } catch (e) {
      ok = false;
    }
    textarea.remove();
    return ok;
  }
};

YBT.csrfToken = function () {
  const meta = document.querySelector('meta[name="csrf-token"]');
  return meta ? meta.content : '';
};

document.addEventListener('DOMContentLoaded', () => {
  initCopyButtons();
  initCodeHighlight();
  initAnswerPanel();
  initMobileSearchTrigger();
});

function initCopyButtons() {
  document.addEventListener('click', async (event) => {
    const btn = event.target.closest('.copy-btn');
    if (!btn) {
      return;
    }
    const targetSelector = btn.dataset.copyTarget;
    let text = '';
    if (targetSelector) {
      const target = document.querySelector(targetSelector);
      text = target ? target.innerText : '';
    } else {
      const block = btn.closest('.sample-block, .code-block, pre');
      if (block) {
        const code = block.querySelector('code');
        text = (code || block).innerText;
      }
    }
    if (!text) {
      return;
    }
    const ok = await YBT.copyText(text.replace(/\n$/, ''));
    if (ok) {
      const original = btn.textContent.trim();
      btn.textContent = '已复制';
      btn.classList.add('is-copied');
      setTimeout(() => {
        btn.textContent = original || '复制';
        btn.classList.remove('is-copied');
      }, 1600);
    } else {
      YBT.toast('复制失败，请手动选择复制', 'error');
    }
  });
}

function initCodeHighlight() {
  const blocks = document.querySelectorAll('.code-block code[data-lazy]');
  if (!blocks.length) {
    return;
  }
  const highlight = (code) => {
    if (code.dataset.highlighted) {
      return;
    }
    if (window.hljs) {
      window.hljs.highlightElement(code);
      code.dataset.highlighted = '1';
    }
  };
  if (!('IntersectionObserver' in window)) {
    blocks.forEach(highlight);
    return;
  }
  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        highlight(entry.target);
        observer.unobserve(entry.target);
      }
    });
  }, { rootMargin: '200px' });
  blocks.forEach((code) => observer.observe(code));
}

function initAnswerPanel() {
  const expandBtn = document.getElementById('answerExpandBtn');
  if (!expandBtn) {
    return;
  }
  expandBtn.addEventListener('click', () => {
    const body = document.getElementById('answerBody');
    if (!body) {
      return;
    }
    const expanded = body.classList.toggle('is-expanded');
    expandBtn.textContent = expanded ? '收起代码' : '展开完整代码';
    expandBtn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
  });
}

function initMobileSearchTrigger() {
  const trigger = document.getElementById('navSearchTrigger');
  const searchBox = document.getElementById('navSearch');
  const cancelBtn = document.getElementById('navSearchCancel');
  const input = document.getElementById('navSearchInput');
  if (!trigger || !searchBox) {
    return;
  }
  trigger.addEventListener('click', () => {
    searchBox.classList.add('is-mobile-open');
    if (input) {
      input.focus();
    }
  });
  if (cancelBtn) {
    cancelBtn.addEventListener('click', () => {
      searchBox.classList.remove('is-mobile-open');
      if (input) {
        input.value = '';
      }
      const suggest = document.getElementById('searchSuggest');
      if (suggest) {
        suggest.hidden = true;
      }
    });
  }
}
