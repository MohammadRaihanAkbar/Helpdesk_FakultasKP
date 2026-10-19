{{--
    Daftar pesan thread (dipakai halaman pelapor dan staff).

    Variabel:
      $comments    koleksi Comment (sudah difilter: pelapor tidak pernah menerima catatan internal)
      $viewer      user yang sedang login (pesannya sendiri ditulis "Anda")
      $maskUserId  (opsional) id pelapor yang identitasnya harus disamarkan — kategori sensitif
                   "Integritas Dosen" dilihat staff yang belum mengambil tiket (TicketPolicy::viewReporterIdentity)
--}}
<ol class="space-y-3">
    @foreach ($comments as $comment)
        @php
            $maskUserId ??= null;
            $mine = $comment->user_id === $viewer->id;
            $fromStaff = $comment->user->isStaff();
            $author = match (true) {
                $mine => 'Anda',
                $maskUserId !== null && $comment->user_id === $maskUserId => 'Pelapor (identitas dirahasiakan)',
                default => $comment->user->name,
            };
        @endphp
        <li @class([
            'rounded-card border px-4 py-3',
            'border-accent/40 bg-accent-soft' => $comment->is_internal_note,
            'border-diproses-dot/30 bg-diproses-bg' => ! $comment->is_internal_note && $fromStaff,
            'border-line bg-surface' => ! $comment->is_internal_note && ! $fromStaff,
            'sm:ml-10' => $mine,
            'sm:mr-10' => ! $mine,
        ])>
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <span class="font-bold text-ink">{{ $author }}</span>
                @if ($comment->is_internal_note)
                    <x-ui.badge tone="warning">Catatan Internal</x-ui.badge>
                @elseif ($fromStaff)
                    <x-ui.badge tone="info">Staff</x-ui.badge>
                @endif
                <span class="text-xs text-muted">{{ $comment->created_at->translatedFormat('j M Y, H:i') }}</span>
            </div>
            <p class="mt-1.5 text-[15px] leading-relaxed whitespace-pre-line text-ink">{{ $comment->body }}</p>
            @if ($comment->attachments->isNotEmpty())
                @include('enduser.tickets._files', ['files' => $comment->attachments, 'route' => 'attachments.show'])
            @endif
        </li>
    @endforeach
</ol>
