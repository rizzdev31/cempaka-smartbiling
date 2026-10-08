<?php

namespace App\Services;

use App\Enums\FnbOrderStatus;
use App\Enums\SessionItemType;
use App\Events\FnbOrderCreated;
use App\Events\SessionUpdated;
use App\Exceptions\ApiException;
use App\Models\BillingSession;
use App\Models\FnbOrder;
use App\Models\FnbProduct;
use App\Models\SessionItem;
use App\Models\User;
use App\Support\Api\ErrorCode;
use App\Support\Audit\AuditAction;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * F&B — API.md §8, PRD §13.
 *
 * Order masuk Open Tab session yang sama, bukan transaksi terpisah: PRD §12
 * menetapkan satu session = satu tagihan. Karena itu setiap order juga
 * melahirkan satu `session_item` bertipe `FNB`.
 */
class FnbService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<int, array{product_id: string, qty: int}>  $items
     */
    public function createOrder(BillingSession $session, array $items, ?string $note, User $actor): FnbOrder
    {
        $order = DB::transaction(function () use ($session, $items, $note, $actor) {
            $session = BillingSession::query()->lockForUpdate()->findOrFail($session->id);

            /*
             * Ini yang menutup ghost order (T05): pesanan untuk sesi yang sudah
             * checkout atau belum dibayar tidak punya Open Tab yang bisa ditagih,
             * dan akan jadi makanan yang keluar tanpa pembayaran.
             */
            if (! $session->status->isOrderable()) {
                throw ApiException::conflict(
                    ErrorCode::SESSION_NOT_ORDERABLE,
                    "Sesi berstatus {$session->status->value} tidak bisa menerima order F&B.",
                );
            }

            $lines = $this->resolveLines($items);
            $total = array_sum(array_column($lines, 'subtotal'));

            $order = $session->fnbOrders()->create([
                'code' => $this->nextCode(),
                'status' => FnbOrderStatus::PENDING,
                'source' => 'OPERATOR',   // CUSTOMER baru di Tahap 3C
                'note' => $note,
                'total' => $total,
                'created_by' => $actor->id,
            ]);

            foreach ($lines as $line) {
                $order->items()->create([
                    'fnb_product_id' => $line['product']->id,
                    // Nama & harga dibekukan: owner mengubah harga menu tidak
                    // boleh mengubah tagihan order yang sudah jalan.
                    'name' => $line['product']->name,
                    'qty' => $line['qty'],
                    'unit_price' => $line['product']->price,
                    'subtotal' => $line['subtotal'],
                ]);

                // Stok null = tidak dilacak (API.md §8) — jangan diturunkan.
                if ($line['product']->stock !== null) {
                    $line['product']->decrement('stock', $line['qty']);
                }
            }

            $session->items()->create([
                'type' => SessionItemType::FNB,
                'name' => "F&B {$order->code}",
                'qty' => 1,
                'unit_price' => $total,
                'subtotal' => $total,
                'is_paid' => false,
                'meta' => ['fnb_order_id' => $order->id],
                'created_by' => $actor->id,
            ]);

            $this->audit->forUser($actor, AuditAction::FNB_ORDER_CREATED, [
                'subject_type' => 'fnb_order',
                'subject_id' => $order->id,
                'after' => ['code' => $order->code, 'session_id' => $session->id, 'total' => $total],
            ]);

            return $order->fresh(['items', 'session.station']);
        });

        FnbOrderCreated::dispatch($order);
        SessionUpdated::dispatch(
            $session->fresh(['station', 'customer', 'items', 'payments']),
            ['items', 'totals'],
        );

        return $order;
    }

    /**
     * Transisi status order — API.md §8.
     *
     * Order yang dibatalkan tidak boleh ikut ditagih, jadi `session_item`-nya
     * dihapus. Pengecualiannya kalau item itu sudah terlanjur dibayar: V1 tidak
     * punya mekanisme refund (API.md §7), jadi barisnya dibiarkan dan selisihnya
     * diselesaikan manual oleh kasir.
     */
    public function updateStatus(FnbOrder $order, FnbOrderStatus $next, User $actor): FnbOrder
    {
        return DB::transaction(function () use ($order, $next, $actor) {
            $order = FnbOrder::query()->lockForUpdate()->findOrFail($order->id);
            $previous = $order->status;

            if (! $previous->canTransitionTo($next)) {
                throw ApiException::conflict(
                    ErrorCode::FNB_STATUS_TRANSITION_INVALID,
                    "Order F&B tidak bisa berpindah dari {$previous->value} ke {$next->value}.",
                    ['from' => $previous->value, 'to' => $next->value],
                );
            }

            $order->status = $next;
            $order->save();

            if ($next === FnbOrderStatus::CANCELLED) {
                SessionItem::query()
                    ->where('session_id', $order->session_id)
                    ->where('type', SessionItemType::FNB->value)
                    ->where('is_paid', false)
                    ->get()
                    ->filter(fn (SessionItem $item) => ($item->meta['fnb_order_id'] ?? null) === $order->id)
                    ->each(fn (SessionItem $item) => $item->delete());
            }

            $this->audit->forUser($actor, AuditAction::FNB_STATUS_CHANGED, [
                'subject_type' => 'fnb_order',
                'subject_id' => $order->id,
                'before' => ['status' => $previous->value],
                'after' => ['status' => $next->value],
            ]);

            return $order->fresh(['items', 'session.station']);
        });
    }

    /**
     * Harga SELALU dari server (PRD §8). Client hanya mengirim `product_id`
     * dan `qty` — kalau client boleh mengirim harga, diskon bisa dibuat
     * sendiri dari tablet.
     *
     * @param  array<int, array{product_id: string, qty: int}>  $items
     * @return array<int, array{product: FnbProduct, qty: int, subtotal: int}>
     */
    private function resolveLines(array $items): array
    {
        $products = FnbProduct::query()
            ->whereIn('id', array_column($items, 'product_id'))
            ->get()
            ->keyBy('id');

        $lines = [];

        foreach ($items as $item) {
            $product = $products->get($item['product_id']);

            if ($product === null || ! $product->isOrderable()) {
                throw ApiException::unprocessable(
                    ErrorCode::VALIDATION_FAILED,
                    'Ada produk yang tidak tersedia.',
                    ['items' => "Produk {$item['product_id']} tidak tersedia."],
                );
            }

            $qty = (int) $item['qty'];

            if ($product->stock !== null && $product->stock < $qty) {
                throw ApiException::unprocessable(
                    ErrorCode::VALIDATION_FAILED,
                    "Stok {$product->name} tinggal {$product->stock}.",
                    ['items' => "Stok {$product->name} tidak cukup."],
                );
            }

            $lines[] = [
                'product' => $product,
                'qty' => $qty,
                'subtotal' => (int) $product->price * $qty,
            ];
        }

        return $lines;
    }

    /** `FB-0012` (API.md §8) — berurutan global, bukan per hari. */
    private function nextCode(): string
    {
        return sprintf('FB-%04d', FnbOrder::query()->count() + 1);
    }
}
