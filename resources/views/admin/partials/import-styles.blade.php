<style>
    .imp-wrap{display:grid;gap:16px}
    .imp-card{padding:18px;border-radius:16px;border:1px solid var(--border-subtle);background:var(--bg-card)}
    .imp-card h3{margin:0 0 4px;font-size:16px;color:var(--text-dark)}
    .imp-card p.imp-sub{margin:0 0 14px;font-size:12.5px;color:var(--text-muted);line-height:1.55}
    .imp-steps{display:flex;gap:8px;flex-wrap:wrap;margin:0;padding:0;list-style:none}
    .imp-steps li{display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:999px;border:1px solid var(--border-subtle);font-size:12px;font-weight:700;color:var(--text-muted)}
    .imp-steps li b{display:inline-grid;place-items:center;width:20px;height:20px;border-radius:999px;background:rgba(15,118,110,.14);color:#0f766e;font-size:11px}
    .imp-steps li.is-on{border-color:#0f766e;color:var(--text-dark)}
    .imp-steps li.is-on b{background:#0f766e;color:#fff}
    .imp-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;min-height:38px;padding:0 14px;border-radius:10px;border:1px solid var(--border-subtle);background:transparent;color:var(--text-dark);font-size:13px;font-weight:700;cursor:pointer;text-decoration:none;white-space:nowrap}
    .imp-btn--primary{background:#0f766e;border-color:#0f766e;color:#fff}
    .imp-btn--warn{background:#b45309;border-color:#b45309;color:#fff}
    .imp-btn[disabled]{opacity:.5;cursor:not-allowed}
    .imp-row{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
    .imp-field{display:grid;gap:6px;min-width:200px;flex:1}
    .imp-field span{font-size:11.5px;font-weight:700;color:var(--text-muted)}
    .imp-field select,.imp-field input[type=file]{width:100%;padding:9px 10px;border-radius:10px;border:1px solid var(--border-subtle);background:var(--bg-main);color:var(--text-dark);font-size:13px}
    .imp-flash{padding:12px 14px;border-radius:12px;font-size:13px;font-weight:600;color:var(--text-dark);line-height:1.5}
    .imp-flash--ok{background:rgba(15,118,110,.12);border:1px solid rgba(15,118,110,.28)}
    .imp-flash--err{background:rgba(220,38,38,.1);border:1px solid rgba(220,38,38,.3)}
    .imp-flash--info{background:rgba(37,99,235,.1);border:1px solid rgba(37,99,235,.25)}
    .imp-flash--warn{background:rgba(217,119,6,.12);border:1px solid rgba(217,119,6,.32)}
    .imp-table-wrap{overflow:auto;border:1px solid var(--border-subtle);border-radius:12px}
    .imp-table{width:100%;border-collapse:collapse;font-size:12.5px;color:var(--text-dark)}
    .imp-table th,.imp-table td{padding:9px 10px;border-bottom:1px solid var(--border-subtle);text-align:left;vertical-align:top}
    .imp-table th{font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);background:color-mix(in srgb,var(--border-subtle) 30%,transparent)}
    .imp-table tr:last-child td{border-bottom:0}
    .imp-table code{font-size:12px}
    .imp-pill{display:inline-flex;padding:3px 9px;border-radius:999px;font-size:11px;font-weight:800;white-space:nowrap}
    .imp-pill--siap,.imp-pill--dibuat{background:rgba(15,118,110,.14);color:#0f766e}
    .imp-pill--duplikat{background:rgba(217,119,6,.15);color:#b45309}
    .imp-pill--gagal{background:rgba(220,38,38,.12);color:#dc2626}
    .imp-pill--dilewati{background:rgba(100,116,139,.15);color:#64748b}
    .imp-totals{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:10px}
    .imp-total{padding:12px;border-radius:12px;border:1px solid var(--border-subtle);text-align:center}
    .imp-total strong{display:block;font-size:22px;color:var(--text-dark)}
    .imp-total span{font-size:11.5px;font-weight:700;color:var(--text-muted)}
    .imp-cols{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
    .imp-col-card{padding:14px;border-radius:12px;border:1px solid var(--border-subtle)}
    .imp-col-card h4{margin:0 0 8px;font-size:13.5px;color:var(--text-dark)}
    .imp-col-card ul{margin:0 0 10px;padding-left:18px;font-size:12px;color:var(--text-muted);line-height:1.6}
    .imp-col-card code{color:var(--text-dark)}
    .imp-muted{font-size:12px;color:var(--text-muted);line-height:1.55}
    .imp-code{font-family:ui-monospace,SFMono-Regular,Consolas,monospace;font-size:26px;font-weight:800;letter-spacing:.12em;padding:14px 18px;border-radius:12px;border:1px dashed #0f766e;text-align:center;color:var(--text-dark);user-select:all}
    @media (max-width:900px){.imp-totals{grid-template-columns:repeat(2,minmax(0,1fr))}.imp-cols{grid-template-columns:1fr}}
</style>
