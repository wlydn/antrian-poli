<!doctype html>
<html>
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Antrian Poliklinik</title>
    <link href="/assets/css/display.css" rel="stylesheet" />
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>

<body x-data="displayApp()" x-init="init()">
    <div class="grid">
        <div class="panel">
            <div class="flex justify-between align-center">
                <div>
                    <div class="text-center">
                        <div class="big">ANTRIAN POLIKLINIK</div>
                    </div>
                </div>
                <div class="clock">
                    <div class="date" x-text="nowDate"></div>
                    <div class="time" x-text="nowTime"></div>
                </div>
            </div>

            <hr class="hr-light" />

            @if (!$antrianAktif)
                <div class="center h-70vh opacity-80">
                    <div class="text-center">
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
                        <img src="{{ Storage::url($p->file_path) }}" alt="{{ $p->title }}" />
                    @else
                        <video src="{{ Storage::url($p->file_path) }}" autoplay muted playsinline></video>
                    @endif
                </div>
            @empty
                <div class="slide active center">Tidak ada konten promosi aktif</div>
            @endforelse
        </div>
    </div>

    <div class="footer-full">
        <div class="ticker" aria-label="pengumuman berjalan">
            <div class="ticker__track">
                <span>{{ $ticker }}</span>
            </div>
        </div>
        <div class="footer-brand" aria-label="Made with love">
            Made with <span class="heart" aria-hidden="true">❤️</span> by IT Rayhan
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
                                .catch(() => { /* ignore transient errors */ });
                        }, 15000);
                    @endif
                },
                startSlider() {
                    const slides = [...document.querySelectorAll('.slide')];
                    if (slides.length === 0) return;

                    let idx = 0;
                    let timer = null;

                    const show = (i) => {
                        // Toggle active slide
                        slides.forEach((el, j) => el.classList.toggle('active', j === i));

                        // Cleanup previous video's playback
                        const prev = slides[(i - 1 + slides.length) % slides.length].querySelector('video');
                        if (prev) {
                            try { prev.pause(); prev.currentTime = 0; prev.onended = null; } catch(e) {}
                        }

                        const slide = slides[i];
                        const video = slide.querySelector('video');

                        if (timer) { clearTimeout(timer); timer = null; }

                        if (video) {
                            // Play video until finished, then advance. No loop.
                            try {
                                video.loop = false;
                                video.muted = true;
                                video.currentTime = 0;
                                video.play().catch(() => {});
                            } catch (e) {}

                            video.onended = () => {
                                video.onended = null;
                                show((i + 1) % slides.length);
                            };

                            // Safety fallback if 'ended' doesn't fire (corrupt metadata, etc.)
                            const fallbackMs = Math.max(((video.duration || 0) * 1000) + 500, 30000);
                            timer = setTimeout(() => {
                                try { video.onended = null; video.pause(); } catch (e) {}
                                show((i + 1) % slides.length);
                            }, fallbackMs);
                        } else {
                            // Image: show for 8s then advance
                            timer = setTimeout(() => show((i + 1) % slides.length), 8000);
                        }
                    };

                    show(idx);
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
