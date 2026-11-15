{{-- Toggle Web Push — aktifkan notifikasi browser (semua role). --}}
<div class="card" id="sb-push-card" style="display:none;">
    <div class="card-body" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
        <div>
            <div style="font-weight:700;" id="sb-push-title">Notifikasi browser</div>
            <div style="font-size:13px;color:var(--text-muted,#64748b);" id="sb-push-desc">
                Terima pemberitahuan langsung di perangkat ini — walau SmartBrail sedang tidak dibuka.
            </div>
        </div>
        <button type="button" id="sb-push-btn" class="sp-btn sp-btn--primary sp-btn--sm"
                style="padding:8px 18px;border-radius:8px;font-weight:600;cursor:pointer;border:0;">
            Aktifkan
        </button>
    </div>
</div>

<script src="/js/webpush.js"></script>
<script>
(function () {
    const card = document.getElementById('sb-push-card');
    const btn = document.getElementById('sb-push-btn');
    const desc = document.getElementById('sb-push-desc');
    if (!window.SbPush || !SbPush.supported()) return; // browser tak mendukung → sembunyikan

    card.style.display = '';
    let sub = null;

    function render() {
        const perm = Notification.permission;
        if (perm === 'denied') {
            btn.disabled = true;
            btn.textContent = 'Diblokir browser';
            desc.textContent = 'Anda menolak izin notifikasi. Aktifkan lewat ikon gembok/pengaturan situs di browser.';
            return;
        }
        btn.disabled = false;
        btn.textContent = sub ? 'Nonaktifkan' : 'Aktifkan';
        desc.textContent = sub
            ? 'Aktif — perangkat ini menerima notifikasi pengajuan ruangan, pengumuman, dan balasan laporan.'
            : 'Terima pemberitahuan langsung di perangkat ini — walau SmartBrail sedang tidak dibuka.';
    }

    SbPush.subscription().then(function (s) { sub = s; render(); });

    btn.addEventListener('click', async function () {
        btn.disabled = true;
        try {
            if (sub) {
                await SbPush.disable();
                sub = null;
            } else {
                const r = await SbPush.enable();
                if (r.ok) {
                    sub = await SbPush.subscription();
                } else if (r.reason === 'unsupported') {
                    desc.textContent = 'Browser ini tidak mendukung notifikasi push.';
                }
            }
        } finally {
            btn.disabled = false;
            render();
        }
    });
})();
</script>
