<x-layouts.app title="Customer">

    <x-page-header title="Customer & Member" subtitle="100 nama pertama. Pakai pencarian untuk yang lain.">
        <x-slot:actions>
            <form method="GET" action="{{ route('admin.customers') }}" class="relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-ink-faint">
                    <x-icon name="search" class="w-3.5 h-3.5" />
                </span>
                <input type="search" name="q" value="{{ $q }}"
                       placeholder="Cari nama atau nomor HP..."
                       class="w-full sm:w-64 pl-9 pr-4 py-1.5 bg-card border border-line rounded-full
                              text-xs text-ink placeholder-ink-soft
                              focus:outline-none focus:border-brand-600 transition-all">
            </form>
        </x-slot:actions>
    </x-page-header>

    {{-- ── Perbaiki data customer — DEC-049 ────────────────────────
         Muncul menggantikan form tambah saat ada `?edit=`. Dua form
         sekaligus di satu halaman membuat orang mengisi yang salah. --}}
    @if ($editing)
        <x-card id="ubah" class="mb-4 border-brand-600">
            <div class="flex items-start justify-between gap-3 flex-wrap mb-4">
                <div>
                    <h2 class="text-base font-bold text-ink">Perbaiki Data Customer</h2>
                    <p class="text-xs text-ink-soft mt-0.5">
                        Mengubah nama atau nomor <span class="font-semibold text-ink">tidak</span>
                        memindahkan saldo, membership, atau riwayat sesi — semuanya menempel ke
                        catatan ini, bukan ke namanya.
                    </p>
                </div>
                <a href="{{ route('admin.customers', ['q' => $q]) }}"
                   class="px-3 py-1.5 text-[11px] font-semibold rounded-full text-ink-soft bg-inset
                          hover:bg-line-soft transition-colors shrink-0">
                    Batal
                </a>
            </div>

            <form method="POST" action="{{ route('admin.customers.update', ['customer' => $editing, 'q' => $q]) }}"
                  class="grid grid-cols-[repeat(auto-fit,minmax(11rem,1fr))] gap-3 items-end">
                @csrf
                @method('PATCH')

                <x-field name="name" label="Nama" :value="$editing->name" required />
                <x-field name="phone" label="No. HP (opsional)" :value="$editing->phone"
                         placeholder="0812-3456-7890" />
                <x-field name="note" label="Catatan (opsional)" :value="$editing->note" />

                <button type="submit"
                        class="px-4 py-2 text-xs font-semibold rounded-full text-card bg-brand-600
                               hover:bg-brand-700 shadow-brand transition-colors">
                    Simpan perubahan
                </button>
            </form>

            <p class="text-[11px] text-ink-soft mt-3">
                Nilai lamanya tercatat di
                <a href="{{ route('admin.audit', ['action' => 'customer.updated']) }}"
                   class="font-semibold text-brand-600 hover:underline">Audit → customer.updated</a>,
                jadi perubahan yang keliru masih bisa dikembalikan.
            </p>
        </x-card>
    @endif

    {{-- ── Daftar customer baru ────────────────────────────────────
         Operator juga bisa dari tablet (DEC-027). Yang di sini untuk kasus
         lain: member yang mendaftar lewat telepon, atau yang datanya
         telanjur ditulis di kertas. --}}
    <x-card class="mb-4 {{ $editing ? 'opacity-60' : '' }}">
        <h2 class="text-base font-bold text-ink mb-4">Tambah Customer</h2>

        <form method="POST" action="{{ route('admin.customers.store') }}"
              class="grid grid-cols-[repeat(auto-fit,minmax(11rem,1fr))] gap-3 items-end">
            @csrf
            <x-field name="name" label="Nama" placeholder="Budi" required />
            <x-field name="phone" label="No. HP (opsional)" placeholder="0812-3456-7890" />
            <x-field name="note" label="Catatan (opsional)" />

            <label class="flex items-center gap-2 pb-2">
                <input type="checkbox" name="jadikan_member" value="1"
                       class="w-4 h-4 rounded-chip accent-brand-600">
                <span class="text-xs font-semibold text-ink">Langsung jadikan member</span>
            </label>

            <button type="submit"
                    class="px-4 py-2 text-xs font-semibold rounded-full text-card bg-brand-600 hover:bg-brand-700 shadow-brand transition-colors">
                Tambah
            </button>
        </form>

        <p class="text-[11px] text-ink-soft mt-3">
            Nomor HP opsional, tapi wajib kalau struk mau dikirim lewat WhatsApp. Biaya member Rp {{ number_format(config('billing.membership_fee'), 0, ',', '.') }} (DEC-029).
        </p>
    </x-card>

    <x-card>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs table-stacked">
                <thead>
                    <tr class="text-ink-soft border-b border-line-soft">
                        <th scope="col" class="py-3 px-3 font-medium">Nama</th>
                        <th scope="col" class="py-3 px-3 font-medium">No. HP</th>
                        <th scope="col" class="py-3 px-3 font-medium">Member</th>
                        <th scope="col" class="py-3 px-3 font-medium md:text-right">Sesi</th>
                        <th scope="col" class="py-3 px-3 font-medium md:text-right">Saldo</th>
                        <th scope="col" class="py-3 px-3 font-medium md:text-right"><span class="sr-only">Aksi</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line-soft">
                    @forelse ($customers as $c)
                        <tr class="hover:bg-inset/60 transition-colors">
                            <td data-label="Nama" class="py-3 px-3 font-semibold text-ink">{{ $c->name }}</td>
                            <td data-label="No. HP" class="py-3 px-3 text-ink-soft tabular">{{ $c->phone ?? '—' }}</td>
                            <td data-label="Member" class="py-3 px-3">
                                @if ($c->membership?->is_active)
                                    <x-badge tone="brand">{{ $c->membership->tier ?? 'Member' }}</x-badge>
                                @else
                                    <span class="text-ink-soft">Bukan member</span>
                                @endif
                            </td>
                            <td data-label="Sesi" class="py-3 px-3 md:text-right text-ink tabular">{{ $c->sessions_count }}</td>
                            <td data-label="Saldo" class="py-3 px-3 md:text-right font-bold text-ink">
                                {{--
                                    Saldo lahir dari sisa waktu Prepaid yang
                                    hangus milik member (DEC-024/026) — BUKAN
                                    dari top-up, yang belum ada (OD-030-an).
                                    Nol berarti belum pernah ada sisa, bukan
                                    belum pernah mengisi.
                                --}}
                                <x-rupiah :value="$saldo[$c->id] ?? 0" />
                            </td>
                            <td data-label="Aksi" class="py-3 px-3">
                                <div class="flex items-center gap-2 md:justify-end flex-wrap">
                                    {{-- Tautan GET, bukan panel: form ubahnya
                                         ada di atas daftar supaya tidak
                                         terjepit selebar kolom ini (DEC-049). --}}
                                    <a href="{{ route('admin.customers', ['q' => $q, 'edit' => $c->id]) }}#ubah"
                                       class="px-3 py-1.5 text-[11px] font-semibold rounded-full
                                              text-ink-soft bg-inset hover:bg-line-soft transition-colors">
                                        Ubah
                                    </a>

                                    @unless ($c->membership?->is_active)
                                        <form method="POST" action="{{ route('admin.customers.membership', $c) }}">
                                            @csrf
                                            <button type="submit"
                                                    class="px-3 py-1.5 text-[11px] font-semibold rounded-full text-card bg-brand-600 hover:bg-brand-700 transition-colors">
                                                Jadikan member
                                            </button>
                                        </form>
                                    @endunless
                                </div>
                            </td>
                        </tr>
                    @empty
                        <x-empty title="{{ $q !== '' ? 'Tidak ada yang cocok' : 'Belum ada customer' }}" icon="search" colspan="6">
                            {{ $q !== ''
                                ? 'Coba kata kunci lain, atau sebagian nomor HP.'
                                : 'Customer dicatat saat operator membuka sesi atas nama seseorang.' }}
                        </x-empty>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

</x-layouts.app>
