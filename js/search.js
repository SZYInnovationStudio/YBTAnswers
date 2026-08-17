'use strict';

document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('navSearchForm');
  const input = document.getElementById('navSearchInput');
  const suggest = document.getElementById('searchSuggest');

  if (!form || !input || !suggest) {
    return;
  }

  const baseUrl = (document.querySelector('meta[name="app-base"]') || {}).content || '';
  let timer = null;
  let aborter = null;
  let activeIndex = -1;
  let items = [];

  const hide = () => {
    suggest.hidden = true;
    suggest.innerHTML = '';
    activeIndex = -1;
    items = [];
  };

  const render = (results, keyword) => {
    suggest.innerHTML = '';
    activeIndex = -1;
    items = [];
    if (!results.length) {
      const empty = document.createElement('div');
      empty.className = 'search-suggest__empty';
      empty.textContent = '未找到匹配的题目';
      suggest.appendChild(empty);
      suggest.hidden = false;
      return;
    }
    results.forEach((item) => {
      const link = document.createElement('a');
      link.className = 'search-suggest__item';
      link.href = baseUrl + '/problem.php?pid=' + encodeURIComponent(item.pid);
      link.setAttribute('role', 'option');

      const pid = document.createElement('span');
      pid.className = 'search-suggest__pid';
      pid.innerHTML = highlight(item.pid, keyword);

      const title = document.createElement('span');
      title.className = 'search-suggest__title';
      title.innerHTML = highlight(item.title, keyword);

      link.appendChild(pid);
      link.appendChild(title);
      suggest.appendChild(link);
      items.push(link);
    });
    suggest.hidden = false;
  };

  const highlight = (text, keyword) => {
    const escaped = escapeHtml(text);
    if (!keyword) {
      return escaped;
    }
    const pattern = new RegExp(escapeRegExp(escapeHtml(keyword)), 'gi');
    return escaped.replace(pattern, (m) => '<mark>' + m + '</mark>');
  };

  const escapeHtml = (str) => String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');

  const escapeRegExp = (str) => str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

  const search = (keyword) => {
    if (aborter) {
      aborter.abort();
    }
    aborter = new AbortController();
    fetch(baseUrl + '/api/search_suggest.php?q=' + encodeURIComponent(keyword), {
      signal: aborter.signal,
      headers: { 'X-Requested-With': 'fetch' },
    })
      .then((res) => res.json())
      .then((data) => {
        render(Array.isArray(data.items) ? data.items : [], keyword);
      })
      .catch((err) => {
        if (err.name !== 'AbortError') {
          hide();
        }
      });
  };

  input.addEventListener('input', () => {
    const value = input.value.trim();
    clearTimeout(timer);
    if (!value) {
      hide();
      return;
    }
    timer = setTimeout(() => search(value), 300);
  });

  input.addEventListener('keydown', (event) => {
    if (event.key === 'ArrowDown' && items.length) {
      event.preventDefault();
      activeIndex = (activeIndex + 1) % items.length;
      updateActive();
    } else if (event.key === 'ArrowUp' && items.length) {
      event.preventDefault();
      activeIndex = (activeIndex - 1 + items.length) % items.length;
      updateActive();
    } else if (event.key === 'Escape') {
      hide();
    }
  });

  const updateActive = () => {
    items.forEach((item, index) => {
      item.classList.toggle('search-suggest__item--active', index === activeIndex);
    });
  };

  form.addEventListener('submit', (event) => {
    const value = input.value.trim();
    if (/^\d{3,5}$/.test(value)) {
      event.preventDefault();
      window.location.href = baseUrl + '/problem.php?pid=' + encodeURIComponent(value);
    }
  });

  document.addEventListener('click', (event) => {
    if (!form.contains(event.target) && !suggest.contains(event.target)) {
      hide();
    }
  });

  input.addEventListener('focus', () => {
    if (input.value.trim() && items.length) {
      suggest.hidden = false;
    }
  });
});
