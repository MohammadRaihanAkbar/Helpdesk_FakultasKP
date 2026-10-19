{{--
    Notifikasi in-app (/notifications) — F-NTF-05/06. Saat ini berisi teguran Ping dari Admin untuk Staff.
    Klik notifikasi = tandai dibaca + buka tiketnya (PATCH /notifications/{id}/read).
--}}
<x-layouts.app title="Notifikasi">
    <x-page-header title="Notifikasi" :description="$unread ? $unread.' notifikasi belum dibaca.' : 'Semua notifikasi sudah dibaca.'">
        @if ($unread)
            <x-slot:action>
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    @method('PATCH')
                    <x-ui.button type="submit" variant="secondary" icon="circle-check">Tandai semua dibaca</x-ui.button>
                </form>
            </x-slot:action>
        @endif
    </x-page-header>

    <x-ui.card class="p-0! md:p-0!">
        @if ($notifications->isEmpty())
            <div class="p-5">
                <x-ui.empty-state icon="bell" title="Belum ada notifikasi">
                    Teguran (Ping) dari Admin Fakultas dan pemberitahuan lain akan muncul di sini.
                </x-ui.empty-state>
            </div>
        @else
            <ul class="divide-y divide-line">
                @foreach ($notifications as $item)
                    <li @class(['bg-menunggu-bg/40' => ! $item->is_read])>
                        <form method="POST" action="{{ route('notifications.read', $item->id) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="flex w-full items-start gap-3 px-4 py-4 text-left hover:bg-sunken/60 md:px-5">
                                <span @class(['mt-0.5 grid size-9 shrink-0 place-items-center rounded-control', 'bg-menunggu-bg text-menunggu-fg' => ! $item->is_read, 'bg-sunken text-muted' => $item->is_read])>
                                    <x-icon name="ping" class="size-4" />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="flex flex-wrap items-center gap-2">
                                        <span class="font-bold text-ink">{{ $item->subject }}</span>
                                        @unless ($item->is_read)
                                            <x-ui.badge tone="warning">Baru</x-ui.badge>
                                        @endunless
                                    </span>
                                    <span class="mt-1 block text-sm whitespace-pre-line text-muted">{{ $item->message }}</span>
                                    <span class="mt-1 block text-xs text-muted">{{ $item->sent_at?->translatedFormat('j M Y, H:i') }} · {{ $item->sent_at?->diffForHumans() }}</span>
                                </span>
                                @if ($item->ticket_id)
                                    <x-icon name="chevron-right" class="mt-2 size-4 text-muted" />
                                @endif
                            </button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>

    <div class="mt-4">{{ $notifications->links() }}</div>
</x-layouts.app>
