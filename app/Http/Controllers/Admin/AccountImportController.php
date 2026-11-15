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
    use \App\Http\Controllers\Concerns\AdminShellProps;

    public function __construct(private readonly AccountImportService $service)
    {
    }

    /** URL unduhan template per jenis akun. */
    private function templateUrls(): array
    {
        $urls = [];
        foreach (AccountImportTemplate::TYPES as $type) {
            $urls[$type] = [
                'xlsx' => route('admin.users.import.template', ['type' => $type, 'format' => 'xlsx']),
                'csv' => route('admin.users.import.template', ['type' => $type, 'format' => 'csv']),
            ];
        }

        return $urls;
    }

    private function formProps(Request $request): array
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

        return [
            'mode' => 'form',
            'types' => $types,
            'selected' => in_array($request->query('jenis'), AccountImportTemplate::TYPES, true) ? $request->query('jenis') : 'mahasiswa',
            'ttl_days' => AccountActivation::codeTtlDays(),
            'urls' => [
                'preview' => route('admin.users.import.preview'),
                'index' => route('admin.users.import'),
                'users' => route('admin.users'),
                'templates' => $this->templateUrls(),
            ],
        ];
    }

    /** Pangkas baris payload ke kolom yang dibutuhkan UI (tabel hasil validasi). */
    private function slimRows(array $payload): array
    {
        return array_map(fn (array $row) => [
            'line' => $row['line'] ?? null,
            'identifier' => $row['identifier'] ?? '',
            'nama' => $row['nama'] ?? '',
            'status' => $row['status'] ?? '',
            'reason' => $row['reason'] ?? '',
        ], $payload['rows'] ?? []);
    }

    public function index(Request $request)
    {
        \Inertia\Inertia::setRootView('app-admin');

        return \Inertia\Inertia::render('AdminImport', array_merge([
            'menu' => $this->adminSidebarMenu($request->user()),
            'notif' => $this->adminNotifications($request->user()),
        ], $this->formProps($request)));
    }

    public function template(Request $request, string $type, string $format)
    {
        abort_unless(AccountImportService::canImport($request->user(), $type), 403);
        $name = AccountImportTemplate::filename($type, $format);

        if ($format === 'xlsx') {
            $projects = [];
            if (in_array($type, ['mahasiswa', 'semua'], true)) {
                $projects = \App\Models\PblProject::query()
                    ->where('aktif', true)
                    ->orderBy('kode')
                    ->get()
                    ->map(fn ($p) => [
                        (string) $p->kode,
                        (string) $p->nama,
                        (string) ($p->manpro ?? $p->manproUser?->nama_lengkap ?? ''),
                        (string) ($p->co_manpro ?? $p->coManproUser?->nama_lengkap ?? ''),
                        (string) ($p->shift_sesi ?? ''),
                    ])->all();
            }

            return response(AccountImportTemplate::xlsx($type, $projects), 200, [
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

        \Inertia\Inertia::setRootView('app-admin');

        $response = \Inertia\Inertia::render('AdminImport', array_merge([
            'menu' => $this->adminSidebarMenu($request->user()),
            'notif' => $this->adminNotifications($request->user()),
        ], $this->formProps($request), [
            'mode' => 'result',
            'payload' => [
                'token' => $payload['token'],
                'type' => $payload['type'],
                'file_name' => $payload['file_name'] ?? '',
                'created_at' => $payload['created_at'] ?? null,
                'applied_at' => $payload['applied_at'] ?? null,
                'state' => $payload['state'],
                'notices' => $payload['notices'] ?? [],
                'totals' => $payload['totals'],
                'rows' => $this->slimRows($payload),
            ],
            'labels' => AccountImportService::STATUS_LABELS,
            'id_label' => strtoupper(AccountImportTemplate::identifierColumn($payload['type'])),
            'type_label' => AccountImportTemplate::label($payload['type']),
            'can_apply' => AccountImportService::canImport($request->user(), $payload['type']),
            'cards_available' => $payload['state'] === 'applied' && $this->service->cardsStillAvailable($token),
            'result_urls' => [
                'apply' => route('admin.users.import.apply', $token),
                'report' => route('admin.users.import.report', $token),
                'cards' => route('admin.users.import.cards', $token),
            ],
        ]));

        $resp = $response->toResponse($request);
        $resp->headers->set('Cache-Control', 'no-store, private');

        return $resp;
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
        $statusHint = match ($payload['type']) {
            'mahasiswa' => 'siap login dengan password sementara',
            'petugas' => 'petugas ruangan tanpa akun portal',
            'semua' => 'campuran per role baris',
            default => 'menunggu aktivasi',
        };
        $message = "Import selesai: {$t['dibuat']} data dibuat ({$statusHint}), {$t['duplikat']} duplikat, {$t['gagal']} gagal, {$t['dilewati']} dilewati dari {$t['total']} baris.";

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

    /** Kartu kredensial sementara / aktivasi: hanya sekali unduh, terpisah dari laporan import. */
    public function cards(Request $request, string $token)
    {
        $payload = $this->service->load($token, $request->user());
        abort_unless($payload, 404);

        $cards = $this->service->pullCards($token, $request->user());
        if (!$cards) {
            return redirect()->route('admin.users.import.show', $token)
                ->with('error', 'Kartu aktivasi sudah diunduh atau kedaluwarsa. Buat kode baru per akun dari Manajemen User bila perlu.');
        }

        $loginUrls = ['default' => route('login')];
        foreach (['mahasiswa', 'dosen', 'laboran', 'admin'] as $r) {
            $loginUrls[$r] = route('login', ['role' => $r]);
        }

        return response($this->service->cardsCsv($cards, $loginUrls), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="kartu-aktivasi-'.$payload['type'].'-'.substr($token, 0, 8).'.csv"',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /**
     * Terbitkan ulang kode aktivasi (untuk akun menunggu_aktivasi) atau reset password sementara (untuk mahasiswa aktif).
     * Memerlukan izin pengelolaan user (users.update) dan tidak berlaku untuk akun nonaktif/ditolak/diri sendiri.
     */
    public function regenerateCode(Request $request, User $user)
    {
        $actor = $request->user();
        abort_unless(AccountImportService::canManageTemporaryCredentials($actor, $user), 403);

        if ($user->isAwaitingActivation()) {
            $issued = AccountActivation::issueCode($user, $actor, $request, 'Kode aktivasi dibuat ulang oleh admin; kode lama batal.');
            $entryUrl = $user->role === 'mahasiswa'
                ? route('login', ['role' => 'mahasiswa'])
                : route('activation.show');
            $credentialKind = 'kode aktivasi';
        } elseif ($user->approval_status === 'approved' && $user->is_active) {
            $note = 'Password sementara direset oleh admin; sesi aktif dan password lama dicabut.';
            $issued = $user->role === 'mahasiswa'
                ? AccountActivation::issueTemporaryPasswordForMahasiswa($user, $actor, $request, $note, true)
                : AccountActivation::issueTemporaryPassword($user, $actor, $request, $note, true);
            $entryUrl = route('login', ['role' => $user->role]);
            $credentialKind = 'password sementara';
        } else {
            return redirect()->route('admin.users')
                ->with('error', 'Kode aktivasi atau password sementara hanya dapat dibuat untuk akun aktif atau akun yang belum menyelesaikan aktivasi.');
        }

        if ($request->header('X-Inertia')) {
            // Kode sekali-tampil lewat flash session — halaman React menampilkannya
            // di modal; setara halaman HTML sekali-tampil sebelumnya.
            return back()->with('activation_once', [
                'nama' => $user->nama_lengkap,
                'nitk' => $user->nitk_nidn,
                'jenis' => $credentialKind,
                'code' => $issued['code'],
                'expires_at' => $issued['expires_at'] instanceof \DateTimeInterface
                    ? $issued['expires_at']->format('d M Y H:i')
                    : (string) $issued['expires_at'],
                'entry_url' => $entryUrl,
            ]);
        }

        return response()->view('admin.activation_code_once', [
            'target' => $user,
            'code' => $issued['code'],
            'expiresAt' => $issued['expires_at'],
            'activationUrl' => $entryUrl,
        ])->header('Cache-Control', 'no-store, private');
    }
}
