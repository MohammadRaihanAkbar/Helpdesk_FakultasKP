{{--
    Tabel performa per Staff (US-15, PRD 10.4): tiket ditangani, selesai/ditolak, rata-rata waktu penyelesaian,
    SLA compliance. Variabel: $rows dari TicketMetrics::byStaff().
    "Ditangani" = tiket pada periode yang diambil staff tsb (status apa pun setelah diambil).
    Waktu penyelesaian & compliance hanya dari tiket Selesai (tiket Ditolak tidak dinilai SLA, PRD 8.2).
    [M6] $detailed = true (dashboard Dekan "hasil kerja"): tambah kolom Masih Diproses dan rata-rata CSAT.
--}}
@php
    use App\Support\Format;

    $detailed ??= false;
@endphp

@if (empty($rows))
    <div class="px-4 pb-4 md:px-5 md:pb-5"><p class="text-sm text-muted">Belum ada Staff di fakultas ini.</p></div>
@else
    <div class="overflow-x-auto border-t border-line">
        <table class="w-full min-w-[40rem] text-sm">
            <thead class="bg-sunken/70 text-left text-xs font-bold text-muted uppercase">
                <tr>
                    <th scope="col" class="px-4 py-2.5 md:px-5">Staff</th>
                    <th scope="col" class="px-3 py-2.5 text-right">Ditangani</th>
                    <th scope="col" class="px-3 py-2.5 text-right">Selesai</th>
                    <th scope="col" class="px-3 py-2.5 text-right">Ditolak</th>
                    @if ($detailed)
                        <th scope="col" class="px-3 py-2.5 text-right">Masih diproses</th>
                    @endif
                    <th scope="col" class="px-3 py-2.5 text-right">Rata-rata penyelesaian</th>
                    <th scope="col" @class(['py-2.5 text-right', 'px-4 md:px-5' => ! $detailed, 'px-3' => $detailed])>Ketepatan Waktu</th>
                    @if ($detailed)
                        <th scope="col" class="px-4 py-2.5 text-right md:px-5">Rata-rata CSAT</th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @foreach ($rows as $row)
                    <tr>
                        <th scope="row" class="min-w-44 px-4 py-2.5 text-left font-semibold text-ink md:px-5">
                            {{ $row['staff']->name }}
                            @if (! $row['staff']->is_active)
                                <span class="text-xs font-normal text-ditolak-fg">(nonaktif)</span>
                            @endif
                        </th>
                        <td class="px-3 py-2.5 text-right font-mono">{{ $row['handled'] }}</td>
                        <td class="px-3 py-2.5 text-right font-mono">{{ $row['selesai'] }}</td>
                        <td class="px-3 py-2.5 text-right font-mono">{{ $row['ditolak'] }}</td>
                        @if ($detailed)
                            <td class="px-3 py-2.5 text-right font-mono">{{ $row['diproses'] }}</td>
                        @endif
                        <td class="px-3 py-2.5 text-right">{{ Format::seconds($row['avg_resolution_seconds']) }}</td>
                        <td @class([
                            'py-2.5 text-right font-mono font-semibold',
                            'px-4 md:px-5' => ! $detailed,
                            'px-3' => $detailed,
                            'text-selesai-fg' => $row['sla_rate'] !== null && $row['sla_rate'] >= 85,
                            'text-ditolak-fg' => $row['sla_rate'] !== null && $row['sla_rate'] < 85,
                            'text-muted' => $row['sla_rate'] === null,
                        ])>{{ Format::percent($row['sla_rate']) }}</td>
                        @if ($detailed)
                            <td class="px-4 py-2.5 text-right font-mono md:px-5" title="{{ $row['rated'] }} rating">
                                {{ $row['csat'] === null ? '–' : number_format($row['csat'], 1, ',', '.').' / 5' }}
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
