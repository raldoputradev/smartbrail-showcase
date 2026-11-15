<style>
    .bms-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:12px}
    .bms-card{background:var(--bg-card);border:1px solid var(--border-subtle);border-radius:14px;padding:14px;display:flex;flex-direction:column;gap:10px}
    [data-theme="dark"] .bms-card{background:rgba(15,23,42,.6)}
    .bms-card__head{display:flex;justify-content:space-between;align-items:flex-start;gap:8px}
    .bms-card__title{font-weight:800;font-size:14px}
    .bms-card__sub{color:var(--text-muted);font-size:11.5px;margin-top:2px}
    .bms-chips{display:flex;flex-wrap:wrap;gap:6px}
    .bms-chip{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:999px;font-size:11px;font-weight:600;background:var(--bg-soft,#f1f5f9);color:var(--text-muted);border:1px solid var(--border-subtle)}
    [data-theme="dark"] .bms-chip{background:rgba(148,163,184,.12)}
    .bms-chip--ok{background:rgba(5,150,105,.12);color:#059669;border-color:transparent}
    .bms-chip--warn{background:rgba(217,119,6,.14);color:#b45309;border-color:transparent}
    .bms-chip--bad{background:rgba(220,38,38,.12);color:#dc2626;border-color:transparent}
    .bms-chip--info{background:rgba(59,130,246,.12);color:#2563eb;border-color:transparent}
    .bms-row{display:flex;justify-content:space-between;gap:8px;font-size:12.5px;color:var(--text-muted)}
    .bms-row strong{color:var(--text-primary,#0f172a);font-weight:700}
    [data-theme="dark"] .bms-row strong{color:#e2e8f0}
    .bms-actions{display:flex;flex-wrap:wrap;gap:6px;margin-top:auto}
    .bms-btn{display:inline-flex;align-items:center;gap:5px;min-height:30px;padding:0 12px;border-radius:9px;font-size:12px;font-weight:700;border:1px solid var(--border-subtle);background:var(--bg-card);color:var(--text-primary,#0f172a);cursor:pointer;text-decoration:none}
    .bms-btn--primary{background:var(--soft-blue,#4a90d9);border-color:transparent;color:#fff}
    .bms-btn--ghost{background:transparent;color:var(--text-muted)}
    .bms-btn--danger{color:#dc2626;border-color:rgba(220,38,38,.3)}
    .bms-input{width:100%;min-height:32px;padding:4px 10px;border-radius:9px;border:1px solid var(--border-subtle);background:var(--bg-card);color:inherit;font-size:12.5px}
    .bms-time{display:flex;align-items:center;gap:6px;padding:8px 10px;border-radius:10px;background:var(--bg-soft,#f1f5f9);font-size:12px;font-weight:600}
    [data-theme="dark"] .bms-time{background:rgba(148,163,184,.1)}
    .bms-time--alt{border:1px dashed rgba(217,119,6,.5);color:#b45309;background:rgba(217,119,6,.08)}
    .bms-people{display:flex;flex-wrap:wrap;gap:4px}
    .bms-person{font-size:11px;padding:2px 8px;border-radius:7px;background:var(--bg-soft,#f1f5f9);color:var(--text-muted)}
    [data-theme="dark"] .bms-person{background:rgba(148,163,184,.12)}
    @media (max-width:640px){.bms-grid{grid-template-columns:1fr}.bms-card{padding:12px}}
</style>
