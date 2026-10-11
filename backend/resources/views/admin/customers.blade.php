<x-layouts.app title="Customer">

    <x-page-header title="Customer &amp; Member" subtitle="100 nama pertama. Pakai pencarian untuk yang lain.">
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

    <x-card padding="p-4 sm:p-6">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs table-stacked">
                <thead>
                    <tr class="text-ink-soft border-b border-line-soft">
                        <th scope="col" class="py-3 px-3 font-medium">Nama</th>
                        <th scope="col" class="py-3 px-3 font-medium">No. HP</th>
                        <th scope="col" class="py-3 px-3 font-medium">Member</th>
                        <th scope="col" class="py-3 px-3 font-medium md:text-right">Sesi</th>
                        <th scope="col" class="py-3 px-3 font-medium md:text-right">Saldo</th>
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
                        </tr>
                    @empty
                        <x-empty title="{{ $q !== '' ? 'Tidak ada yang cocok' : 'Belum ada customer' }}" icon="search" colspan="5">
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
