<?php

namespace App\Http\Controllers;

use App\Models\PromoContent;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DisplayController extends Controller
{
    /**
     * Halaman utama display: antrian + slider
     */
    public function index(){
        $nmPoli = request()->query('poli');   // contoh: "Poli Umum"
        $nmDokter = request()->query('dokter'); // contoh: "dr. Budi"

        $hari = $this->hariIdUpper();

        // 1) Cek jadwal hari ini dari DB SIK (tabel: jadwal)
        $jadwal = $this->fetchJadwalToday($hari, $nmPoli, $nmDokter);

        $now = Carbon::now();
        $jamMulai = $jamSelesai = null;
        $antrianAktif = false;

        if ($jadwal) {
            $jamMulai = $this->parseJam($jadwal->jam_mulai);
            $jamSelesai = $this->parseJam($jadwal->jam_selesai);
            $validJam = $jadwal->jam_mulai !== '00:00:00' && $jadwal->jam_selesai !== '00:00:00';
            $antrianAktif = $validJam
                && $jamMulai instanceof Carbon
                && $jamSelesai instanceof Carbon
                && $now->greaterThanOrEqualTo($jamMulai)
                && $now->lessThanOrEqualTo($jamSelesai);
        }

        // Tandai status mulai untuk poli/dokter terpilih sebelum kita paksa grid tampil sepanjang hari
        $isStartedSelected = $antrianAktif;

        // 2) Ambil konten promo publishable
        $promos = PromoContent::publishable()->get();
        if ($promos->isEmpty()) {
            $promos = PromoContent::orderBy('display_order')->get(); // fallback agar layar tidak kosong
        }

        // Bangun daftar sesi (poli-dokter) aktif HARI INI dan ambil antrian masing-masing
        $sessions = $this->fetchSessionsToday($hari);

        // Tampilkan grid sesi sepanjang hari, tidak tergantung aktif
        $antrianAktif = true;

        // 3) Ambil antrian pasien dari SIK (untuk kompatibilitas variabel lama)
        $pasien = [];
        try {
            $q = DB::connection('khanza')->table('reg_periksa')
                ->join('dokter', 'reg_periksa.kd_dokter', '=', 'dokter.kd_dokter')
                ->join('pasien', 'reg_periksa.no_rkm_medis', '=', 'pasien.no_rkm_medis')
                ->join('poliklinik', 'reg_periksa.kd_poli', '=', 'poliklinik.kd_poli')
                ->select(
                    'reg_periksa.no_reg',
                    'reg_periksa.no_rkm_medis',
                    'pasien.nm_pasien',
                    'reg_periksa.no_rawat',
                    'dokter.nm_dokter',
                    'reg_periksa.jam_reg'
                )
                ->whereDate('reg_periksa.tgl_registrasi', Carbon::today());

            if ($nmPoli) {
                $q->where('poliklinik.nm_poli', $nmPoli);
            }
            if ($nmDokter) {
                $q->where('dokter.nm_dokter', $nmDokter);
            }

            $pasien = $q->where('stts', 'Belum')
                ->orderByRaw('CAST(reg_periksa.no_reg AS UNSIGNED) asc')
                ->orderBy('reg_periksa.jam_reg', 'asc')
                ->get()
                ->all();
        } catch (\Throwable $e) {
            report($e);
            $pasien = [];
        }

        // Hitung current untuk hero pada render awal:
        if ($isStartedSelected && $jadwal) {
            $nmPoli = $nmPoli ?: ($jadwal->nm_poli ?? null);
            $nmDokter = $nmDokter ?: ($jadwal->nm_dokter ?? null);
        }

        $current = null;
        if ($isStartedSelected && $nmPoli && $nmDokter) {
            $current = $this->resolveCurrent('Dalam Pelayanan', $nmPoli, $nmDokter);
        }

        // Oper variabel 'started' ke view agar hero "Nomor Antrian Saat Ini" dapat menampilkan 000 sebelum praktik dimulai
        $started = $isStartedSelected;

        // Siapkan ticker dari konfigurasi (sanitize di controller, hindari @php di view)
        $ticker = strip_tags((string) config('display.footer_ticker_text'));

        return view('display.index', compact(
            'promos',
            'sessions',
            'pasien',
            'antrianAktif',
            'jadwal',
            'jamMulai',
            'jamSelesai',
            'nmPoli',
            'nmDokter',
            'started',
            'current',
            'ticker'
        ));
    }

    public function partialQueue(){
        $hari = $this->hariIdUpper();
        $sessions = $this->fetchSessionsToday($hari);

        return view('display.partials._queue', compact('sessions'));
    }

    public function partialCurrent(){
        $nmPoli = request()->query('poli');
        $nmDokter = request()->query('dokter');

        $current = null;
        $started = true; // default: anggap mulai, kecuali bisa dipastikan belum mulai

        try {
            $hari = $this->hariIdUpper();

            $jadwal = $this->fetchJadwalToday($hari, $nmPoli, $nmDokter);
            if ($jadwal && $jadwal->jam_mulai !== '00:00:00' && $jadwal->jam_selesai !== '00:00:00') {
                $jm = $this->parseJam($jadwal->jam_mulai);
                $js = $this->parseJam($jadwal->jam_selesai);
                $now = Carbon::now();
                if ($jm && $js) {
                    $started = $now->greaterThanOrEqualTo($jm) && $now->lessThanOrEqualTo($js);
                }
            }

            // Hanya ambil pasien jika jadwal sudah mulai
            // Defaultkan filter poli/dokter dari jadwal aktif bila tidak disuplai di query
            if ($started && $jadwal) {
                $nmPoli = $nmPoli ?: ($jadwal->nm_poli ?? null);
                $nmDokter = $nmDokter ?: ($jadwal->nm_dokter ?? null);
            }

            if ($started && $nmPoli && $nmDokter) {
                $current = $this->resolveCurrent('Dalam Pelayanan', $nmPoli, $nmDokter);
            } else {
                $current = null; // sebelum mulai: hero akan tampil "000"
            }
        } catch (\Throwable $e) {
            report($e);
            // biarkan $current tetap null
        }

        return view('display.partials._current', compact('current', 'started'));
    }

    // ===========================
    // Private helpers
    // ===========================

    private function hariIdUpper(): string{
        $locale = 'id';
        Carbon::setLocale($locale);
        return Str::upper(Carbon::now()->locale($locale)->dayName); // SENIN/SELASA/...
    }

    private function parseJam(?string $jam): ?Carbon{
        try {
            return ($jam && $jam !== '00:00:00') ? Carbon::createFromFormat('H:i:s', $jam) : null;
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }

    private function fetchJadwalToday(string $hari, ?string $nmPoli, ?string $nmDokter){
        try {
            return DB::connection('khanza')->table('jadwal')
                ->join('dokter', 'dokter.kd_dokter', '=', 'jadwal.kd_dokter')
                ->join('poliklinik', 'poliklinik.kd_poli', '=', 'jadwal.kd_poli')
                ->select('jadwal.*', 'dokter.nm_dokter', 'poliklinik.nm_poli')
                ->where('hari_kerja', $hari)
                ->when($nmPoli, fn($q) => $q->where('poliklinik.nm_poli', $nmPoli))
                ->when($nmDokter, fn($q) => $q->where('dokter.nm_dokter', $nmDokter))
                ->first();
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }

    private function computeStatus(Carbon $now, ?Carbon $jm, ?Carbon $js): string
    {
        if (!$jm || !$js) {
            return 'Belum Mulai';
        }
        if ($now->lt($jm)) {
            return 'Belum Mulai';
        }
        if ($now->gt($js)) {
            return 'Selesai';
        }
        return 'Dalam Pelayanan';
    }

    /**
     * Ambil list pasien berstatus "Belum" untuk poli-dokter tertentu, arah ASC/DESC.
     *
     * @return array<object>
     */
    private function fetchPasienBelum(string $nmPoli, string $nmDokter, string $direction = 'asc'): array
    {
        try {
            $q = DB::connection('khanza')->table('reg_periksa')
                ->join('dokter', 'reg_periksa.kd_dokter', '=', 'dokter.kd_dokter')
                ->join('pasien', 'reg_periksa.no_rkm_medis', '=', 'pasien.no_rkm_medis')
                ->join('poliklinik', 'reg_periksa.kd_poli', '=', 'poliklinik.kd_poli')
                ->select(
                    'reg_periksa.no_reg',
                    'reg_periksa.no_rkm_medis',
                    'pasien.nm_pasien',
                    'reg_periksa.no_rawat',
                    'dokter.nm_dokter',
                    'reg_periksa.jam_reg'
                )
                ->whereDate('reg_periksa.tgl_registrasi', Carbon::today())
                ->where('poliklinik.nm_poli', $nmPoli)
                ->where('dokter.nm_dokter', $nmDokter);

            if (strtolower($direction) === 'asc') {
                $q->where('stts', 'Belum')
                  ->orderByRaw('CAST(reg_periksa.no_reg AS UNSIGNED) asc')
                  ->orderBy('reg_periksa.jam_reg', 'asc');
            } else {
                $q->orderByRaw('CAST(reg_periksa.no_reg AS UNSIGNED) desc')
                  ->orderBy('reg_periksa.jam_reg', 'desc');
            }

            return $q->get()->all();
        } catch (\Throwable $e) {
            report($e);
            return [];
        }
    }

    private function fetchLastPasienOfToday(string $nmPoli, string $nmDokter): ?object
    {
        try {
            $rows = DB::connection('khanza')->table('reg_periksa')
                ->join('dokter', 'reg_periksa.kd_dokter', '=', 'dokter.kd_dokter')
                ->join('pasien', 'reg_periksa.no_rkm_medis', '=', 'pasien.no_rkm_medis')
                ->join('poliklinik', 'reg_periksa.kd_poli', '=', 'poliklinik.kd_poli')
                ->select(
                    'reg_periksa.no_reg',
                    'reg_periksa.no_rkm_medis',
                    'pasien.nm_pasien',
                    'reg_periksa.no_rawat',
                    'dokter.nm_dokter',
                    'reg_periksa.jam_reg'
                )
                ->whereDate('reg_periksa.tgl_registrasi', Carbon::today())
                ->where('poliklinik.nm_poli', $nmPoli)
                ->where('dokter.nm_dokter', $nmDokter)
                ->orderByRaw('CAST(reg_periksa.no_reg AS UNSIGNED) desc')
                ->orderBy('reg_periksa.jam_reg', 'desc')
                ->limit(1)
                ->get()
                ->all();

            return $rows[0] ?? null;
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }

    /**
     * Pasien yang sedang dipanggil = antrean terdepan (status 'Belum' paling awal).
     * Jika belum mulai: kembalikan null -> akan ditampilkan "000".
     * Jika tidak ada 'Belum' lagi: fallback ke nomor terakhir hari ini.
     */
    private function resolveCurrent(string $status, ?string $nmPoli, ?string $nmDokter): ?object
    {
        if (!$nmPoli || !$nmDokter) {
            return null;
        }

        if ($status === 'Belum Mulai') {
            return null;
        }

        $currentAsc = $this->fetchPasienBelum($nmPoli, $nmDokter, 'asc');
        if (!empty($currentAsc)) {
            return $currentAsc[0] ?? null;
        }

        return $this->fetchLastPasienOfToday($nmPoli, $nmDokter);
    }

    /**
     * Ambil semua sesi hari ini yang jamnya valid dan belum "Selesai".
     *
     * @return array<array<string, mixed>>
     */
    private function fetchSessionsToday(string $hari): array
    {
        $sessions = [];
        try {
            $now = Carbon::now();

            $jadwalsAct = DB::connection('khanza')->table('jadwal')
                ->join('dokter', 'dokter.kd_dokter', '=', 'jadwal.kd_dokter')
                ->join('poliklinik', 'poliklinik.kd_poli', '=', 'jadwal.kd_poli')
                ->select('jadwal.*', 'dokter.nm_dokter', 'poliklinik.nm_poli')
                ->where('hari_kerja', $hari)
                ->where('jadwal.jam_mulai', '!=', '00:00:00')
                ->where('jadwal.jam_selesai', '!=', '00:00:00')
                ->get();

            foreach ($jadwalsAct as $j) {
                try {
                    $jm = $this->parseJam($j->jam_mulai);
                    $js = $this->parseJam($j->jam_selesai);

                    $status = $this->computeStatus($now, $jm, $js);

                    // Sembunyikan sesi yang sudah berakhir
                    if ($status === 'Selesai') {
                        continue;
                    }

                    $pasList = $this->fetchPasienBelum($j->nm_poli, $j->nm_dokter, 'asc');

                    $current = $this->resolveCurrent($status, $j->nm_poli, $j->nm_dokter);

                    $sessions[] = [
                        'nm_poli' => $j->nm_poli,
                        'nm_dokter' => $j->nm_dokter,
                        'jam_mulai' => $jm,
                        'jam_selesai' => $js,
                        'status' => $status,
                        'current' => $current,
                        'pasien' => $pasList,
                    ];
                } catch (\Throwable $e) {
                    report($e); // skip baris jadwal bermasalah
                }
            }
        } catch (\Throwable $e) {
            report($e);
            $sessions = [];
        }

        return $sessions;
    }
}
