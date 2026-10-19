{{--
    Daftar berkas yang bisa diunduh. Variabel: $files (Attachment / ResolutionProof), $route (attachments.show / proofs.show).
    Tautan menuju route ber-izin, BUKAN ke /storage, karena berkas disimpan di disk privat.
--}}
<ul class="mt-2 flex flex-wrap gap-2">
    @foreach ($files as $file)
        <li>
            <a href="{{ route($route, $file) }}"
               class="inline-flex min-h-11 max-w-full items-center gap-2 rounded-control border border-line bg-surface px-3 text-sm shadow-sm hover:bg-sunken">
                <x-icon :name="str_starts_with($file->mime_type, 'image/') ? 'file' : 'paperclip'" class="size-4 text-accent" />
                <span class="truncate font-semibold">{{ $file->file_name }}</span>
                <span class="shrink-0 text-muted">{{ \App\Support\Format::fileSize($file->file_size) }}</span>
                <x-icon name="download" class="size-4 shrink-0 text-muted" />
            </a>
        </li>
    @endforeach
</ul>
