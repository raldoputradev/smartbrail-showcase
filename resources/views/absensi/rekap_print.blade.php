@php
    use App\Support\RekapAbsensiExportHtml;

    $html = RekapAbsensiExportHtml::render($filter, $rows, $summary, auth()->user());
    $toolbar = view('absensi._export_print_toolbar', ['panel' => $panel])->render();
    echo str_replace('</body>', $toolbar . '</body>', $html);
@endphp
