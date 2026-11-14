<style>
.absensi-stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:10px;margin-bottom:18px}
.absensi-stat{background:var(--bg-card);border:1px solid var(--border-subtle);border-radius:14px;padding:14px;text-align:center}
.absensi-stat b{display:block;font-size:22px;font-weight:800;line-height:1.1}
.absensi-stat span{font-size:11px;color:var(--text-muted);margin-top:4px;display:block}
.absensi-table{width:100%;border-collapse:collapse;font-size:13px}
.absensi-table th,.absensi-table td{padding:12px 10px;border-bottom:1px solid var(--border-subtle);text-align:left}
.absensi-badge{display:inline-block;padding:4px 10px;border-radius:999px;font-size:11px;font-weight:700;text-transform:uppercase}
.absensi-badge.lengkap{background:rgba(5,150,105,.15);color:#059669}
.absensi-badge.alfa{background:rgba(220,38,38,.12);color:#dc2626}
.absensi-badge.tidak_lengkap{background:rgba(234,88,12,.12);color:#ea580c}
.absensi-badge.masuk{background:rgba(37,99,235,.12);color:#2563eb}
.absensi-badge.sakit{background:rgba(168,85,247,.12);color:#7c3aed}
.absensi-badge.izin{background:rgba(14,116,144,.12);color:#0e7490}
.absensi-badge.belum,.absensi-badge.libur{background:rgba(148,163,184,.15);color:#64748b}
.absensi-badge.tidak_wajib{background:rgba(100,116,139,.1);color:#94a3b8}
.tap-jenis-badge{display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:999px;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.02em}
.tap-jenis-badge.masuk{background:rgba(15,118,110,.18);color:#0f766e;border:1px solid rgba(15,118,110,.28)}
.tap-jenis-badge.pulang{background:rgba(234,88,12,.15);color:#ea580c;border:1px solid rgba(234,88,12,.28)}
.tap-jenis-legenda{display:flex;flex-wrap:wrap;gap:12px;align-items:center;padding:10px 14px;margin-bottom:12px;border:1px solid var(--border-subtle);border-radius:12px;background:var(--bg-main);font-size:12px;color:var(--text-muted)}
.tap-jenis-legenda strong{color:var(--text-dark);font-weight:800;margin-right:4px}
.btn-absensi{padding:8px 14px;border-radius:10px;font-size:12px;font-weight:700;border:1px solid var(--border-subtle);background:var(--bg-card);cursor:pointer;font-family:inherit}
.btn-absensi.primary{background:linear-gradient(135deg,#0f766e,#2dd4bf);border:none;color:#fff}
.export-btn-group{display:inline-flex;gap:8px;flex-wrap:wrap;align-items:center}
.export-btn{display:inline-flex;align-items:center;gap:6px;padding:9px 14px;border-radius:10px;font-size:12px;font-weight:800;text-decoration:none;border:1px solid transparent;transition:transform .15s ease,box-shadow .15s ease;font-family:inherit}
.export-btn:hover{transform:translateY(-1px);box-shadow:0 6px 16px rgba(15,23,42,.12)}
.export-btn-icon{display:inline-flex;align-items:center;justify-content:center;line-height:0}
.export-btn-icon svg{width:16px;height:16px;stroke-width:2.25px}
/* filter toolbar: lihat panel-premium.css */
.export-btn.excel{background:linear-gradient(135deg,#15803d,#4ade80);color:#fff;border-color:rgba(21,128,61,.4)}
.export-btn.pdf{background:linear-gradient(135deg,#1e3a5f,#3b82f6);color:#fff;border-color:rgba(30,58,95,.35)}
.export-btn.word{background:linear-gradient(135deg,#1d4ed8,#60a5fa);color:#fff;border-color:rgba(29,78,216,.35)}
.abs-modal-overlay{position:fixed;inset:0;z-index:900;background:rgba(15,23,42,.55);display:none;align-items:center;justify-content:center;padding:16px}
.abs-modal-overlay.show{display:flex}
.abs-modal{background:var(--bg-card);border-radius:18px;width:min(440px,100%);box-shadow:0 24px 60px rgba(0,0,0,.25);border:1px solid var(--border-subtle)}
.abs-modal-h{padding:18px 20px;border-bottom:1px solid var(--border-subtle);display:flex;justify-content:space-between;align-items:center}
.abs-modal-h h3{margin:0;font-size:17px}
.abs-modal-b{padding:20px;display:grid;gap:14px}
.abs-modal-b label{display:block;font-size:12px;font-weight:700;margin-bottom:6px;color:var(--text-label)}
.abs-modal-b select,.abs-modal-b textarea,.abs-modal-b input{width:100%;padding:10px;border-radius:10px;border:1px solid var(--border-subtle);background:var(--bg-main);color:var(--text-dark);font-family:inherit}
.abs-modal-f{padding:14px 20px;border-top:1px solid var(--border-subtle);display:flex;justify-content:flex-end;gap:8px}
.absensi-panduan-panel{margin-bottom:20px;border:2px solid rgba(45,212,191,.45);border-radius:14px;background:linear-gradient(180deg,rgba(15,118,110,.14),rgba(15,118,110,.04));box-shadow:0 4px 20px rgba(15,118,110,.12)}
.absensi-panduan-panel>summary{cursor:pointer;padding:14px 18px;font-weight:800;font-size:14px;list-style:none;color:var(--text-dark);display:flex;align-items:center;justify-content:space-between;gap:12px;user-select:none}
.absensi-panduan-panel>summary::-webkit-details-marker{display:none}
.absensi-panduan-panel>summary::marker{content:''}
.absensi-panduan-panel>summary .absensi-panduan-hint{font-size:11px;font-weight:700;color:#2dd4bf;white-space:nowrap}
.absensi-panduan-panel>summary .absensi-panduan-chevron{display:inline-block;transition:transform .2s ease;color:#2dd4bf;font-size:18px;line-height:1}
.absensi-panduan-panel[open]>summary .absensi-panduan-chevron{transform:rotate(180deg)}
.absensi-panduan-panel .card-body{border-top:1px solid rgba(45,212,191,.2)}
.panduan-section-title{margin:18px 0 10px;font-size:13px;font-weight:800;color:#5eead4;letter-spacing:.02em}
.panduan-lead{margin:0 0 14px;font-size:13px;line-height:1.65;color:var(--text-dark)}
.panduan-grid-2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-bottom:14px}
@media(max-width:720px){.panduan-grid-2{grid-template-columns:1fr}}
.panduan-grid-3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-bottom:16px}
@media(max-width:900px){.panduan-grid-3{grid-template-columns:1fr}}
.panduan-mini-card{padding:12px 14px;border-radius:12px;background:rgba(15,23,42,.35);border:1px solid var(--border-subtle)}
.panduan-mini-card strong{display:block;font-size:12px;color:var(--text-dark);margin-bottom:4px}
.panduan-mini-card p{margin:0;font-size:12px;line-height:1.55;color:var(--text-dark);opacity:.92}
.panduan-flow-card{padding:14px 16px;border-radius:14px;border:1px solid rgba(45,212,191,.28);background:linear-gradient(145deg,rgba(15,118,110,.18),rgba(37,99,235,.08));color:var(--text-dark)}
.panduan-flow-card__icon{font-size:22px;line-height:1;margin-bottom:8px}
.panduan-flow-card strong{display:block;font-size:13px;margin-bottom:4px}
.panduan-flow-card p{margin:0;font-size:12px;line-height:1.5;opacity:.95}
.panduan-compare{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px}
@media(max-width:720px){.panduan-compare{grid-template-columns:1fr}}
.panduan-compare-col{padding:12px 14px;border-radius:12px;border:1px solid var(--border-subtle);font-size:12px;line-height:1.55;color:var(--text-dark)}
.panduan-compare-col h5{margin:0 0 8px;font-size:12px;font-weight:800;color:var(--text-dark)}
.panduan-compare-col ul{margin:0;padding-left:16px}
.panduan-compare-col--here{border-color:rgba(45,212,191,.35);background:rgba(15,118,110,.12)}
.panduan-bullets{margin:0;padding-left:18px;font-size:13px;line-height:1.6;color:var(--text-dark)}
.panduan-bullets li{margin-bottom:6px}
.panduan-bullets strong{color:#fff}

.absensi-dosen-day-note {
    margin-bottom: 16px;
    padding: 10px 14px;
    border-radius: 10px;
    border: 1px solid rgba(245, 158, 11, 0.35);
    background: rgba(245, 158, 11, 0.1);
    font-size: 13px;
    color: var(--text-dark);
}

.stat-cards--rekap {
    grid-template-columns: repeat(4, minmax(0, 1fr));
    margin-bottom: 0;
}
@media (max-width: 1200px) {
    .stat-cards--rekap { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 560px) {
    .stat-cards--rekap { grid-template-columns: 1fr; }
}
.abs-settings-muted{font-size:13px;line-height:1.55;color:var(--text-dark);opacity:.88}
.abs-settings-label{display:block;font-size:12px;font-weight:700;color:var(--text-dark);margin-bottom:6px}
.abs-settings-card{border-radius:16px;border:1px solid var(--border-subtle);background:var(--bg-card)}
.abs-settings-card .card-header h3{margin:0;color:var(--text-dark)}
.abs-ops-stat-label{display:block;font-size:11px;font-weight:700;color:var(--text-dark);opacity:.85;margin-bottom:4px;text-transform:uppercase;letter-spacing:.04em}
.abs-ops-stat-meta{display:block;font-size:11px;color:var(--text-dark);opacity:.78;margin-top:4px;line-height:1.4}
.abs-settings-table th,.abs-settings-table td{color:var(--text-dark)}
.abs-settings-form label{color:var(--text-dark);font-size:12px;font-weight:600}
.abs-settings-form input[type="time"],.abs-settings-form input[type="number"],.abs-settings-form input[type="date"],.abs-settings-form input[type="text"]{color:var(--text-dark);background:var(--bg-main);border:1px solid var(--border-subtle)}

/* ——— Atur Absensi (redesign) ——— */
.abs-btn{
    display:inline-flex;align-items:center;justify-content:center;gap:6px;
    min-height:38px;padding:0 14px;border-radius:10px;border:1px solid var(--border-subtle);
    background:transparent;color:var(--text-dark);font-size:13px;font-weight:700;
    cursor:pointer;text-decoration:none;white-space:nowrap;
}
.abs-btn--primary{background:#0f766e;border-color:#0f766e;color:#fff}
.abs-btn--primary:hover{filter:brightness(1.06)}
.abs-btn--danger{background:#dc2626;border-color:#dc2626;color:#fff}
.abs-btn--ghost:hover{border-color:color-mix(in srgb,#0f766e 40%,var(--border-subtle));color:var(--text-dark)}
.abs-help-q{
    display:inline-grid;place-items:center;width:18px;height:18px;margin-left:4px;
    border:1px solid color-mix(in srgb,#0f766e 45%,var(--border-subtle));
    border-radius:999px;background:rgba(15,118,110,.12);color:#0f766e;
    font-size:11px;font-weight:800;line-height:1;cursor:pointer;padding:0;vertical-align:middle;
}
.abs-help-q:hover,.abs-btn .abs-help-q{background:#0f766e;color:#fff;border-color:#0f766e}
.abs-btn .abs-help-q{margin-left:2px;background:rgba(15,118,110,.18)}
.abs-help-link{
    border:0;background:transparent;color:#0f766e;font-size:12px;font-weight:800;
    cursor:pointer;padding:0;text-decoration:underline;text-underline-offset:3px;
}

.abs-set-toolbar{
    display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;
    margin-bottom:16px;padding:14px 16px;border-radius:14px;
    border:1px solid var(--border-subtle);background:var(--bg-card);
}
.abs-set-toolbar__left{display:flex;align-items:center;gap:12px;min-width:0}
.abs-set-toolbar__left strong{display:block;font-size:14px;color:var(--text-dark)}
.abs-set-toolbar__left small{display:block;margin-top:2px;font-size:12px;color:var(--text-muted)}
.abs-set-toolbar__right{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.abs-set-live{
    display:inline-flex;align-items:center;gap:7px;padding:6px 10px;border-radius:999px;
    background:rgba(15,118,110,.12);color:#0f766e;font-size:10.5px;font-weight:800;letter-spacing:.08em;
}
.abs-set-live__dot{width:7px;height:7px;border-radius:999px;background:#0f766e;box-shadow:0 0 0 3px rgba(15,118,110,.2)}

.abs-flash{margin-bottom:14px;padding:12px 14px;border-radius:12px;font-size:13px;font-weight:600;color:var(--text-dark)}
.abs-flash--ok{background:rgba(15,118,110,.12);border:1px solid rgba(15,118,110,.28)}
.abs-flash--info{background:rgba(37,99,235,.1);border:1px solid rgba(37,99,235,.25)}

.abs-ops{
    margin-bottom:18px;padding:16px;border-radius:16px;
    border:1px solid var(--border-subtle);background:var(--bg-card);
}
.abs-ops.is-ready{border-color:rgba(15,118,110,.35)}
.abs-ops.is-alert{border-color:rgba(220,38,38,.3)}
.abs-ops__head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;margin-bottom:14px}
.abs-ops__eyebrow{font-size:10.5px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:var(--text-muted)}
.abs-ops__title{margin:4px 0 0;font-size:17px;color:var(--text-dark)}
.abs-ops__meta{margin:4px 0 0;font-size:12px;color:var(--text-muted)}
.abs-ops__tag{color:#0f766e;font-weight:800}
.abs-ops__head-right{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.abs-ops__badge{display:inline-flex;padding:6px 11px;border-radius:999px;font-size:11.5px;font-weight:800}
.abs-ops__badge.is-ok{background:rgba(15,118,110,.14);color:#0f766e}
.abs-ops__badge.is-bad{background:rgba(220,38,38,.12);color:#f87171}
.abs-ops__stats{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:10px}
.abs-ops__stat{
    padding:12px;border-radius:12px;border:1px solid var(--border-subtle);
    background:color-mix(in srgb,var(--border-subtle) 35%,transparent);
}
.abs-ops__stat-label{display:block;font-size:10.5px;font-weight:800;letter-spacing:.04em;text-transform:uppercase;color:var(--text-muted);margin-bottom:4px}
.abs-ops__stat strong{display:block;font-size:20px;line-height:1.15;color:var(--text-dark)}
.abs-ops__stat small{display:block;margin-top:4px;font-size:11px;color:var(--text-muted);line-height:1.35}
.abs-ops__stat.is-ok strong{color:#0f766e;font-size:15px}
.abs-ops__stat.is-bad strong{color:#f87171;font-size:15px}
.abs-ops__stat.is-warn strong{color:#f59e0b}
.abs-ops__hints{margin:12px 0 0;font-size:12px;color:var(--text-muted);line-height:1.5}
.abs-ops__hints strong{color:var(--text-dark);text-transform:uppercase;font-size:11px}
.abs-ops__issues{list-style:none;margin:12px 0 0;padding:0;display:grid;gap:8px}
.abs-ops__issues li{padding:10px 12px;border-radius:10px;font-size:12.5px;line-height:1.45}
.abs-ops__issues li strong{display:block;margin-bottom:2px}
.abs-ops__issues li.is-bad{background:rgba(220,38,38,.1);color:#fca5a5}
.abs-ops__issues li.is-warn{background:rgba(245,158,11,.1);color:#fcd34d}
.abs-ops__actions{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-top:14px;padding-top:14px;border-top:1px solid var(--border-subtle)}
.abs-ops__form{display:flex;gap:6px;align-items:center;flex-wrap:wrap}
.abs-ops__form input[type="date"]{
    padding:8px 10px;border-radius:10px;border:1px solid var(--border-subtle);
    background:var(--bg-main);color:var(--text-dark);font-size:13px;
}

.abs-card{
    margin-bottom:18px;padding:16px;border-radius:16px;
    border:1px solid var(--border-subtle);background:var(--bg-card);
}
.abs-card__head{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;margin-bottom:14px}
.abs-card__head h3{margin:0;font-size:16px;color:var(--text-dark)}
.abs-card__head p{margin:4px 0 0;font-size:12.5px;color:var(--text-muted)}
.abs-card__head p strong{color:var(--text-dark)}

.abs-policy{display:grid;grid-template-columns:1fr 1fr auto;gap:10px;align-items:stretch}
.abs-policy__opt{
    display:grid;gap:4px;padding:14px;border-radius:12px;cursor:pointer;
    border:1px solid var(--border-subtle);background:color-mix(in srgb,var(--border-subtle) 30%,transparent);
    transition:border-color .15s ease,background .15s ease;
}
.abs-policy__opt input{position:absolute;opacity:0;pointer-events:none}
.abs-policy__opt.is-active{border-color:rgba(15,118,110,.45);background:rgba(15,118,110,.1)}
.abs-policy__title{font-size:14px;font-weight:800;color:var(--text-dark)}
.abs-policy__desc{font-size:12px;color:var(--text-muted);line-height:1.4}
.abs-policy__actions{display:flex;align-items:center}

.abs-sesi-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.abs-sesi{padding:14px;border-radius:14px;border:1px solid var(--border-subtle);background:color-mix(in srgb,var(--border-subtle) 28%,transparent)}
.abs-sesi__head{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px}
.abs-sesi__badge{display:inline-flex;padding:5px 10px;border-radius:999px;background:rgba(15,118,110,.14);color:#0f766e;font-size:11px;font-weight:800;letter-spacing:.04em;text-transform:uppercase}
.abs-sesi__active{display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:700;color:var(--text-dark)}
.abs-sesi__fields{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.abs-sesi__fields label{display:grid;gap:5px}
.abs-sesi__fields span{font-size:11.5px;font-weight:700;color:var(--text-muted)}
.abs-sesi__fields input{
    width:100%;padding:9px 10px;border-radius:10px;border:1px solid var(--border-subtle);
    background:var(--bg-main);color:var(--text-dark);font-size:13px;
}
.abs-sesi__foot{margin-top:12px;display:flex;justify-content:flex-end}

.abs-table-wrap{overflow-x:auto}
.abs-split{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:8px}
.abs-inline-form{display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap}
.abs-inline-form input{
    flex:1;min-width:110px;padding:9px 10px;border-radius:10px;border:1px solid var(--border-subtle);
    background:var(--bg-main);color:var(--text-dark);font-size:13px;
}
.abs-list{list-style:none;margin:0;padding:0;max-height:280px;overflow:auto}
.abs-list li{
    display:flex;justify-content:space-between;align-items:center;gap:10px;
    padding:10px 0;border-bottom:1px solid var(--border-subtle);font-size:13px;color:var(--text-dark);
}
.abs-list li:last-child{border-bottom:0}
.abs-list small{opacity:.7}
.abs-list__del{border:0;background:none;color:#f87171;font-size:12px;font-weight:700;cursor:pointer;padding:0}
.abs-list__empty{color:var(--text-muted)!important;justify-content:center}

/* Help modal */
.iot-modal-backdrop{
    position:fixed;inset:0;z-index:3000;display:none;align-items:center;justify-content:center;
    padding:18px;background:rgba(15,23,42,.72);backdrop-filter:blur(8px);
}
.iot-modal-backdrop.is-open{display:flex}
.iot-modal{
    width:min(640px,100%);position:relative;border:1px solid rgba(148,163,184,.28);
    border-radius:18px;padding:22px;background:var(--bg-card);color:var(--text-dark);
    box-shadow:0 24px 70px rgba(15,23,42,.38);max-height:min(92vh,860px);overflow:auto;
}
.iot-modal-close{
    position:absolute;top:14px;right:14px;width:34px;height:34px;border:0;border-radius:999px;
    background:rgba(148,163,184,.14);color:var(--text-dark);font-size:20px;cursor:pointer;
}
.abs-help-modal{width:min(620px,100%)}
.abs-help-modal__badge{
    display:inline-flex;padding:5px 10px;border-radius:999px;margin-bottom:8px;
    background:rgba(15,118,110,.14);color:#0f766e;font-size:10.5px;font-weight:800;letter-spacing:.08em;
}
.abs-help-modal h3{margin:0 0 8px;font-size:20px}
.abs-help-modal__lead{margin:0 0 16px;font-size:13px;line-height:1.6;color:var(--text-muted)}
.abs-help-modal__lead a{color:#0f766e;font-weight:700;text-decoration:none}
.abs-help-steps{display:grid;gap:10px;margin-bottom:16px}
.abs-help-step{display:flex;gap:10px;align-items:flex-start}
.abs-help-step__n{
    width:24px;height:24px;border-radius:999px;flex-shrink:0;display:grid;place-items:center;
    background:#0f766e;color:#fff;font-size:11px;font-weight:800;
}
.abs-help-step strong{display:block;font-size:13px}
.abs-help-step small{display:block;margin-top:2px;font-size:12px;color:var(--text-muted);line-height:1.45}
.abs-help-section{margin-bottom:14px;padding-top:12px;border-top:1px solid var(--border-subtle)}
.abs-help-section h4{margin:0 0 8px;font-size:13px;color:var(--text-dark)}
.abs-help-section p{margin:0 0 8px;font-size:12.5px;color:var(--text-muted);line-height:1.5}
.abs-help-list{margin:0;padding-left:18px;font-size:12.5px;color:var(--text-muted);line-height:1.65}
.abs-help-list strong{color:var(--text-dark)}
.abs-help-ok{color:#0f766e;font-weight:700}
.abs-help-warn{color:#d97706;font-weight:700}
.abs-help-bad{color:#dc2626;font-weight:700}
.abs-help-table{width:100%;border-collapse:collapse;font-size:12px}
.abs-help-table th,.abs-help-table td{padding:8px 10px;border-bottom:1px solid var(--border-subtle);text-align:left;color:var(--text-dark)}
.abs-help-table th{font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:var(--text-muted)}
.abs-help-code{display:grid;gap:8px}
.abs-help-code > div{padding:10px 12px;border-radius:10px;border:1px solid var(--border-subtle);background:color-mix(in srgb,var(--border-subtle) 40%,transparent)}
.abs-help-code span{display:block;font-size:10.5px;font-weight:800;text-transform:uppercase;letter-spacing:.04em;color:var(--text-muted);margin-bottom:4px}
.abs-help-code code{display:block;font-size:11.5px;word-break:break-all;color:var(--text-dark)}
.abs-help-note{margin:10px 0 0!important;font-size:12px!important}
.abs-help-actions{display:flex;justify-content:flex-end;margin-top:8px}

/* ——— Rekap Harian redesign ——— */
.rk-help-q{
    display:inline-grid;place-items:center;width:18px;height:18px;margin-left:6px;
    border:1px solid color-mix(in srgb,#0f766e 45%,var(--border-subtle));
    border-radius:999px;background:rgba(15,118,110,.12);color:#0f766e;
    font-size:11px;font-weight:800;line-height:1;cursor:pointer;padding:0;vertical-align:middle;
}
.rk-help-q:hover{background:#0f766e;color:#fff;border-color:#0f766e}
.rk-btn .rk-help-q{margin-left:2px;background:rgba(15,118,110,.16)}
.rk-btn{
    display:inline-flex;align-items:center;justify-content:center;gap:6px;
    min-height:36px;padding:0 13px;border-radius:10px;border:1px solid var(--border-subtle);
    background:transparent;color:var(--text-dark);font-size:12.5px;font-weight:700;
    cursor:pointer;text-decoration:none;white-space:nowrap;font-family:inherit;
}
.rk-btn--primary{background:#0f766e;border-color:#0f766e;color:#fff}
.rk-btn--ghost:hover{border-color:color-mix(in srgb,#0f766e 40%,var(--border-subtle))}
.rk-btn--sm{min-height:30px;padding:0 10px;font-size:11.5px}

.rk-toolbar{
    display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;
    margin-bottom:16px;padding:14px 16px;border-radius:14px;
    border:1px solid var(--border-subtle);background:var(--bg-card);
}
.rk-toolbar__left{display:flex;align-items:center;gap:12px}
.rk-toolbar__left strong{display:block;font-size:14px;color:var(--text-dark)}
.rk-toolbar__left small{display:block;margin-top:2px;font-size:12px;color:var(--text-muted)}
.rk-toolbar__right{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.rk-live{
    display:inline-flex;align-items:center;gap:7px;padding:6px 10px;border-radius:999px;
    background:rgba(15,118,110,.12);color:#0f766e;font-size:10.5px;font-weight:800;letter-spacing:.08em;
}
.rk-live__dot{width:7px;height:7px;border-radius:999px;background:#0f766e;box-shadow:0 0 0 3px rgba(15,118,110,.2)}
.rk-flash{margin-bottom:14px;padding:12px 14px;border-radius:12px;font-size:13px;font-weight:600;color:var(--text-dark)}
.rk-flash--ok{background:rgba(15,118,110,.12);border:1px solid rgba(15,118,110,.28)}
.rk-flash--bad{background:rgba(220,38,38,.1);border:1px solid rgba(220,38,38,.25)}

.rk-stats{margin-bottom:16px}
.rk-charts{display:grid;grid-template-columns:1.1fr 1fr;gap:14px;margin-bottom:16px}
.rk-card{
    margin-bottom:16px;padding:16px;border-radius:16px;
    border:1px solid var(--border-subtle);background:var(--bg-card);
}
.rk-card--chart{margin-bottom:0}
.rk-card__head{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;margin-bottom:12px}
.rk-card__head--wrap{flex-wrap:wrap}
.rk-card__head h3{margin:0;font-size:16px;color:var(--text-dark)}
.rk-card__head p{margin:4px 0 0;font-size:12.5px;color:var(--text-muted)}
.rk-chart-wrap{position:relative;min-height:220px}

.rk-tabs{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px}
.rk-tab{
    display:inline-flex;align-items:center;gap:8px;padding:8px 14px;border-radius:999px;
    border:1px solid var(--border-subtle);background:var(--bg-card);color:var(--text-muted);
    font-size:12.5px;font-weight:700;text-decoration:none;
}
.rk-tab span{
    display:inline-flex;min-width:22px;justify-content:center;padding:2px 7px;border-radius:999px;
    background:rgba(148,163,184,.16);font-size:11px;
}
.rk-tab.is-active{border-color:rgba(15,118,110,.4);background:rgba(15,118,110,.12);color:var(--text-dark)}
.rk-tab.is-active span{background:#0f766e;color:#fff}

.rk-filters{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px}
.rk-filters input,.rk-filters select{
    min-height:36px;padding:0 10px;border-radius:10px;border:1px solid var(--border-subtle);
    background:var(--bg-main);color:var(--text-dark);font-size:13px;
}
.rk-table-wrap{overflow-x:auto}
.rk-table{min-width:760px}
.rk-table th.is-right,.rk-table td.is-right{text-align:right}
.rk-rank{font-weight:800;color:#0f766e;text-align:center}
.rk-mono{font-family:ui-monospace,SFMono-Regular,Consolas,monospace;font-size:12px}
.rk-center{text-align:center}
.rk-muted{font-size:11px;color:var(--text-muted)}
.rk-sub{font-size:11px;color:var(--text-muted);margin-top:2px}
.rk-empty{text-align:center;color:var(--text-muted);padding:24px 14px!important}
.rk-row--tapped{background:rgba(15,118,110,.04)}

.rk-mini-nav{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px}
.rk-mini-nav__link{
    display:inline-flex;padding:8px 14px;border-radius:10px;border:1px solid var(--border-subtle);
    background:var(--bg-card);color:var(--text-muted);font-size:12.5px;font-weight:700;text-decoration:none;
}
.rk-mini-nav__link.is-active{background:rgba(15,118,110,.12);color:#0f766e;border-color:rgba(15,118,110,.3)}

.iot-modal-backdrop{
    position:fixed;inset:0;z-index:3000;display:none;align-items:center;justify-content:center;
    padding:18px;background:rgba(15,23,42,.72);backdrop-filter:blur(8px);
}
.iot-modal-backdrop.is-open{display:flex}
.iot-modal{
    width:min(560px,100%);position:relative;border:1px solid rgba(148,163,184,.28);
    border-radius:18px;padding:22px;background:var(--bg-card);color:var(--text-dark);
    box-shadow:0 24px 70px rgba(15,23,42,.38);max-height:min(90vh,720px);overflow:auto;
}
.iot-modal-close{
    position:absolute;top:14px;right:14px;width:34px;height:34px;border:0;border-radius:999px;
    background:rgba(148,163,184,.14);color:var(--text-dark);font-size:20px;cursor:pointer;
}
.rk-help-badge{
    display:inline-flex;padding:5px 10px;border-radius:999px;margin-bottom:8px;
    background:rgba(15,118,110,.14);color:#0f766e;font-size:10.5px;font-weight:800;letter-spacing:.08em;
}
.rk-help-modal h3{margin:0 0 8px;font-size:18px}
.rk-help-desc{margin:0 0 12px;font-size:13px;line-height:1.6;color:var(--text-muted)}
.rk-help-code{
    font-family:ui-monospace,SFMono-Regular,Consolas,monospace;font-size:12px;line-height:1.55;
    padding:12px 14px;border-radius:12px;border:1px solid var(--border-subtle);
    background:color-mix(in srgb,var(--border-subtle) 40%,transparent);color:var(--text-dark);
    white-space:pre-wrap;margin-bottom:8px;
}
.rk-help-code:empty{display:none}
.rk-help-actions{display:flex;justify-content:flex-end}

@media (max-width:1100px){
    .abs-ops__stats{grid-template-columns:repeat(3,minmax(0,1fr))}
    .abs-policy{grid-template-columns:1fr}
    .abs-sesi-grid,.abs-split{grid-template-columns:1fr}
    .rk-charts{grid-template-columns:1fr}
}
@media (max-width:640px){
    .abs-ops__stats{grid-template-columns:1fr 1fr}
    .abs-sesi__fields{grid-template-columns:1fr}
    .abs-set-toolbar{align-items:stretch}
    .abs-set-toolbar__right{width:100%}
    .abs-set-toolbar__right .abs-btn{flex:1}
    .rk-toolbar{align-items:stretch}
    .rk-toolbar__right{width:100%}
}
</style>
