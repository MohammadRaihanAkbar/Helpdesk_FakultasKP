{{--
    Isi sisi kiri halaman login. Datanya disiapkan class App\View\Components\LoginShowcase.
    Hiasan lengkung kubah + 9 bintang emas (motif logo UIN) digambar dengan SVG inline: tanpa file gambar.
--}}
<div class="relative">
    {{-- Hiasan: hanya tampil di laptop, disembunyikan dari pembaca layar. --}}
    <svg class="pointer-events-none absolute top-2 -left-8 hidden w-[600px] opacity-90 [mask-image:linear-gradient(to_bottom,#000_50%,transparent_72%)] lg:block"
         viewBox="0 0 640 640" aria-hidden="true">
        <defs>
            <linearGradient id="emas" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#D4AE5A"/><stop offset=".5" stop-color="#BB8F32"/><stop offset="1" stop-color="#8C603B"/></linearGradient>
            <linearGradient id="hijau" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#138A5A"/><stop offset="1" stop-color="#0E423E"/></linearGradient>
            <path id="bintang" d="M12 2.5l2.94 5.96 6.56.95-4.75 4.63 1.12 6.54L12 17.5l-5.87 3.08 1.12-6.54L2.5 9.41l6.56-.95z" fill="url(#emas)"/>
        </defs>
        <path d="M110 600V403C110 215 244 140 320 130c76 10 210 85 210 273v197" fill="none" stroke="url(#emas)" stroke-width="2" opacity=".55"/>
        <path d="M150 600V436c0-156 109-216 170-226 61 10 170 70 170 226v164" fill="none" stroke="url(#hijau)" stroke-width="1.5" opacity=".45"/>
        <path d="M190 600V470c0-124 83-170 130-180 47 10 130 56 130 180v130" fill="none" stroke="url(#emas)" stroke-width="1.2" opacity=".35"/>
        @foreach ([[74.6, 171.3, .75], [122.2, 122.9, .75], [179.5, 86.4, .75], [241.5, 61.7, .92], [305, 50, 1.25], [376.5, 61.7, .92], [442.5, 86.4, .75], [499.8, 122.9, .75], [547.4, 171.3, .75]] as [$x, $y, $scale])
            <use href="#bintang" transform="translate({{ $x }} {{ $y }}) scale({{ $scale }})"/>
        @endforeach
    </svg>

    <div class="relative">
        <p class="flex items-center gap-3 text-sm font-semibold text-muted">
            <span class="h-0.5 w-10 rounded-full bg-accent" aria-hidden="true"></span>
            UIN Siber Syekh Nurjati Cirebon
        </p>

        <p class="mt-8 flex items-center gap-2 font-heading text-xs font-extrabold tracking-[.18em] text-accent-ink uppercase lg:mt-52">
            <x-icon name="star" class="size-3.5 fill-current text-accent" />
            Helpdesk Fakultas Terpadu
        </p>
        <h2 class="mt-4 text-3xl leading-[1.05] font-extrabold tracking-[-0.03em] sm:text-4xl xl:text-[56px]">
            Satu pintu untuk setiap <span class="text-gold-gradient">aduan fakultas.</span>
        </h2>
        <p class="mt-5 max-w-xl text-base leading-relaxed text-muted lg:text-lg">
            Laporkan kendala akademik, administrasi, fasilitas, hingga jaringan kampus.
            Tiket Anda diteruskan ke staf yang tepat dan dipantau dengan target SLA yang jelas.
        </p>

        @if ($facultyCodes->isNotEmpty())
            <ul class="mt-6 flex flex-wrap gap-2" aria-label="Fakultas yang terhubung">
                @foreach ($facultyCodes as $code)
                    <li class="rounded-full border border-line bg-surface/80 px-3.5 py-1.5 font-mono text-sm font-semibold text-ink">{{ $code }}</li>
                @endforeach
            </ul>
        @endif

        <dl class="mt-8 grid max-w-xl grid-cols-3 divide-x divide-line">
            <div class="pr-4">
                <dt class="sr-only">Fakultas terhubung</dt>
                <dd class="font-heading text-2xl font-extrabold text-primary sm:text-3xl">{{ $facultyCodes->count() }}</dd>
                <dd class="mt-1 text-sm text-muted" aria-hidden="true">Fakultas terhubung</dd>
            </div>
            <div class="px-4">
                <dt class="sr-only">Jenis layanan</dt>
                <dd class="font-heading text-2xl font-extrabold text-primary sm:text-3xl">{{ $serviceCount }}</dd>
                <dd class="mt-1 text-sm text-muted" aria-hidden="true">Jenis layanan</dd>
            </div>
            <div class="pl-4">
                <dt class="sr-only">SLA tercepat</dt>
                <dd class="font-heading text-2xl font-extrabold text-primary sm:text-3xl">{{ $fastestSla ? $fastestSla.' jam' : '–' }}</dd>
                <dd class="mt-1 text-sm text-muted" aria-hidden="true">SLA tercepat</dd>
            </div>
        </dl>

        <p class="mt-10 text-sm text-muted lg:mt-24">&copy; {{ now()->year }} UIN Siber Syekh Nurjati Cirebon · Helpdesk Fakultas Terpadu</p>
    </div>
</div>
