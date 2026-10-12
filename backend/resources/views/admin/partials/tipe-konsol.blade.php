{{--
    Tipe konsol — DEC-019 (pembawa tarif), DEC-048 (bisa ditambah & diubah).

    Diletakkan di ATAS daftar station, bukan di halaman sendiri: tipe adalah
    hal pertama yang harus ada sebelum station baru bisa dibuat, dan halaman
    terpisah membuat urutan itu tidak terlihat.
--}}

<x-card class="mb-6" padding="p-4 sm:p-6">
    <div class="flex items-start justify-between gap-3 flex-wrap mb-4">
        <div>
            <h2 class="text-base font-bold text-ink">Tipe Konsol</h2>
            <p class="text-xs text-ink-soft mt-0.5">
                Namanya bebas — PS3, PS5 Pro, VIP Room, Simulator Balap.
                Tarif menempel ke tipe, bukan ke station.
            </p>
        </div>
        <x-badge tone="neutral">{{ $tipe->count() }} tipe</x-badge>
    </div>

    <div class="grid grid-cols-[repeat(auto-fit,minmax(16rem,1fr))] gap-3">
        @forelse ($tipe as $t)
            <div class="rounded-block border border-line p-4 flex flex-col gap-3">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-sm font-bold text-ink truncate">{{ $t->name }}</p>
                        <p class="text-[11px] text-ink-soft mt-0.5 tabular">
                            {{ $t->stations_count }} station · urutan {{ $t->sort_order }}
                        </p>
                    </div>
                    <x-badge :tone="$t->is_active ? 'up' : 'neutral'">
                        {{ $t->is_active ? 'Aktif' : 'Nonaktif' }}
                    </x-badge>
                </div>

                @if ($bolehOwner)
                    <x-action-panel title="Ubah tipe">
                        <form method="POST" action="{{ route('admin.station-types.update', $t) }}" class="space-y-3 mt-3">
                            @csrf
                            @method('PATCH')

                            <x-field name="name" label="Nama" :value="$t->name" required />
                            <x-field name="sort_order" label="Urutan tampil" type="number" :value="$t->sort_order" />

                            {{-- Checkbox yang tidak dicentang tidak dikirim
                                 browser; controller membacanya eksplisit. --}}
                            <label class="flex items-center gap-2 text-xs text-ink-soft cursor-pointer">
                                <input type="checkbox" name="is_active" value="1" @checked($t->is_active)
                                       class="w-4 h-4 rounded accent-brand-600">
                                Aktif — muncul sebagai pilihan saat menambah station
                            </label>

                            @if ($t->stations_count > 0)
                                <p class="text-[11px] text-warning leading-relaxed">
                                    Dipakai {{ $t->stations_count }} station. Menonaktifkannya ditolak
                                    selama masih ada station aktif yang memakainya — kalau tidak,
                                    station itu akan terlihat kosong tapi tidak punya satu paket pun.
                                </p>
                            @endif

                            <x-action-submit>Simpan tipe</x-action-submit>
                        </form>
                    </x-action-panel>
                @endif
            </div>
        @empty
            <div class="col-span-full">
                <x-empty title="Belum ada tipe konsol" icon="tv">
                    Tambahkan satu dulu — station dan paket keduanya menempel ke tipe.
                </x-empty>
            </div>
        @endforelse

        @if ($bolehOwner)
            <div class="rounded-block border border-dashed border-line p-4">
                <x-action-panel title="Tambah tipe konsol" :open="$tipe->isEmpty()">
                    <form method="POST" action="{{ route('admin.station-types.store') }}" class="space-y-3 mt-3">
                        @csrf
                        <x-field name="name" label="Nama tipe" placeholder="PS5 Pro" required />
                        <x-field name="sort_order" label="Urutan tampil" type="number" value="0" />
                        <p class="text-[11px] text-ink-soft leading-relaxed">
                            Setelah dibuat, isi paketnya di
                            <a href="{{ route('admin.pricing') }}" class="font-semibold text-brand-600 hover:underline">Harga &amp; Paket</a>.
                            Tipe tanpa paket tidak bisa dipakai memulai sesi.
                        </p>
                        <x-action-submit>Tambah tipe</x-action-submit>
                    </form>
                </x-action-panel>
            </div>
        @endif
    </div>
</x-card>
