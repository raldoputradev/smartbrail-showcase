/**
 * Laporan Periodik — detail mahasiswa (modal).
 */
(function () {
    'use strict';

    var modal = null;
    var bodyEl = null;
    var titleEl = null;
    var detailUrlTemplate = '';

    function qs(sel, root) {
        return (root || document).querySelector(sel);
    }

    function getFilterParams() {
        var form = qs('.report-filter');
        if (!form) {
            return new URLSearchParams(window.location.search);
        }
        return new URLSearchParams(new FormData(form));
    }

    function buildDetailUrl(userId) {
        var base = detailUrlTemplate.replace('__USER__', String(userId));
        var params = getFilterParams();
        ['tanggal_mulai', 'tanggal_akhir', 'aktivitas'].forEach(function (key) {
            if (!params.get(key) && key === 'aktivitas') {
                params.set(key, 'semua');
            }
        });
        return base + '?' + params.toString();
    }

    function openModal() {
        if (!modal) return;
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        if (!modal) return;
        modal.classList.remove('show');
        document.body.style.overflow = '';
    }

    function setLoading(isLoading) {
        if (!bodyEl) return;
        bodyEl.setAttribute('aria-busy', isLoading ? 'true' : 'false');
        if (isLoading) {
            bodyEl.innerHTML = '<p style="text-align:center;color:var(--text-muted);padding:32px 16px;">Memuat detail mahasiswa…</p>';
        }
    }

    function loadStudentDetail(userId, nama) {
        if (!bodyEl || !userId) return;
        if (titleEl) {
            titleEl.textContent = nama ? 'Detail — ' + nama : 'Detail Mahasiswa';
        }
        openModal();
        setLoading(true);

        fetch(buildDetailUrl(userId), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
            credentials: 'same-origin',
        })
            .then(function (res) {
                if (!res.ok) {
                    throw new Error('HTTP ' + res.status);
                }
                return res.json();
            })
            .then(function (data) {
                if (!data.ok || !data.html) {
                    throw new Error('invalid payload');
                }
                bodyEl.innerHTML = data.html;
                bodyEl.setAttribute('aria-busy', 'false');
            })
            .catch(function () {
                bodyEl.innerHTML = '<p style="text-align:center;color:#dc2626;padding:32px 16px;">Gagal memuat detail. Coba lagi.</p>';
                bodyEl.setAttribute('aria-busy', 'false');
            });
    }

    function bindTriggers(root) {
        if (!root) return;
        root.addEventListener('click', function (event) {
            var trigger = event.target.closest('.js-periodic-student-open');
            if (!trigger || !root.contains(trigger)) {
                return;
            }
            event.preventDefault();
            loadStudentDetail(
                trigger.getAttribute('data-user-id'),
                trigger.getAttribute('data-nama') || ''
            );
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        modal = document.getElementById('periodicStudentModal');
        bodyEl = document.getElementById('periodicStudentModalBody');
        titleEl = document.getElementById('periodicStudentModalTitle');
        detailUrlTemplate = (document.getElementById('periodicStudentDetailConfig') || {}).dataset?.urlTemplate || '';

        if (!modal || !detailUrlTemplate) {
            return;
        }

        var closeBtn = document.getElementById('periodicStudentModalClose');
        if (closeBtn) {
            closeBtn.addEventListener('click', closeModal);
        }

        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeModal();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && modal.classList.contains('show')) {
                closeModal();
            }
        });

        bindTriggers(document.getElementById('periodic-recap-panel'));
    });
})();
