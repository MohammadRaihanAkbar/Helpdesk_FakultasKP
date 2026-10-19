{{--
    Banner hijau sambutan di atas setiap dashboard (mockup design/a-dashboard.png):
    tanggal, "Selamat datang, <nama>!" (nama berwarna emas), kalimat ringkasan, dan tombol opsional.

    Pemakaian:
      <x-dashboard-greeting :user="$user">
          Anda memiliki <b>2 tiket menunggu</b>.               <- ringkasan dari data asli (opsional)
          <x-slot:action> <x-ui.button variant="cta">...</x-ui.button> </x-slot:action>
      </x-dashboard-greeting>

    Tanpa isi slot, banner menampilkan peran, jenis pelapor, dan fakultas sebagai badge.
--}}
@props(['user'])
@php
    // Sapaan memakai nama depan tanpa gelar di depan (Dr., Prof., ...).
    $firstName = \Illuminate\Support\Str::of($user->name)
        ->replaceMatches('/^((Dr|Prof|Ir|H)\.\s*)+/i', '')
        ->explode(' ')
        ->first();
@endphp

<section {{ $attributes->merge(['class' => 'relative mb-4 overflow-hidden rounded-panel bg-brand px-5 py-5 text-on-primary shadow-pop md:mb-5 md:px-8 md:py-6']) }}>
    {{-- Hiasan bintang emas tipis (motif logo), tanpa gambar. --}}
    <div class="pointer-events-none absolute top-3 right-6 flex gap-4 text-accent opacity-25 md:right-80" aria-hidden="true">
        <x-icon name="star" class="size-5 fill-current" />
        <x-icon name="star" class="mt-4 size-4 fill-current" />
        <x-icon name="star" class="size-6 fill-current" />
    </div>

    <div class="relative flex flex-col gap-5 md:flex-row md:items-center md:gap-8">
        <div class="min-w-0 flex-1">
            <p class="flex items-center gap-2 text-sm font-semibold text-on-primary/85">
                <x-icon name="calendar" class="size-4" />
                {{ now()->translatedFormat('l, j F Y') }}
            </p>
            <h1 class="mt-1.5 text-2xl font-extrabold md:text-3xl">Selamat datang, <span class="text-cream">{{ $firstName }}</span>!</h1>

            @if ($slot->isNotEmpty())
                <p class="mt-1.5 max-w-2xl text-[15px] text-on-primary/90 [&_b]:text-on-primary">{{ $slot }}</p>
            @else
                <div class="mt-3 flex flex-wrap items-center gap-2 text-sm">
                    <span class="rounded-full bg-on-primary/15 px-3 py-1 font-semibold">{{ $user->role->label() }}</span>
                    @if ($user->user_type)
                        <span class="rounded-full bg-on-primary/15 px-3 py-1 font-semibold">{{ $user->user_type->label() }}</span>
                    @endif
                    @if ($user->faculty)
                        <span class="rounded-full bg-on-primary/15 px-3 py-1 font-semibold">{{ $user->faculty->name }}</span>
                    @endif
                </div>
            @endif
        </div>

        @isset($action)
            <div class="shrink-0">{{ $action }}</div>
        @endisset
    </div>
</section>
