<div class="no-print" style="position:fixed;top:14px;right:14px;z-index:9999;display:flex;gap:8px;flex-wrap:wrap;max-width:420px;justify-content:flex-end;">
    <button type="button" onclick="window.print()" style="display:inline-flex;align-items:center;gap:6px;padding:10px 16px;border:none;border-radius:10px;background:linear-gradient(135deg,#1e3a5f,#3b82f6);color:#fff;font-weight:800;cursor:pointer;font-family:inherit;">🧾 Cetak / PDF</button>
    @include('absensi._export_buttons', ['panel' => $panel])
</div>
<style>@media print{.no-print{display:none!important}body{padding:12px!important}}</style>
