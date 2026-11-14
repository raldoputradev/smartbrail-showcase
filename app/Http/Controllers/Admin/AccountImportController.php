<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AccountActivation;
use App\Support\AccountImport\AccountImportService;
use App\Support\AccountImport\AccountImportTemplate;
use App\Support\AccountImport\ImportFileException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountImportController extends Controller
{
    public function __construct(private readonly AccountImportService $service)
    {
    }

    public function index(Request $request)
    {
        $actor = $request->user();
        $types = [];
        foreach (AccountImportTemplate::TYPES as $type) {
            $types[$type] = [
                'label' => AccountImportTemplate::label($type),
                'allowed' => AccountImportService::canImport($actor, $type),
                'columns' => AccountImportTemplate::columns($type),
            ];
        }

        return view('admin.users_import', [
            'types' => $types,
            'selected' => in_array($request->query('jenis'), AccountImportTemplate::TYPES, true) ? $request->query('jenis') : 'mahasiswa',
            'ttlDays' => AccountActivation::codeTtlDays(),
        ]);
    }

    public function template(Request $request, string $type, string $format)
    {
        abort_unless(AccountImportService::canImport($request->user(), $type), 403);
        $name = "template-import-{$type}.{$format}";

        if ($format === 'xlsx') {
            return response(AccountImportTemplate::xlsx($type), 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$name.'"',
            ]);
        }

        return response(AccountImportTemplate::csv($type), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
        ]);
    }

    public function preview(Request $request)
    {
        $data = $request->validate([
            'jenis' => ['required', Rule::in(AccountImportTemplate::TYPES)],
            'file' => ['required', 'file', 'max:2048'],
        ], [
            'file.max' => 'Ukuran file melebihi batas 2 MB.',
            'file.required' => 'Pilih file .xlsx atau .csv.',
        ]);

        if (!AccountImportService::canImport($request->user(), $data['jenis'])) {
            abort(403, 'Anda tidak berwenang mengimpor akun '.$data['jenis'].'.');
        }

        $file = $request->file('file');
        try {
            $payload = $this->service->preview($data['jenis'], (string) $file->getRealPath(), (string) $file->getClientOriginalName(), $request->user());
        } catch (ImportFileException $e) {
            return redirect()->route('admin.users.import', ['jenis' => $data['jenis']])
                ->withErrors(['file' => $e->getMessage()]);
        }

        return redirect()->route('admin.users.import.show', $payload['token']);
    }

    public function show(Request $request, string $token)
    {
        $payload = $this->service->load($token, $request->user());
        abort_unless($payload, 404);

        return response()->view('admin.users_import_result', [
            'payload' => $payload,
            'labels' => AccountImportService::STATUS_LABELS,
            'cardsAvailable' => $payload['state'] === 'applied' && $this->service->cardsStillAvailable($token),
            'idLabel' => strtoupper(AccountImportTemplate::identifierColumn($payload['type'])),
            'typeLabel' => AccountImportTemplate::label($payload['type']),
            'canApply' => AccountImportService::canImport($request->user(), $payload['type']),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function apply(Request $request, string $token)
    {
        $payload = $this->service->load($token, $request->user());
        abort_unless($payload, 404);
        abort_unless(AccountImportService::canImport($request->user(), $payload['type']), 403);

        try {
            $result = $this->service->apply($token, $request->user(), $request);
        } catch (ImportFileException $e) {
            return redirect()->route('admin.users.import.show', $token)->with('error', $e->getMessage());
        }

        $t = $result['payload']['totals'];
        $message = "Import selesai: {$t['dibuat']} akun dibuat (menunggu aktivasi), {$t['duplikat']} duplikat, {$t['gagal']} gagal, {$t['dilewati']} dilewati dari {$t['total']} baris.";

        return redirect()->route('admin.users.import.show', $token)->with('success', $message);
    }

    public function report(Request $request, string $token)
    {
        $payload = $this->service->load($token, $request->user());
        abort_unless($payload, 404);

        $suffix = $payload['state'] === 'applied' ? 'hasil' : 'pratinjau';

        return response($this->service->reportCsv($payload), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="laporan-import-'.$payload['type'].'-'.$suffix.'-'.substr($token, 0, 8).'.csv"',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /** Kartu aktivasi: hanya sekali unduh, terpisah dari laporan import. */
    public function cards(Request $request, string $token)
    {
        $payload = $this->service->load($token, $request->user());
        abort_unless($payload, 404);

        $cards = $this->service->pullCards($token, $request->user());
        if (!$cards) {
            return redirect()->route('admin.users.import.show', $token)
                ->with('error', 'Kartu aktivasi sudah diunduh atau kedaluwarsa. Buat kode baru per akun dari Manajemen User bila perlu.');
        }

        return response($this->service->cardsCsv($cards, route('activation.show')), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="kartu-aktivasi-'.$payload['type'].'-'.substr($token, 0, 8).'.csv"',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /** Kode baru hanya untuk akun yang belum selesai aktivasi; bukan jalur reset password. */
    public function regenerateCode(Request $request, User $user)
    {
        if (!$user->isAwaitingActivation()) {
            return redirect()->route('admin.users')->with('error', 'Kode aktivasi hanya bisa dibuat untuk akun yang belum menyelesaikan aktivasi.');
        }
        abort_unless(AccountImportService::canImport($request->user(), $user->role), 403);

        $issued = AccountActivation::issueCode($user, $request->user(), $request, 'Kode aktivasi dibuat ulang oleh admin; kode lama batal.');

        return response()->view('admin.activation_code_once', [
            'target' => $user,
            'code' => $issued['code'],
            'expiresAt' => $issued['expires_at'],
            'activationUrl' => route('activation.show'),
        ])->header('Cache-Control', 'no-store, private');
    }
}
