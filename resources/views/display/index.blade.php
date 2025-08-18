<!doctype html>
<html>

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Display Antrian Poliklinik</title>
    <link href="/assets/css/display.css" rel="stylesheet" />
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        :root {
            --radius: 16px
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box
        }

        html,
        body {
            height: 100%;
            margin: 0;
            overflow: hidden
        }

        body {
            font-family: system-ui, Segoe UI, Roboto, Arial, sans-serif;
            background: #ffffff;
            color: #111
        }

        .grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 16px;
            height: calc(100vh - 56px);
            padding: 8px;
            align-items: stretch
        }

        .panel {
            background: #ffffff;
            border: 0px solid #e5e7eb;
            border-radius: var(--radius);
            padding: 8px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            height: 100%
        }

        .big {
            font-size: 36px;
            font-weight: 800
        }

        .muted {
            opacity: .8
        }

        .clock {
            text-align: right;
            line-height: 1.1
        }

        .clock .date {
            font-size: 16px;
            opacity: .9
        }

        .clock .time {
            font-size: 36px;
            font-weight: 800;
            letter-spacing: 1px
        }

        hr { margin: 4px 0 }

        /* Queue layout: sejajar ke samping (4 per baris) */
        #queue {
            flex: 1;
            min-height: 0;
            overflow: hidden;
        }
        #queue .queue-grid{
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 4px;
            align-content: start;
        }
        #queue .queue-grid > .queue-item{
            width: 100%;
        }

        .queue-item {
            display: flex;
            flex-direction: column;
            gap: 3px;
            padding: 3px;
            border: 1px solid #b7b7b7;
            border-radius: calc(var(--radius) - 6px);
            background: #e2e5e6
        }

        .queue-item .title {
            font-weight: 800;
            font-size: 16px;
            padding: 3px 6px;
            border-radius: 10px;
            color: #0073df;
            word-break: break-word;
            hyphens: auto;
        }

        .queue-item .subtitle {
            opacity: .85;
            font-size: 11px;
        }

        .queue-item .nowName {
            opacity: .85;
            font-size: 14px;
        }

        .queue-item .now {
            font-size: 40px;
            font-weight: 900;
            letter-spacing: 2px
        }

        .queue-item .next {
            font-size: 14px;
            opacity: .85
        }

        /* Slider area with inner padding and rounded media */
        .slider {
            height: 100%;
            position: relative;
            padding: 0
        }

        .slide {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity .8s
        }

        .slide.active {
            opacity: 1
        }

        .slide img,
        .slide video {
            width: 100%;
            height: 100%;
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            border-radius: var(--radius);
            overflow: hidden
        }

        /* Footer bar (full width) + text berjalan */
        .footer-full {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            background: #f8fafc;
            border-top: 1px solid #e5e7eb;
            z-index: 50;
            height: 56px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 12px
        }

        .footer-full .ticker {
            position: relative;
            width: 100%;
            overflow: hidden
        }

        .footer-full .ticker__track {
            display: inline-block;
            white-space: nowrap;
            will-change: transform;
            animation: ticker-scroll 35s linear infinite;
            color: #111;
            padding-left: 100%;
        }

        @keyframes ticker-scroll {
            0% {
                transform: translateX(0)
            }

            100% {
                transform: translateX(-100%)
            }
        }
    </style>
</head>

<body x-data="displayApp()" x-init="init()">
    <div class="grid">
        <div class="panel">
            <div style="display:flex;justify-content:space-between;align-items:center">
                <div>
                    <div style="text-align:center">
                        <div class="big">ANTRIAN POLIKLINIK</div>
                    </div>
                    {{-- <div class="muted">Sesi aktif hari ini: {{ isset($sessions) ? count($sessions) : 0 }}</div> --}}
                </div>
                <div class="clock">
                    <div class="date" x-text="nowDate"></div>
                    <div class="time" x-text="nowTime"></div>
                </div>
            </div>
            <hr style="border-color:#e5e7eb" />
            @if (!$antrianAktif)
                <div style="display:flex;height:70vh;align-items:center;justify-content:center;opacity:.8">
                    <div>
                        <div class="big">Pelayanan belum/ tidak berlangsung</div>
                        <div>Datang kembali pada jam praktik yang ditentukan.</div>
                    </div>
                </div>
            @else
                <div id="queue" x-html="queueHtml"></div>
            @endif
        </div>

        <div class="panel slider" x-init="startSlider()">
            @forelse($promos as $i=>$p)
                <div class="slide {{ $i === 0 ? 'active' : '' }}">
                    @if ($p->media_type === 'image')
                        <img src="{{ \Illuminate\Support\Facades\Storage::url($p->file_path) }}"
                            alt="{{ $p->title }}" />
                    @else
                        <video src="{{ \Illuminate\Support\Facades\Storage::url($p->file_path) }}" autoplay muted loop
                            playsinline></video>
                    @endif
                </div>
            @empty
                <div class="slide active" style="display:flex;align-items:center;justify-content:center">Tidak ada
                    konten promosi aktif</div>
            @endforelse
        </div>
    </div>

  <div class="footer-full">
    @php
      // Static text berjalan di footer; dapat diubah lewat .env: FOOTER_TICKER_TEXT
      $ticker = env('FOOTER_TICKER_TEXT');
    @endphp
    <div class="ticker" aria-label="pengumuman berjalan">
      <div class="ticker__track">
        <span>{{ $ticker }}</span>
      </div>
    </div>
  </div>

    <script>
        function displayApp() {
            return {
                now: '',
                queueHtml: `@include('display.partials._queue')`,
                init() {
                    this.tick();
                    setInterval(() => this.tick(), 1000);
                    // Auto refresh queue setiap 15 detik (ringan)
                    @if ($antrianAktif)
                        setInterval(() => {
                            fetch(`{{ route('display.partial.queue') }}`)
                                .then(r => r.text())
                                .then(html => {
                                    // Response adalah partial Blade ('display.partials._queue') tanpa wrapper #queue
                                    this.queueHtml = html;
                                })
                                .catch(() => {
                                    /* ignore transient errors */ });
                        }, 15000);
                    @endif
                },
                startSlider() {
                    let idx = 0;
                    const slides = [...document.querySelectorAll('.slide')];
                    setInterval(() => {
                        if (slides.length <= 1) return;
                        slides[idx].classList.remove('active');
                        idx = (idx + 1) % slides.length;
                        slides[idx].classList.add('active');
                    }, 8000);
                },
                tick() {
                    const d = new Date();
                    this.nowDate = d.toLocaleDateString('id-ID', {
                        day: '2-digit',
                        month: 'long',
                        year: 'numeric'
                    });
                    const hh = String(d.getHours()).padStart(2, '0');
                    const mm = String(d.getMinutes()).padStart(2, '0');
                    const ss = String(d.getSeconds()).padStart(2, '0');
                    this.nowTime = `${hh}:${mm}:${ss}`;
                }
            }
        }
    </script>
</body>

</html>
