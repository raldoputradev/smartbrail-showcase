from pathlib import Path

p = Path(__file__).resolve().parents[1] / "resources/views/dosen/mahasiswa.blade.php"
t = p.read_text(encoding="utf-8")
start = t.find('<form method="GET" action="{{ route(\'dosen.mahasiswa\') }}"')
end = t.find("</form>", start) + len("</form>")
new_form = r'''<form method="GET" action="{{ route('dosen.mahasiswa') }}" class="filter-toolbar">
                <motion class="filter-field filter-field--wide">
                    <label>Cari Nama / NIM / Email</label>
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="Ketik untuk cari…">
                </motion>
                <div class="filter-field">
                    <label>Kelas</label>
                    <select name="kelas">
                        <option value="">Semua</option>
                        @foreach($kelasOptions ?? [] as $k)<option value="{{ $k }}" @selected(request('kelas')===$k)>{{ $k }}</option>@endforeach
                    </select>
                </motion>
                <div class="filter-field">
                    <label>Tim PBL</label>
                    <select name="tim">
                        <option value="">Semua</option>
                        @foreach($timOptions ?? [] as $timOpt)<option value="{{ $timOpt }}" @selected(request('tim')===$timOpt)>{{ $timOpt }}</option>@endforeach
                    </select>
                </motion>
                <div class="filter-field">
                    <label>Semester</label>
                    <select name="semester">
                        <option value="">Semua</option>
                        @foreach($semesterOptions ?? [] as $s)<option value="{{ $s }}" @selected((string)request('semester')===(string)$s)>{{ $s }}</option>@endforeach
                    </select>
                </motion>
                <div class="filter-field">
                    <label>Status</label>
                    <select name="status">
                        <option value="">Semua</option>
                        <option value="active" @selected(request('status') === 'active')>Aktif</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>Pending</option>
                    </select>
                </motion>
                @include('partials.filter-actions', ['resetUrl' => route('dosen.mahasiswa'), 'submitLabel' => 'Filter'])
            </form>'''
new_form = new_form.replace("<motion", "<div").replace("</motion>", "</div>")
if start > 0:
    p.write_text(t[:start] + new_form + t[end:], encoding="utf-8")
    print("ok")
