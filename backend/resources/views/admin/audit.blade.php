@php
    use App\Support\Time\BusinessDay;
    $tz = BusinessDay::timezone();
@endphp

<x-layouts.app title="Audit">

    <x-page-header
        title="Audit Log"
        subtitle="100 kejadian terbaru. Inilah yang menjawab “siapa yang membatalkan itu”." />

    {{-- Filter aksi — menggeser mendatar, daftar aksinya bisa panjang. --}}
    <div class="flex items-center gap-2 overflow-x-auto pb-4 mb-2
                [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
        <a href="{{ route('admin.audit') }}"
           class="px-3.5 py-1.5 text-xs font-semibold rounded-full whitespace-nowrap transition-colors
                  {{ $action === '' ? 'bg-pitch text-card' : 'bg-inset text-ink-soft hover:bg-track' }}">
            Semua {{ $actions->sum() }}
        </a>
        @foreach ($actions as $nama => $jumlah)
            <a href="{{ route('admin.audit', ['action' => $nama]) }}"
               class="px-3.5 py-1.5 text-xs font-semibold rounded-full whitespace-nowrap transition-colors
                      {{ $action === $nama ? 'bg-pitch text-card' : 'bg-inset text-ink-soft hover:bg-track' }}">
                {{ $nama }} {{ $jumlah }}
            </a>
        @endforeach
    </div>

    <x-card padding="p-4 sm:p-6">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs table-stacked">
                <thead>
                    <tr class="text-ink-soft border-b border-line-soft">
                        <th scope="col" class="py-3 px-3 font-medium">Waktu</th>
                        <th scope="col" class="py-3 px-3 font-medium">Pelaku</th>
                        <th scope="col" class="py-3 px-3 font-medium">Aksi</th>
                        <th scope="col" class="py-3 px-3 font-medium">Objek</th>
                        <th scope="col" class="py-3 px-3 font-medium">Rincian</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line-soft">
                    @forelse ($logs as $log)
                        <tr class="hover:bg-inset/60 transition-colors align-top">
                            <td data-label="Waktu" class="py-3 px-3 text-ink-soft tabular whitespace-nowrap">
                                {{ $log->created_at->timezone($tz)->format('d/m H:i:s') }}
                            </td>
                            <td data-label="Pelaku" class="py-3 px-3">
                                <span class="font-semibold text-ink">{{ $log->actor_name ?? 'Sistem' }}</span>
                                @if ($log->actor_role)
                                    <span class="text-ink-soft">{{ $log->actor_role }}</span>
                                @endif
                            </td>
                            <td data-label="Aksi" class="py-3 px-3">
                                <span class="px-2 py-0.5 rounded-chip text-[10px] font-semibold bg-inset text-ink tabular">
                                    {{ $log->action }}
                                </span>
                            </td>
                            <td data-label="Objek" class="py-3 px-3 text-ink-soft">
                                {{ class_basename($log->subject_type ?? '') ?: '—' }}
                            </td>
                            <td data-label="Rincian" class="py-3 px-3 text-ink-soft">
                                @php
                                    /*
                                     * Hanya field yang BERUBAH yang
                                     * ditampilkan. Menumpahkan seluruh
                                     * `before`/`after` membuat kolom ini
                                     * setinggi layar dan justru
                                     * menyembunyikan satu angka yang dicari.
                                     */
                                    $before = $log->before ?? [];
                                    $after = $log->after ?? [];
                                    $berubah = collect($after)
                                        ->filter(fn ($v, $k) => ! is_array($v) && ($before[$k] ?? null) !== $v)
                                        ->take(4);
                                @endphp

                                @if ($berubah->isEmpty())
                                    —
                                @else
                                    <div class="flex flex-col gap-0.5">
                                        @foreach ($berubah as $kunci => $nilai)
                                            <span class="tabular">
                                                <span class="text-ink-faint">{{ $kunci }}</span>
                                                @if (array_key_exists($kunci, $before))
                                                    <span class="line-through">{{ \Illuminate\Support\Str::limit((string) $before[$kunci], 24) }}</span>
                                                    →
                                                @endif
                                                <span class="font-semibold text-ink">{{ \Illuminate\Support\Str::limit((string) $nilai, 24) }}</span>
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-empty title="Belum ada catatan" icon="search" colspan="5">
                            Login, pembayaran, extend, swap, diskon, dan perubahan harga akan tercatat di sini.
                        </x-empty>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

</x-layouts.app>
