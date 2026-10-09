<?php

namespace App\Services;

use App\Enums\SessionStatus;
use App\Events\DeviceHeartbeat;
use App\Exceptions\ApiException;
use App\Models\BillingSession;
use App\Models\Device;
use App\Models\Station;
use App\Support\Api\ErrorCode;
use App\Support\Audit\AuditAction;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * TV Agent — API.md §9.
 *
 * Device memakai `X-Device-Token`, bukan `Authorization`, dan tidak punya
 * akses apa pun selain endpoint di sini (PRD §6).
 */
class DeviceService
{
    /** REALTIME.md §7 — maksimum 1 broadcast heartbeat per device per 30 detik. */
    private const HEARTBEAT_BROADCAST_WINDOW = 30;

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * `POST /devices/register` — dipanggil sekali saat provisioning.
     *
     * @return array{device: Device, token: string}
     */
    public function register(array $input): array
    {
        return DB::transaction(function () use ($input) {
            $kode = strtoupper(trim((string) $input['enrollment_code']));

            $station = Station::query()->lockForUpdate()
                ->where('enrollment_code', $kode)
                ->first();

            if ($station === null) {
                throw ApiException::unprocessable(
                    ErrorCode::ENROLLMENT_CODE_INVALID,
                    'Kode pendaftaran tidak dikenal.',
                );
            }

            $device = Device::query()->lockForUpdate()
                ->where('device_uid', $input['device_uid'])
                ->first();

            /*
             * Station yang sudah dipegang TV LAIN tidak boleh diambil alih
             * diam-diam. Teknisi yang salah membacakan kode akan membuat TV
             * yang sedang jalan kehilangan tokennya tanpa ada yang sadar —
             * dan station itu berhenti bekerja di tengah jam operasional.
             *
             * Mendaftarkan ulang TV yang SAMA (device_uid cocok) tetap boleh:
             * itu kejadian normal saat APK dipasang ulang.
             */
            $penghuni = Device::query()
                ->where('station_id', $station->id)
                ->when($device !== null, fn ($q) => $q->whereKeyNot($device->id))
                ->first();

            if ($penghuni !== null) {
                throw ApiException::conflict(
                    ErrorCode::STATION_HAS_DEVICE,
                    "Station {$station->code} sudah dipakai TV lain. Cabut dulu yang lama.",
                    ['device_uid' => $penghuni->device_uid],
                );
            }

            $device ??= new Device(['device_uid' => $input['device_uid']]);

            $device->fill([
                'station_id' => $station->id,
                'model' => $input['model'] ?? null,
                'os_version' => $input['os_version'] ?? null,
                'app_version' => $input['app_version'] ?? null,
                'registered_at' => Carbon::now(),
            ]);
            $device->save();

            $token = $device->issueToken();

            $this->audit->forSystem(AuditAction::DEVICE_REGISTERED, [
                'subject_type' => 'device',
                'subject_id' => $device->id,
                'after' => [
                    'device_uid' => $device->device_uid,
                    'station' => $station->code,
                    'model' => $device->model,
                ],
            ]);

            return ['device' => $device->fresh('station'), 'token' => $token];
        });
    }

    /**
     * `POST /devices/heartbeat`.
     *
     * @return array{acknowledged: bool, state_match: bool, state: array}
     */
    public function heartbeat(Device $device, array $input): array
    {
        $device->fill([
            'last_seen_at' => Carbon::now(),
            'uptime_seconds' => $input['uptime_seconds'] ?? $device->uptime_seconds,
            'app_version' => $input['app_version'] ?? $device->app_version,
        ])->save();

        $state = $this->state($device);

        $this->broadcastThrottled($device);

        return [
            'acknowledged' => true,
            'state_match' => $this->stateMatches($state, $input),
            'state' => $state,
        ];
    }

    /**
     * `GET /devices/me/state` — endpoint reconcile setelah reboot atau
     * reconnect (PRD §16, T11/T12).
     */
    public function state(Device $device): array
    {
        $station = $device->station;

        if ($station === null) {
            throw ApiException::conflict(
                ErrorCode::DEVICE_NOT_ASSIGNED,
                'Device ini belum dipetakan ke station mana pun.',
            );
        }

        $session = $station->currentSession();

        return [
            'station' => ['code' => $station->code, 'name' => $station->name],
            'session' => $session === null ? null : [
                'id' => $session->id,
                'status' => $session->status->value,
                /*
                 * Tidak ada `remaining_seconds` — TV menghitungnya dari
                 * `end_at` ditambah server-time offset (PRD §16, DEC-003).
                 * null untuk Postpaid, yang memang tidak punya batas (DEC-034):
                 * TV lalu menghitung MAJU dari `started_at`.
                 */
                'started_at' => $session->started_at?->toIso8601ZuluString(),
                'end_at' => $session->end_at?->toIso8601ZuluString(),
                'customer_label' => $session->customerLabel(),
            ],
            'display' => ['mode' => $this->displayMode($session)],
        ];
    }

    /**
     * Apa yang harus ditampilkan TV.
     *
     * `LOCKED` akhirnya terpakai. Kontrak menandainya "belum dipakai, menunggu
     * OD-001 & OD-004" — keduanya sudah diputuskan: DEC-033 (waktu habis
     * berarti berhenti, TV mati/standby) dan DEC-030 (warning overlay).
     *
     * Bentuk visualnya — benar-benar padam, standby, atau layar "waktu habis,
     * silakan ke kasir" — masih sisa OD-004 dan urusan Tahap 2. Server cuma
     * menyatakan station ini TIDAK boleh dimainkan.
     */
    private function displayMode(?BillingSession $session): string
    {
        if ($session === null || $session->status === SessionStatus::PENDING_PAYMENT) {
            return 'IDLE';
        }

        return $session->status->isRunning() ? 'TIMER' : 'LOCKED';
    }

    /**
     * Apakah state lokal TV masih sama dengan server.
     *
     * `false` berarti TV harus memakai `state` dari response — reconciliation
     * murah tanpa WebSocket (API.md §9), yang justru paling dibutuhkan ketika
     * WebSocket-nya sedang putus.
     */
    private function stateMatches(array $state, array $input): bool
    {
        $sessionId = $state['session']['id'] ?? null;
        $endAt = $state['session']['end_at'] ?? null;

        if (($input['known_session_id'] ?? null) !== $sessionId) {
            return false;
        }

        $known = $input['known_end_at'] ?? null;

        // Dibandingkan sebagai waktu, bukan string: "08:00:00Z" dan
        // "08:00:00.000Z" adalah saat yang sama tapi teksnya berbeda.
        if ($known === null || $endAt === null) {
            return $known === $endAt;
        }

        return Carbon::parse($known)->equalTo(Carbon::parse($endAt));
    }

    /**
     * REALTIME.md §7 — maksimum 1 broadcast per device per 30 detik.
     *
     * Di sini throttle (buang yang kelebihan) memang benar, beda dengan
     * `session.updated` yang butuh debounce: heartbeat yang terbuang tidak
     * menghilangkan informasi apa pun. `last_seen_at` sudah tersimpan di
     * database, dan heartbeat berikutnya datang 30 detik lagi membawa
     * keadaan yang sama.
     */
    private function broadcastThrottled(Device $device): void
    {
        $key = "broadcast:device-heartbeat:{$device->id}";

        if (! Cache::add($key, true, self::HEARTBEAT_BROADCAST_WINDOW)) {
            return;
        }

        DeviceHeartbeat::dispatch($device);
    }
}
