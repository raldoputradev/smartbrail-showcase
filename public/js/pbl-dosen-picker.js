(function () {
    'use strict';

    function filterSelect(select, query) {
        if (!select) return;
        const q = (query || '').trim().toLowerCase();
        Array.from(select.options).forEach(function (opt) {
            if (!opt.value) {
                opt.hidden = false;
                return;
            }
            const hay = opt.getAttribute('data-search') || opt.textContent.toLowerCase();
            opt.hidden = q !== '' && hay.indexOf(q) === -1;
        });
    }

    function updateSelectedLabel(select, labelEl) {
        if (!labelEl) return;
        const opt = select.options[select.selectedIndex];
        if (opt && opt.value) {
            labelEl.textContent = 'Terpilih: ' + opt.textContent.trim();
            labelEl.classList.remove('is-empty');
        } else {
            labelEl.textContent = 'Belum memilih dosen';
            labelEl.classList.add('is-empty');
        }
    }

    function initPicker(root) {
        root.querySelectorAll('.pbl-dosen-search').forEach(function (input) {
            const targetId = input.getAttribute('data-target');
            const select = targetId ? document.getElementById(targetId) : null;
            const picker = input.closest('.pbl-dosen-picker');
            const labelEl = picker
                ? picker.querySelector('.pbl-dosen-selected')
                : null;

            input.addEventListener('input', function () {
                filterSelect(select, input.value);
            });

            if (select) {
                select.addEventListener('change', function () {
                    updateSelectedLabel(select, labelEl);
                });
                updateSelectedLabel(select, labelEl);
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.pbl-dosen-pickers').forEach(initPicker);
    });

    window.pblDosenPickerRefresh = function (container) {
        const root = container || document;
        root.querySelectorAll('.pbl-dosen-pickers').forEach(initPicker);
        root.querySelectorAll('.pbl-dosen-select').forEach(function (select) {
            const picker = select.closest('.pbl-dosen-picker');
            const labelEl = picker ? picker.querySelector('.pbl-dosen-selected') : null;
            updateSelectedLabel(select, labelEl);
        });
    };
})();
