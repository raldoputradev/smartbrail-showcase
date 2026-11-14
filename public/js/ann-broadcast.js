(function () {
  var cfg = window.annBroadcastConfig || {};
  var modal = document.getElementById('annModal');
  var form = document.getElementById('annComposerForm');
  if (!modal || !form) return;

  var titleInput = document.getElementById('ann_judul');
  var isiInput = document.getElementById('ann_isi');
  var expiresInput = document.getElementById('ann_expires');
  var metaBox = document.getElementById('annComposerMeta');
  var secondaryBox = document.getElementById('annComposerSecondary');
  var publishLabel = document.getElementById('annPublishLabel');
  var methodInput = document.getElementById('annComposerMethod');
  var toggleForm = document.getElementById('annToggleForm');
  var destroyForm = document.getElementById('annDestroyForm');
  var toggleBtn = document.getElementById('annToggleBtn');
  var modalTitle = document.getElementById('annModalTitle');
  var charCount = document.getElementById('annCharCount');

  var previewCard = document.getElementById('annPreviewCard');
  var previewTitle = document.getElementById('annPreviewTitle');
  var previewBody = document.getElementById('annPreviewBody');
  var previewPrio = document.getElementById('annPreviewPrio');
  var previewTarget = document.getElementById('annPreviewTarget');
  var previewWhen = document.getElementById('annPreviewWhen');
  var previewAuthor = document.getElementById('annPreviewAuthor');
  var previewExpires = document.getElementById('annPreviewExpires');
  var previewExpiresVal = document.getElementById('annPreviewExpiresVal');

  function capitalize(s) {
    s = String(s || '');
    return s ? s.charAt(0).toUpperCase() + s.slice(1) : '';
  }

  function getRadioValue(name) {
    var el = form.querySelector('input[name="' + name + '"]:checked');
    return el ? el.value : '';
  }

  function setRadioValue(name, value) {
    form.querySelectorAll('input[name="' + name + '"]').forEach(function (r) {
      r.checked = r.value === value;
      var label = r.closest('.ann-seg__opt');
      if (label) label.classList.toggle('is-on', r.checked);
    });
  }

  function formatExpiresLabel(localVal) {
    if (!localVal) return '';
    var d = new Date(localVal);
    if (isNaN(d.getTime())) return localVal;
    try {
      return d.toLocaleString('id-ID', {
        day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
      });
    } catch (e) {
      return localVal;
    }
  }

  function syncPreview() {
    var judul = (titleInput && titleInput.value.trim()) || 'Judul pengumuman';
    var isi = (isiInput && isiInput.value.trim()) || 'Isi pengumuman…';
    var prio = getRadioValue('prioritas') || 'normal';
    var target = getRadioValue('target') || 'semua';

    if (previewTitle) previewTitle.textContent = judul;
    if (previewBody) previewBody.textContent = isi;
    if (previewPrio) previewPrio.textContent = capitalize(prio);
    if (previewTarget) previewTarget.textContent = capitalize(target);
    if (previewCard) previewCard.setAttribute('data-prio', prio);
    if (charCount && isiInput) {
      charCount.textContent = isiInput.value.length.toLocaleString('id-ID') + ' karakter';
    }
    if (previewExpires && previewExpiresVal) {
      var exp = expiresInput ? expiresInput.value : '';
      if (exp) {
        previewExpires.hidden = false;
        previewExpiresVal.textContent = formatExpiresLabel(exp);
      } else {
        previewExpires.hidden = true;
        previewExpiresVal.textContent = '';
      }
    }
  }

  function updateUrl(params) {
    var url = new URL(window.location.href);
    Object.keys(params).forEach(function (key) {
      if (params[key] === null || params[key] === undefined) {
        url.searchParams.delete(key);
      } else {
        url.searchParams.set(key, params[key]);
      }
    });
    window.history.replaceState({}, '', url.toString());
  }

  function openModal() {
    modal.hidden = false;
    modal.classList.add('is-open');
    document.body.style.overflow = 'hidden';
    syncPreview();
  }

  function closeModal() {
    modal.classList.remove('is-open');
    modal.hidden = true;
    document.body.style.overflow = '';
    updateUrl({ id: null, new: null });
  }

  function applyEdit(data) {
    if (!data) return;
    form.action = data.update_url;
    if (methodInput) {
      methodInput.disabled = false;
      methodInput.value = 'PUT';
    }
    if (titleInput) titleInput.value = data.judul || '';
    if (isiInput) isiInput.value = data.isi || '';
    if (expiresInput) expiresInput.value = data.expires_at || '';
    setRadioValue('target', data.target || 'semua');
    setRadioValue('prioritas', data.prioritas || 'normal');
    if (publishLabel) publishLabel.textContent = 'Publikasikan ulang';
    if (modalTitle) modalTitle.textContent = 'Edit pengumuman';
    if (previewWhen) previewWhen.textContent = data.created_label || '-';
    if (previewAuthor) previewAuthor.textContent = data.author || 'Admin';
    if (metaBox) {
      metaBox.innerHTML = '<span>Dibuat ' + (data.created_label || '-') + ' · ' + (data.author || 'Admin') + '</span>';
      metaBox.hidden = false;
    }
    if (secondaryBox) secondaryBox.hidden = false;
    if (toggleForm) toggleForm.action = data.toggle_url;
    if (destroyForm) destroyForm.action = data.destroy_url;
    if (toggleBtn) toggleBtn.textContent = data.is_active ? 'Nonaktifkan' : 'Aktifkan';
    updateUrl({ id: data.id, new: null });
    openModal();
    syncPreview();
  }

  function applyCreate() {
    form.action = cfg.storeUrl || form.action;
    if (methodInput) {
      methodInput.disabled = true;
      methodInput.value = 'PUT';
    }
    if (titleInput) titleInput.value = '';
    if (isiInput) isiInput.value = '';
    if (expiresInput) expiresInput.value = '';
    setRadioValue('target', 'semua');
    setRadioValue('prioritas', 'normal');
    if (publishLabel) publishLabel.textContent = 'Publikasikan';
    if (modalTitle) modalTitle.textContent = 'Pengumuman baru';
    if (previewWhen) {
      try {
        previewWhen.textContent = new Date().toLocaleString('id-ID', {
          day: '2-digit', month: 'short', year: 'numeric',
          hour: '2-digit', minute: '2-digit',
        });
      } catch (e) {
        previewWhen.textContent = 'Sekarang';
      }
    }
    if (previewAuthor) previewAuthor.textContent = cfg.authorFallback || 'Admin';
    if (metaBox) metaBox.hidden = true;
    if (secondaryBox) secondaryBox.hidden = true;
    updateUrl({ id: null, new: '1' });
    openModal();
    syncPreview();
    if (titleInput) setTimeout(function () { titleInput.focus(); }, 50);
  }

  form.querySelectorAll('.ann-seg').forEach(function (seg) {
    seg.addEventListener('change', function (e) {
      if (!e.target || e.target.type !== 'radio') return;
      seg.querySelectorAll('.ann-seg__opt').forEach(function (opt) {
        opt.classList.toggle('is-on', opt.querySelector('input') === e.target);
      });
      syncPreview();
    });
  });

  ['input', 'change'].forEach(function (evt) {
    if (titleInput) titleInput.addEventListener(evt, syncPreview);
    if (isiInput) isiInput.addEventListener(evt, syncPreview);
    if (expiresInput) expiresInput.addEventListener(evt, syncPreview);
  });

  document.querySelectorAll('.js-ann-edit').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      var id = btn.dataset.id;
      if (!id) return;
      btn.disabled = true;
      fetch(cfg.jsonBase.replace('__ID__', id), {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      })
        .then(function (r) { return r.json(); })
        .then(function (payload) {
          if (payload && payload.success) applyEdit(payload.data);
        })
        .catch(function () {
          window.location.href = window.location.pathname + '?id=' + encodeURIComponent(id);
        })
        .finally(function () { btn.disabled = false; });
    });
  });

  var openCreate = document.getElementById('annOpenCreate');
  if (openCreate) openCreate.addEventListener('click', function (e) {
    e.preventDefault();
    applyCreate();
  });

  var closeBtn = document.getElementById('annModalClose');
  var cancelBtn = document.getElementById('annModalCancel');
  if (closeBtn) closeBtn.addEventListener('click', closeModal);
  if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
  modal.addEventListener('click', function (e) {
    if (e.target === modal) closeModal();
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
  });

  if (cfg.initial) {
    applyEdit(cfg.initial);
  } else if (cfg.openOnLoad || cfg.createMode) {
    applyCreate();
  } else {
    syncPreview();
  }
})();
