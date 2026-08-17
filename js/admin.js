'use strict';

document.addEventListener('DOMContentLoaded', () => {
  initConfirmForms();
  initModals();
  initChapterManager();
  initRegenerate();
  initFetchPage();
  initApiTest();
});

const adminBase = (document.querySelector('meta[name="admin-base"]') || {}).content || '';

function ajaxPost(url, formData) {
  return fetch(url, {
    method: 'POST',
    body: formData,
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
  }).then((res) => res.json());
}

function setButtonLoading(btn, loading) {
  if (!btn) {
    return;
  }
  if (loading) {
    btn.dataset.originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner" aria-hidden="true"></span> 处理中…';
  } else if (btn.dataset.originalHtml) {
    btn.disabled = false;
    btn.innerHTML = btn.dataset.originalHtml;
    delete btn.dataset.originalHtml;
  }
}

function initConfirmForms() {
  document.addEventListener('submit', (event) => {
    const form = event.target.closest('form[data-confirm]');
    if (!form) {
      return;
    }
    if (!window.confirm(form.dataset.confirm)) {
      event.preventDefault();
    }
  });

  document.addEventListener('click', (event) => {
    const btn = event.target.closest('button[data-confirm-action]');
    if (!btn) {
      return;
    }
    if (!window.confirm(btn.dataset.confirmAction)) {
      event.preventDefault();
      event.stopPropagation();
    }
  });
}

function initModals() {
  document.addEventListener('click', (event) => {
    const opener = event.target.closest('[data-modal-open]');
    if (opener) {
      event.preventDefault();
      const modal = document.getElementById(opener.dataset.modalOpen);
      openModal(modal);
      return;
    }
    const closer = event.target.closest('[data-modal-close]');
    if (closer) {
      event.preventDefault();
      const backdrop = closer.closest('.modal-backdrop');
      closeModal(backdrop);
      return;
    }
    if (event.target.classList && event.target.classList.contains('modal-backdrop')) {
      closeModal(event.target);
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      const open = document.querySelector('.modal-backdrop.is-visible');
      if (open) {
        closeModal(open);
      }
    }
  });
}

function openModal(backdrop) {
  if (!backdrop) {
    return;
  }
  backdrop.hidden = false;
  requestAnimationFrame(() => backdrop.classList.add('is-visible'));
  const focusable = backdrop.querySelector('input, select, textarea, button');
  if (focusable) {
    setTimeout(() => focusable.focus(), 120);
  }
}

function closeModal(backdrop) {
  if (!backdrop) {
    return;
  }
  backdrop.classList.remove('is-visible');
  setTimeout(() => { backdrop.hidden = true; }, 200);
}

/* ===== 章节管理 ===== */
function initChapterManager() {
  const modal = document.getElementById('chapterModal');
  if (!modal) {
    return;
  }
  const form = modal.querySelector('form');
  const idInput = form.querySelector('[name="id"]');
  const title = modal.querySelector('.modal__title');

  document.addEventListener('click', (event) => {
    const editBtn = event.target.closest('[data-edit-chapter]');
    if (!editBtn) {
      return;
    }
    event.preventDefault();
    const data = JSON.parse(editBtn.dataset.editChapter);
    idInput.value = data.id;
    form.querySelector('[name="name"]').value = data.name;
    form.querySelector('[name="subpart_id"]').value = data.subpart_id;
    if (title) {
      title.textContent = '编辑章节';
    }
    openModal(modal);
  });

  const addBtn = document.querySelector('[data-modal-open="chapterModal"]');
  if (addBtn) {
    addBtn.addEventListener('click', () => {
      idInput.value = '';
      form.reset();
      if (title) {
        title.textContent = '添加章节';
      }
    });
  }

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    const submitBtn = form.querySelector('[type="submit"]');
    const formData = new FormData(form);
    const action = idInput.value ? 'chapter_update' : 'chapter_create';
    formData.append('action', action);
    setButtonLoading(submitBtn, true);
    ajaxPost(adminBase + '/ajax.php', formData)
      .then((data) => {
        setButtonLoading(submitBtn, false);
        if (data.ok) {
          YBT.toast(data.message || '保存成功');
          setTimeout(() => window.location.reload(), 500);
        } else {
          YBT.toast(data.message || '操作失败', 'error');
        }
      })
      .catch(() => {
        setButtonLoading(submitBtn, false);
        YBT.toast('网络错误，请重试', 'error');
      });
  });

  document.addEventListener('click', (event) => {
    const moveBtn = event.target.closest('[data-move-chapter]');
    if (!moveBtn) {
      return;
    }
    const formData = new FormData();
    formData.append('action', 'chapter_move');
    formData.append('id', moveBtn.dataset.moveChapter);
    formData.append('dir', moveBtn.dataset.dir);
    formData.append('csrf_token', YBT.csrfToken());
    setButtonLoading(moveBtn, true);
    ajaxPost(adminBase + '/ajax.php', formData)
      .then((data) => {
        if (data.ok) {
          window.location.reload();
        } else {
          setButtonLoading(moveBtn, false);
          YBT.toast(data.message || '移动失败', 'error');
        }
      })
      .catch(() => {
        setButtonLoading(moveBtn, false);
        YBT.toast('网络错误，请重试', 'error');
      });
  });
}

/* ===== 重新生成答案 ===== */
function initRegenerate() {
  document.addEventListener('click', (event) => {
    const btn = event.target.closest('[data-regenerate]');
    if (!btn) {
      return;
    }
    event.preventDefault();
    const isManual = btn.dataset.manual === '1';
    const message = isManual
      ? '该答案已被人工修改，重新生成将覆盖手动修改的内容。确定继续吗？'
      : '确定要重新生成该题目的 AI 答案吗？';
    if (!window.confirm(message)) {
      return;
    }
    const formData = new FormData();
    formData.append('action', 'problem_generate');
    formData.append('id', btn.dataset.regenerate);
    formData.append('csrf_token', YBT.csrfToken());
    setButtonLoading(btn, true);
    ajaxPost(adminBase + '/ajax.php', formData)
      .then((data) => {
        setButtonLoading(btn, false);
        if (data.ok) {
          YBT.toast('答案已重新生成');
          setTimeout(() => window.location.reload(), 600);
        } else {
          YBT.toast(data.message || '生成失败', 'error');
        }
      })
      .catch(() => {
        setButtonLoading(btn, false);
        YBT.toast('网络错误，请重试', 'error');
      });
  });
}

/* ===== 抓取页 ===== */
function initFetchPage() {
  const singleForm = document.getElementById('fetchSingleForm');
  const batchForm = document.getElementById('fetchBatchForm');
  if (!singleForm && !batchForm) {
    return;
  }

  const logBox = document.getElementById('fetchLog');
  const progressBar = document.getElementById('fetchProgressBar');
  const progressText = document.getElementById('fetchProgressText');
  let batchRunning = false;
  let batchStop = false;

  const log = (message, type) => {
    if (!logBox) {
      return;
    }
    const line = document.createElement('div');
    line.className = 'fetch-log__line' + (type ? ' fetch-log__line--' + type : '');
    line.textContent = '[' + new Date().toLocaleTimeString('zh-CN', { hour12: false }) + '] ' + message;
    logBox.appendChild(line);
    logBox.scrollTop = logBox.scrollHeight;
  };

  const setProgress = (current, total) => {
    if (progressBar && total > 0) {
      progressBar.style.width = Math.round((current / total) * 100) + '%';
    }
    if (progressText) {
      progressText.textContent = current + ' / ' + total;
    }
  };

  const fetchOne = (target, chapterId, generate) => {
    const formData = new FormData();
    formData.append('action', 'fetch_one');
    formData.append('target', target);
    formData.append('chapter_id', chapterId);
    formData.append('generate', generate ? '1' : '0');
    formData.append('csrf_token', YBT.csrfToken());
    return ajaxPost(adminBase + '/ajax.php', formData);
  };

  if (singleForm) {
    singleForm.addEventListener('submit', (event) => {
      event.preventDefault();
      const btn = singleForm.querySelector('[type="submit"]');
      const target = singleForm.querySelector('[name="target"]').value.trim();
      const chapterId = singleForm.querySelector('[name="chapter_id"]').value;
      const generate = singleForm.querySelector('[name="generate"]').checked;
      if (!target) {
        YBT.toast('请输入题目链接或题号', 'warning');
        return;
      }
      setButtonLoading(btn, true);
      log('开始抓取：' + target, 'info');
      fetchOne(target, chapterId, generate)
        .then((data) => {
          setButtonLoading(btn, false);
          if (data.ok) {
            log('抓取成功：' + data.title + '（' + data.pid + '）' + (data.generated ? '，答案已生成' : ''), 'ok');
            if (!data.generated && data.message) {
              log(data.message, 'error');
            }
            YBT.toast(data.generated ? '抓取成功' : (data.message || '抓取成功'), data.generated ? 'success' : 'warning');
          } else {
            log('抓取失败：' + data.message, 'error');
            YBT.toast(data.message || '抓取失败', 'error');
          }
        })
        .catch(() => {
          setButtonLoading(btn, false);
          log('网络错误', 'error');
        });
    });
  }

  if (batchForm) {
    const modeSelect = batchForm.querySelector('[name="mode"]');
    const rangeRow = document.getElementById('batchRangeRow');
    const listRow = document.getElementById('batchListRow');

    const syncMode = () => {
      const isRange = modeSelect.value === 'range';
      if (rangeRow) {
        rangeRow.hidden = !isRange;
      }
      if (listRow) {
        listRow.hidden = isRange;
      }
    };
    if (modeSelect) {
      modeSelect.addEventListener('change', syncMode);
      syncMode();
    }

    batchForm.addEventListener('submit', (event) => {
      event.preventDefault();
      if (batchRunning) {
        batchStop = true;
        log('正在停止…', 'info');
        return;
      }

      const chapterId = batchForm.querySelector('[name="chapter_id"]').value;
      const generate = batchForm.querySelector('[name="generate"]').checked;
      let pids = [];

      if (modeSelect.value === 'range') {
        const from = parseInt(batchForm.querySelector('[name="pid_from"]').value, 10);
        const to = parseInt(batchForm.querySelector('[name="pid_to"]').value, 10);
        if (!from || !to || from > to || to - from > 500) {
          YBT.toast('请输入有效的题号范围（最多 500 题）', 'warning');
          return;
        }
        for (let i = from; i <= to; i++) {
          pids.push(String(i));
        }
      } else {
        const raw = batchForm.querySelector('[name="pid_list"]').value;
        pids = raw.split(/[,，\s]+/).map((s) => s.trim()).filter((s) => /^\d{3,5}$/.test(s));
        if (!pids.length) {
          YBT.toast('请输入有效的题号列表', 'warning');
          return;
        }
      }

      batchRunning = true;
      batchStop = false;
      const btn = batchForm.querySelector('[type="submit"]');
      btn.textContent = '停止抓取';
      log('批量抓取开始，共 ' + pids.length + ' 题', 'info');
      setProgress(0, pids.length);

      let done = 0;
      let okCount = 0;

      const next = () => {
        if (batchStop || done >= pids.length) {
          batchRunning = false;
          btn.textContent = '开始批量抓取';
          log('批量抓取结束：成功 ' + okCount + ' / ' + done, done === okCount ? 'ok' : 'info');
          if (progressBar) {
            progressBar.classList.toggle('progress__bar--error', okCount < done);
          }
          return;
        }
        const pid = pids[done];
        fetchOne(pid, chapterId, generate)
          .then((data) => {
            done++;
            if (data.ok) {
              okCount++;
              log('[' + pid + '] 成功：' + data.title + (data.generated ? '（答案已生成）' : ''), 'ok');
            } else {
              log('[' + pid + '] 失败：' + data.message, 'error');
            }
            setProgress(done, pids.length);
            setTimeout(next, 400);
          })
          .catch(() => {
            done++;
            log('[' + pid + '] 网络错误', 'error');
            setProgress(done, pids.length);
            setTimeout(next, 800);
          });
      };
      next();
    });
  }
}

/* ===== API 测试 ===== */
function initApiTest() {
  const btn = document.getElementById('testApiBtn');
  if (!btn) {
    return;
  }
  btn.addEventListener('click', () => {
    const formData = new FormData();
    formData.append('action', 'test_api');
    formData.append('csrf_token', YBT.csrfToken());
    setButtonLoading(btn, true);
    ajaxPost(adminBase + '/ajax.php', formData)
      .then((data) => {
        setButtonLoading(btn, false);
        YBT.toast(data.message || (data.ok ? '连接成功' : '连接失败'), data.ok ? 'success' : 'error');
      })
      .catch(() => {
        setButtonLoading(btn, false);
        YBT.toast('网络错误，请重试', 'error');
      });
  });
}
