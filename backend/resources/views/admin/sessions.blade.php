<x-layouts.app title="Sesi" :poll="10">

    <x-page-header title="Sesi" subtitle="50 sesi terbaru. Angka pada chip dihitung dari seluruh riwayat." />

    {{-- ── Filter status ───────────────────────────────────────────
         Menggeser mendatar di layar sempit, bukan membungkus jadi tiga
         baris yang mendorong tabel ke bawah lipatan. --}}
    <div class="flex items-center gap-2 overflow-x-auto pb-4 mb-2
                [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
        <a href="{{ route('admin.sessions') }}"
           class="px-3.5 py-1.5 text-xs font-semibold rounded-full whitespace-nowrap transition-colors
                  {{ $status === '' ? 'bg-pitch text-card' : 'bg-inset text-ink-soft hover:bg-track' }}">
            Semua {{ $counts->sum() }}
        </a>

        @foreach (\App\Enums\SessionStatus::cases() as $case)
            @continue(($counts[$case->value] ?? 0) === 0)
            <a href="{{ route('admin.sessions', ['status' => $case->value]) }}"
               class="px-3.5 py-1.5 text-xs font-semibold rounded-full whitespace-nowrap transition-colors
                      {{ $status === $case->value ? 'bg-pitch text-card' : 'bg-inset text-ink-soft hover:bg-track' }}">
                {{ $case->value }} {{ $counts[$case->value] }}
            </a>
        @endforeach
    </div>

    <x-card>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs table-stacked">
                <thead>
                    <tr class="text-ink-soft border-b border-line-soft">
                        <th scope="col" class="py-3 px-3 font-medium">Kode</th>
                        <th scope="col" class="py-3 px-3 font-medium">Station</th>
                        <th scope="col" class="py-3 px-3 font-medium">Customer</th>
                        <th scope="col" class="py-3 px-3 font-medium">Paket</th>
                        <th scope="col" class="py-3 px-3 font-medium">Mode</th>
                        <th scope="col" class="py-3 px-3 font-medium">Mulai</th>
                        <th scope="col" class="py-3 px-3 font-medium">Status</th>
                        <th scope="col" class="py-3 px-3 font-medium md:text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line-soft">
                    @forelse ($sessions as $s)
                        <tr class="hover:bg-inset/60 transition-colors">
                            <td data-label="Kode" class="py-3 px-3">
                                <a href="{{ route('admin.session', $s) }}"
                                   class="font-semibold text-brand-600 hover:underline tabular">{{ $s->code }}</a>
                            </td>
                            <td data-label="Station" class="py-3 px-3">
                                <span class="font-semibold text-ink">{{ $s->station?->code }}</span>
                                <span class="text-ink-soft">{{ $s->station?->stationType?->name }}</span>
                            </td>
                            <td data-label="Customer" class="py-3 px-3 text-ink font-medium">{{ $s->customerLabel() }}</td>
                            <td data-label="Paket" class="py-3 px-3 text-ink-soft">{{ $s->package_name }}</td>
                            <td data-label="Mode" class="py-3 px-3 text-ink-soft">{{ $s->mode->value }}</td>
                            <td data-label="Mulai" class="py-3 px-3 text-ink-soft tabular">
                                {{ $s->started_at?->timezone(\App\Support\Time\BusinessDay::timezone())->format('d/m H:i') ?? '—' }}
                            </td>
                            <td data-label="Status" class="py-3 px-3"><x-status-pill :status="$s->status->value" /></td>
                            <td data-label="Total" class="py-3 px-3 md:text-right font-bold text-ink">
                                <x-rupiah :value="$totals[$s->id] ?? 0" />
                            </td>
                        </tr>
                    @empty
                        <x-empty title="Belum ada sesi" icon="play" colspan="8">
                            Sesi yang dibuka dari tablet operator akan muncul di sini.
                        </x-empty>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

</x-layouts.app>
