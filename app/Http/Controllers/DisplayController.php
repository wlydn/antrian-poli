<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\PromoContent;

class DisplayController extends Controller
{
    // Halaman utama display: antrian + slider
    public function index(Request $request)
    {
        $nmPoli   = $request->query('poli');   // contoh: "Poli Umum"
        $nmDokter = $request->query('dokter'); // contoh: "dr. Budi"

        // 1) Cek jadwal hari ini dari DB SIK (tabel: jadwal)
        //    Kolom contoh: kd_dokter, hari_kerja (SENIN dst), jam_mulai, jam_selesai, kd_poli, kuota
        $locale = 'id';
        Carbon::setLocale($locale);
        $hari = Str::upper(Carbon::now()->locale($locale)->dayName); // SENIN/SELASA/...

        $jadwal = null;
        try {
            $jadwal = DB::connection('khanza')->table('jadwal')
                ->join('dokter', 'dokter.kd_dokter', '=', 'jadwal.kd_dokter')
                ->join('poliklinik', 'poliklinik.kd_poli', '=', 'jadwal.kd_poli')
                ->select('jadwal.*', 'dokter.nm_dokter', 'poliklinik.nm_poli')
                ->where('hari_kerja', $hari)
                ->when($nmPoli, fn($q) => $q->where('poliklinik.nm_poli', $nmPoli))
                ->when($nmDokter, fn($q) => $q->where('dokter.nm_dokter', $nmDokter))
                ->first();
        } catch (\Throwable $e) {
            $jadwal = null;
        }

        $now = Carbon::now();
        $antrianAktif = false;
        $jamMulai = $jamSelesai = null;

        if ($jadwal) {
            $validJam = ($jadwal->jam_mulai ?? '00:00:00') !== '00:00:00' && ($jadwal->jam_selesai ?? '00:00:00') !== '00:00:00';
            try {
                $jamMulai = Carbon::createFromFormat('H:i:s', $jadwal->jam_mulai);
            } catch (\Throwable $e) {
                $jamMulai = null;
            }
            try {
                $jamSelesai = Carbon::createFromFormat('H:i:s', $jadwal->jam_selesai);
            } catch (\Throwable $e) {
                $jamSelesai = null;
            }
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
        // Fallback: bila tidak ada yang memenuhi kriteria publishable (mis. is_active tidak dicentang
        // atau jadwal tayang di luar waktu), tampilkan semua berdasarkan urutan agar layar tidak kosong.
        if ($promos->isEmpty()) {
            $promos = PromoContent::orderBy('display_order')->get();
        }

        // Bangun daftar sesi (poli-dokter) aktif HARI INI dan ambil antrian masing-masing
        $sessions = [];
        try {
            $now2 = Carbon::now();

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
                    $jm = Carbon::createFromFormat('H:i:s', $j->jam_mulai);
                    $js = Carbon::createFromFormat('H:i:s', $j->jam_selesai);

                    // Status sesi sepanjang hari (tanpa menyaring hanya yang aktif)
                    $status = $now2->lt($jm) ? 'Belum Mulai' : ($now2->gt($js) ? 'Selesai' : 'Dalam Pelayanan');

                    // Sembunyikan sesi yang sudah berakhir
                    if ($status === 'Selesai') {
                        continue;
                    }

                    $pasList = DB::connection('khanza')->table('reg_periksa')
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
                        ->whereDate('reg_periksa.tgl_registrasi', \Carbon\Carbon::today())
                        ->where('poliklinik.nm_poli', $j->nm_poli)
                        ->where('dokter.nm_dokter', $j->nm_dokter)
                        ->where('stts', 'Belum')
                        ->orderBy('reg_periksa.jam_reg', 'asc')
                        ->get()
                        ->all();

                    // Pasien yang sedang dipanggil = antrean terdepan (status 'Belum' paling awal).
                    // Jika belum mulai: paksa null -> ditampilkan "000".
                    // Jika tidak ada 'Belum' lagi: fallback ke nomor terakhir hari ini.
                    if ($status === 'Belum Mulai') {
                        $current = null;
                    } else {
                        $current = $pasList[0] ?? null;
                        if (!$current) {
                            $lastRows = DB::connection('khanza')->table('reg_periksa')
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
                                ->whereDate('reg_periksa.tgl_registrasi', \Carbon\Carbon::today())
                                ->where('poliklinik.nm_poli', $j->nm_poli)
                                ->where('dokter.nm_dokter', $j->nm_dokter)
                                ->orderByRaw('CAST(reg_periksa.no_reg AS UNSIGNED) desc')
                                ->orderBy('reg_periksa.jam_reg', 'desc')
                                ->limit(1)
                                ->get()
                                ->all();
                            $current = $lastRows[0] ?? null;
                        }
                    }

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
                    // skip jadwal bermasalah
                }
            }
        } catch (\Throwable $e) {
            $sessions = [];
        }

        // Tampilkan grid sesi sepanjang hari, tidak tergantung aktif
        $antrianAktif = true;

        // 3) Ambil antrian pasien dari SIK hanya jika jadwal aktif
        $pasien = [];
        if ($antrianAktif) {
            try {
                $pasien = DB::connection('khanza')->table('reg_periksa')
                    ->join('dokter', 'reg_periksa.kd_dokter', '=', 'dokter.kd_dokter')
                    ->join('pasien', 'reg_periksa.no_rkm_medis', '=', 'pasien.no_rkm_medis')
                    ->join('poliklinik', 'reg_periksa.kd_poli', '=', 'poliklinik.kd_poli')
                    ->select('reg_periksa.no_reg', 'reg_periksa.no_rkm_medis', 'pasien.nm_pasien', 'reg_periksa.no_rawat', 'dokter.nm_dokter', 'reg_periksa.jam_reg')
                    ->whereDate('reg_periksa.tgl_registrasi', \Carbon\Carbon::today())
                    ->where('poliklinik.nm_poli', $nmPoli)
                    ->where('dokter.nm_dokter', $nmDokter)
                    ->where('stts', 'Belum')
                    ->orderByRaw('CAST(reg_periksa.no_reg AS UNSIGNED) asc')
                    ->orderBy('reg_periksa.jam_reg', 'asc')
                    ->get()
                    ->all();
            } catch (\Throwable $e) {
                $pasien = [];
            }
        }

        // Hitung current untuk hero pada render awal: jika sudah mulai ambil 'Belum' pertama, jika kosong fallback nomor terakhir.
        $current = null;
        if ($isStartedSelected && $nmPoli && $nmDokter) {
            try {
                $rowsCur = DB::connection('khanza')->table('reg_periksa')
                    ->join('dokter', 'reg_periksa.kd_dokter', '=', 'dokter.kd_dokter')
                    ->join('pasien', 'reg_periksa.no_rkm_medis', '=', 'pasien.no_rkm_medis')
                    ->join('poliklinik', 'reg_periksa.kd_poli', '=', 'poliklinik.kd_poli')
                    ->select('reg_periksa.no_reg', 'reg_periksa.no_rkm_medis', 'pasien.nm_pasien', 'reg_periksa.no_rawat', 'dokter.nm_dokter', 'reg_periksa.jam_reg')
                    ->whereDate('reg_periksa.tgl_registrasi', \Carbon\Carbon::today())
                    ->where('poliklinik.nm_poli', $nmPoli)
                    ->where('dokter.nm_dokter', $nmDokter)
                    ->where('stts', 'Belum')
                    ->orderByRaw('CAST(reg_periksa.no_reg AS UNSIGNED) asc')
                    ->orderBy('reg_periksa.jam_reg', 'asc')
                    ->get()
                    ->all();
                $current = $rowsCur[0] ?? null;
                if (!$current) {
                    $rowsLast = DB::connection('khanza')->table('reg_periksa')
                        ->join('dokter', 'reg_periksa.kd_dokter', '=', 'dokter.kd_dokter')
                        ->join('pasien', 'reg_periksa.no_rkm_medis', '=', 'pasien.no_rkm_medis')
                        ->join('poliklinik', 'reg_periksa.kd_poli', '=', 'poliklinik.kd_poli')
                        ->select('reg_periksa.no_reg', 'reg_periksa.no_rkm_medis', 'pasien.nm_pasien', 'reg_periksa.no_rawat', 'dokter.nm_dokter', 'reg_periksa.jam_reg')
                        ->whereDate('reg_periksa.tgl_registrasi', \Carbon\Carbon::today())
                        ->where('poliklinik.nm_poli', $nmPoli)
                        ->where('dokter.nm_dokter', $nmDokter)
                        ->orderByRaw('CAST(reg_periksa.no_reg AS UNSIGNED) desc')
                        ->orderBy('reg_periksa.jam_reg', 'desc')
                        ->limit(1)
                        ->get()
                        ->all();
                    $current = $rowsLast[0] ?? null;
                }
            } catch (\Throwable $e) {
                $current = null;
            }
        }

        // Oper variabel 'started' ke view agar hero "Nomor Antrian Saat Ini" dapat menampilkan 000 sebelum praktik dimulai
        $started = $isStartedSelected;
        return view('display.index', compact('promos','sessions','pasien','antrianAktif','jadwal','jamMulai','jamSelesai','nmPoli','nmDokter','started','current'));
    }

    // Partial untuk auto-refresh list antrian: kembalikan hanya HTML partial queue
    public function partialQueue(Request $request){
        $locale = 'id';
        Carbon::setLocale($locale);
        $hari = Str::upper(Carbon::now()->locale($locale)->dayName);

        $sessions = [];
        try {
            $now2 = Carbon::now();

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
                    $jm = Carbon::createFromFormat('H:i:s', $j->jam_mulai);
                    $js = Carbon::createFromFormat('H:i:s', $j->jam_selesai);

                    $status = $now2->lt($jm) ? 'Belum Mulai' : ($now2->gt($js) ? 'Selesai' : 'Dalam Pelayanan');

                    // Sembunyikan sesi yang sudah berakhir
                    if ($status === 'Selesai') {
                        continue;
                    }

                    $pasList = DB::connection('khanza')->table('reg_periksa')
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
                        ->whereDate('reg_periksa.tgl_registrasi', \Carbon\Carbon::today())
                        ->where('poliklinik.nm_poli', $j->nm_poli)
                        ->where('dokter.nm_dokter', $j->nm_dokter)
                        ->where('stts', 'Belum')
                        ->orderBy('reg_periksa.jam_reg', 'asc')
                        ->get()
                        ->all();

                    // Pasien yang sedang dipanggil = antrean terdepan (status 'Belum' paling awal).
                    // Jika belum mulai: paksa null -> ditampilkan "000".
                    // Jika tidak ada 'Belum' lagi: fallback ke nomor terakhir hari ini.
                    if ($status === 'Belum Mulai') {
                        $current = null;
                    } else {
                        $current = $pasList[0] ?? null;
                        if (!$current) {
                            $lastRows = DB::connection('khanza')->table('reg_periksa')
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
                                ->whereDate('reg_periksa.tgl_registrasi', \Carbon\Carbon::today())
                                ->where('poliklinik.nm_poli', $j->nm_poli)
                                ->where('dokter.nm_dokter', $j->nm_dokter)
                                ->orderByRaw('CAST(reg_periksa.no_reg AS UNSIGNED) desc')
                                ->orderBy('reg_periksa.jam_reg', 'desc')
                                ->limit(1)
                                ->get()
                                ->all();
                            $current = $lastRows[0] ?? null;
                        }
                    }

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
                    // skip baris jadwal bermasalah
                }
            }
        } catch (\Throwable $e) {
            $sessions = [];
        }

        return view('display.partials._queue', compact('sessions'));
    }

    // Partial untuk menampilkan nomor antrian saat ini (hero). Jika jadwal praktik belum dimulai, tampilkan "000".
    public function partialCurrent(Request $request){
        $nmPoli = $request->query('poli');
        $nmDokter = $request->query('dokter');

        $current = null;
        $started = true; // default: anggap mulai, kecuali bisa dipastikan belum mulai

        try {
            // Cek jadwal hari ini untuk poli/dokter (jika disediakan)
            $locale = 'id';
            Carbon::setLocale($locale);
            $hari = \Illuminate\Support\Str::upper(Carbon::now()->locale($locale)->dayName);

            $jadwal = DB::connection('khanza')->table('jadwal')
                ->join('dokter', 'dokter.kd_dokter', '=', 'jadwal.kd_dokter')
                ->join('poliklinik', 'poliklinik.kd_poli', '=', 'jadwal.kd_poli')
                ->select('jadwal.*', 'dokter.nm_dokter', 'poliklinik.nm_poli')
                ->where('hari_kerja', $hari)
                ->when($nmPoli, fn($q) => $q->where('poliklinik.nm_poli', $nmPoli))
                ->when($nmDokter, fn($q) => $q->where('dokter.nm_dokter', $nmDokter))
                ->first();

            if ($jadwal && $jadwal->jam_mulai !== '00:00:00' && $jadwal->jam_selesai !== '00:00:00') {
                try {
                    $jm = Carbon::createFromFormat('H:i:s', $jadwal->jam_mulai);
                    $js = Carbon::createFromFormat('H:i:s', $jadwal->jam_selesai);
                    $now = Carbon::now();
                    $started = $now->greaterThanOrEqualTo($jm) && $now->lessThanOrEqualTo($js);
                } catch (\Throwable $e) {
                    $started = true; // parsing gagal, jangan blokir tampilan
                }
            }

            // Hanya ambil pasien jika jadwal sudah mulai
            if ($started) {
                $rows = DB::connection('khanza')->table('reg_periksa')
                    ->join('dokter', 'reg_periksa.kd_dokter', '=', 'dokter.kd_dokter')
                    ->join('pasien', 'reg_periksa.no_rkm_medis', '=', 'pasien.no_rkm_medis')
                    ->join('poliklinik', 'reg_periksa.kd_poli', '=', 'poliklinik.kd_poli')
                    ->select('reg_periksa.no_reg', 'reg_periksa.no_rkm_medis', 'pasien.nm_pasien', 'reg_periksa.no_rawat', 'dokter.nm_dokter', 'reg_periksa.jam_reg')
                    ->whereDate('reg_periksa.tgl_registrasi', \Carbon\Carbon::today())
                    ->where('poliklinik.nm_poli', $nmPoli)
                    ->where('dokter.nm_dokter', $nmDokter)
                    ->where('stts', 'Belum')
                    ->orderByRaw('CAST(reg_periksa.no_reg AS UNSIGNED) asc')
                    ->orderBy('reg_periksa.jam_reg', 'asc')
                    ->get()
                    ->all();
                $current = $rows[0] ?? null;
                if (!$current) {
                    $rowsLast = DB::connection('khanza')->table('reg_periksa')
                        ->join('dokter', 'reg_periksa.kd_dokter', '=', 'dokter.kd_dokter')
                        ->join('pasien', 'reg_periksa.no_rkm_medis', '=', 'pasien.no_rkm_medis')
                        ->join('poliklinik', 'reg_periksa.kd_poli', '=', 'poliklinik.kd_poli')
                        ->select('reg_periksa.no_reg', 'reg_periksa.no_rkm_medis', 'pasien.nm_pasien', 'reg_periksa.no_rawat', 'dokter.nm_dokter', 'reg_periksa.jam_reg')
                        ->whereDate('reg_periksa.tgl_registrasi', \Carbon\Carbon::today())
                        ->where('poliklinik.nm_poli', $nmPoli)
                        ->where('dokter.nm_dokter', $nmDokter)
                        ->orderByRaw('CAST(reg_periksa.no_reg AS UNSIGNED) desc')
                        ->orderBy('reg_periksa.jam_reg', 'desc')
                        ->limit(1)
                        ->get()
                        ->all();
                    $current = $rowsLast[0] ?? null;
                }
            } else {
                $current = null; // sebelum mulai: hero akan tampil "000"
            }
        } catch (\Throwable $e) {
            // biarkan $current tetap null
        }

        return view('display.partials._current', compact('current','started'));
    }
}
