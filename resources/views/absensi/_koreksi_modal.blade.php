<div class="abs-modal-overlay" id="absKoreksiModal" aria-hidden="true">
    <div class="abs-modal" role="dialog">
        <div class="abs-modal-h">
            <h3 id="absKoreksiTitle">Koreksi absensi</h3>
            <button type="button" class="btn-absensi" id="absKoreksiClose" aria-label="Tutup">✕</button>
        </div>
        <form method="POST" id="absKoreksiForm" class="abs-modal-b">
            @csrf
            <div id="absKoreksiMeta" style="font-size:13px;color:var(--text-muted);line-height:1.5;"></div>
            <div>
                <label for="absKoreksiStatus">Status baru</label>
                <select name="status" id="absKoreksiStatus">
                    <option value="lengkap">Hadir lengkap (masuk + pulang)</option>
                    <option value="tidak_lengkap">Tidak lengkap</option>
                    <option value="masuk">Hanya masuk</option>
                    <option value="alfa">Alfa</option>
                    <option value="belum">Belum / reset</option>
                </select>
            </div>
            <div>
                <label for="absKoreksiCatatan">Catatan</label>
                <textarea name="catatan_koreksi" id="absKoreksiCatatan" rows="3" placeholder="Contoh: surat dokter, izin praktikum…"></textarea>
            </div>
            <div class="abs-modal-f" style="border:none;padding:0;">
                <button type="button" class="btn-absensi" id="absKoreksiCancel">Batal</button>
                <button type="submit" class="btn-absensi primary">Simpan koreksi</button>
            </div>
        </form>
    </div>
</div>
<script>
(function(){
    const modal = document.getElementById('absKoreksiModal');
    const form = document.getElementById('absKoreksiForm');
    const meta = document.getElementById('absKoreksiMeta');
    const statusSel = document.getElementById('absKoreksiStatus');
    const catatan = document.getElementById('absKoreksiCatatan');
    const title = document.getElementById('absKoreksiTitle');
    function close(){ modal?.classList.remove('show'); modal?.setAttribute('aria-hidden','true'); }
    document.getElementById('absKoreksiClose')?.addEventListener('click', close);
    document.getElementById('absKoreksiCancel')?.addEventListener('click', close);
    modal?.addEventListener('click', e => { if(e.target === modal) close(); });
    window.openAbsensiKoreksi = function(opts){
        if(!form || !modal) return;
        form.action = opts.action;
        title.textContent = opts.title || 'Koreksi absensi';
        meta.innerHTML = opts.meta || '';
        statusSel.value = opts.status || 'lengkap';
        catatan.value = opts.catatan || '';
        const alfaOnly = opts.alfaMode === true;
        statusSel.querySelectorAll('option').forEach(o => {
            o.hidden = alfaOnly ? !['lengkap','belum'].includes(o.value) : false;
        });
        if(alfaOnly) statusSel.value = 'lengkap';
        modal.classList.add('show');
        modal.setAttribute('aria-hidden','false');
    };
})();
</script>
