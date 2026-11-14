@forelse($mahasiswa as $student)
    <tr>
        <td>
            <a href="{{ route('profiles.show', $student) }}" class="student-link">{{ $student->nama_lengkap }}</a>
            <div class="fp-sub">{{ $student->email ?: '-' }}</div>
        </td>
        <td class="fp-mono">{{ $student->nitk_nidn }}</td>
        <td>{{ $student->kelas ?: '-' }} / {{ $student->tim ?: '-' }}</td>
        <td>
            @if(in_array($student->shift, ['pagi', 'malam'], true))
                <span class="status-badge success">{{ ucfirst($student->shift) }}</span>
            @else
                <span class="status-badge danger" title="Tap absen ditolak sampai shift diisi">Kosong</span>
            @endif
        </td>
        <td>
            @if($student->finger_id)
                <span class="finger-id-chip">#{{ $student->finger_id }}</span>
            @else
                <span class="status-badge warning">Belum assign</span>
            @endif
        </td>
        <td>
            @if($student->is_active)
                @if($student->hasFingerprintEnrolled())
                    <span class="status-badge success">Blob OK</span>
                @elseif($student->hasLegacyFingerprintPlaceholder())
                    <span class="status-badge danger" title="Data lama — enroll ulang di mesin absensi">Legacy</span>
                @elseif($student->finger_id)
                    <span class="status-badge warning">Belum enroll</span>
                @else
                    <span class="status-badge danger">Perlu Finger ID</span>
                @endif
            @else
                <span class="status-badge danger">Tidak aktif</span>
            @endif
        </td>
        <td>
            @if($student->hasFingerprintEnrolled())
                <div class="readiness ready">Siap tap</div>
            @elseif($student->hasLegacyFingerprintPlaceholder())
                <div class="readiness danger">Enroll ulang</div>
            @elseif($student->finger_id)
                <div class="readiness waiting">Scan 2× di mesin</div>
            @else
                <div class="readiness danger">Assign slot</div>
            @endif
        </td>
        <td class="is-right fp-actions">
            <form method="POST" action="{{ route('admin.fingerprint.assign', $student) }}" class="finger-action-form">
                @csrf
                <input type="number" name="finger_id" min="1" max="200" placeholder="auto" value="{{ $student->finger_id }}">
                <button type="submit" class="fp-icon-btn" title="Simpan Finger ID">@include('components.icon', ['name' => 'save'])</button>
            </form>
            @if($student->hasFingerprintEnrolled() || $student->hasLegacyFingerprintPlaceholder())
                <form method="POST" action="{{ route('admin.fingerprint.clear-enroll', $student) }}" class="inline-action-form"
                    data-swal-confirm
                    data-swal-title="Ulang enroll sidik jari?"
                    data-swal-text="Slot #{{ $student->finger_id }} tetap sama. Status di server jadi Belum Enroll — mesin bisa scan 2× lagi."
                    data-swal-icon="question"
                    data-swal-confirm-button="Ya, ulang enroll"
                    data-swal-confirm-color="#0f766e">
                    @csrf
                    <button type="submit" class="fp-icon-btn" title="Ulang enroll">@include('components.icon', ['name' => 'refresh-cw'])</button>
                </form>
            @endif
            @if($student->finger_id)
                <form method="POST" action="{{ route('admin.fingerprint.reset', $student) }}" class="inline-action-form"
                    data-swal-confirm
                    data-swal-title="Reset Finger ID?"
                    data-swal-text="Finger ID dan template {{ $student->nama_lengkap }} akan dikosongkan."
                    data-swal-icon="warning"
                    data-swal-confirm-button="Ya, Reset"
                    data-swal-confirm-color="#f43f5e">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="fp-icon-btn fp-icon-btn--danger" title="Hapus finger_id + enroll">@include('components.icon', ['name' => 'trash-2'])</button>
                </form>
            @endif
        </td>
    </tr>
@empty
    <tr>
        <td colspan="8" class="fp-empty">Tidak ada mahasiswa sesuai filter.</td>
    </tr>
@endforelse
