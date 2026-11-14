<?php



namespace App\Support;



use App\Models\AbsensiSesiSetting;

use Carbon\Carbon;

use Illuminate\Support\Facades\Cache;



class AbsensiSessionCloser

{

    public function __construct(

        private readonly AbsensiTapEvaluator $evaluator

    ) {}



    /**

     * Tutup sesi: alfa (tanpa tap) sejak jam pulang; tidak lengkap (hanya masuk) setelah jam pulang + grace.

     *

     * @return list<array{shift: string, tanggal: string, alfa: int, ditutup_pada?: string, alfa_pada?: string}>

     */

    public function closeDueSessions(?Carbon $now = null): array

    {

        $results = [];



        foreach (AbsensiSesiSetting::query()->where('aktif', true)->get() as $setting) {

            $localized = $setting->timezoneCarbon($now);

            $tanggal = $localized->toDateString();

            $row = $this->closeShiftForDate($setting, $tanggal, $localized, false);

            if ($row !== null) {

                $results[] = $row;

            }

        }



        return $results;

    }



    /**

     * Paksa tutup sesi untuk satu tanggal (backfill / tombol admin).

     *

     * @return list<array{shift: string, tanggal: string, alfa: int, ditutup_pada?: string, alfa_pada?: string}>

     */

    public function closeForDate(string $tanggal, bool $force = false): array

    {

        $results = [];

        $anchor = Carbon::parse($tanggal)->endOfDay();



        foreach (AbsensiSesiSetting::query()->where('aktif', true)->get() as $setting) {

            $localized = $setting->timezoneCarbon($anchor);

            $row = $this->closeShiftForDate($setting, $tanggal, $localized, $force);

            if ($row !== null) {

                $results[] = $row;

            }

        }



        return $results;

    }



    /**

     * @return array{shift: string, tanggal: string, alfa: int, ditutup_pada: string, alfa_pada: string}|null

     */

    private function closeShiftForDate(

        AbsensiSesiSetting $setting,

        string $tanggal,

        Carbon $now,

        bool $force

    ): ?array {

        if (!AbsensiKebijakan::isHariWajib($tanggal)) {

            return null;

        }



        $day = Carbon::parse($tanggal, $setting->timezone ?: 'Asia/Jakarta');

        $pulangAt = $setting->jamPulangOn($day);

        $graceCloseAt = $pulangAt->copy()->addMinutes((int) $setting->grace_alfa_menit);



        $alfaKey = "absensi_alfa_assigned:{$setting->shift}:{$tanggal}";

        $sessionKey = "absensi_sesi_ditutup:{$setting->shift}:{$tanggal}";



        $alfaCount = 0;

        $didWork = false;



        if ($force || $now->gte($pulangAt)) {

            if ($force || !Cache::has($alfaKey)) {

                $alfaCount = $this->evaluator->assignAlfaForNoTapStudents($tanggal, $setting->shift);

                Cache::put($alfaKey, true, $day->copy()->endOfDay());

                $didWork = true;

            }

        }



        if ($force || $now->gte($graceCloseAt)) {

            if ($force || !Cache::has($sessionKey)) {

                $this->evaluator->finalizeTidakLengkapForDate($tanggal, $setting->shift);

                Cache::put($sessionKey, true, $day->copy()->endOfDay());

                $didWork = true;

            }

        }



        if (!$didWork && !$force) {

            return null;

        }



        return [

            'shift' => $setting->shift,

            'tanggal' => $tanggal,

            'alfa' => $alfaCount,

            'alfa_pada' => $pulangAt->toDateTimeString(),

            'ditutup_pada' => $graceCloseAt->toDateTimeString(),

        ];

    }

}


