<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Menyambung ke Reverb sebagai client sungguhan dan mencetak event yang masuk.
 *
 * Dibuat karena sisi **subscribe** tidak bisa dibuktikan oleh test: test
 * memakai `BROADCAST_CONNECTION=null`, jadi seluruh suite bisa hijau tanpa satu
 * event pun benar-benar sampai ke client. Perintah ini menutup celah itu —
 * ia menempuh jalur yang persis sama dengan Flutter dan Kotlin nanti:
 *
 *   login  ->  WebSocket handshake  ->  POST /broadcasting/auth  ->  subscribe
 *
 * Juga berguna di lapangan: kalau operator bilang "tabletnya tidak update",
 * jalankan ini di laptop untuk tahu apakah masalahnya di server atau di client.
 *
 * Protokolnya Pusher (dipakai Reverb). Client-nya ditulis dengan soket mentah,
 * bukan menambah dependensi — yang dibutuhkan cuma handshake dan baca-tulis
 * frame teks.
 */
class RealtimeListen extends Command
{
    protected $signature = 'realtime:listen
        {--api=http://127.0.0.1:8000 : Base URL API (tanpa /api/v1)}
        {--username=operator1}
        {--password=password}
        {--channel=operator : Nama channel tanpa awalan private-}
        {--seconds=30 : Berapa lama mendengarkan}';

    protected $description = 'Menyambung ke Reverb sebagai client dan mencetak event yang diterima';

    public function handle(): int
    {
        $host = (string) config('broadcasting.connections.reverb.options.host');
        $port = (int) config('broadcasting.connections.reverb.options.port');
        $key = (string) config('broadcasting.connections.reverb.key');

        if ($host === '' || $key === '') {
            $this->error('REVERB_HOST / REVERB_APP_KEY belum diisi di .env.');

            return self::FAILURE;
        }

        $this->line("Menyambung ke ws://{$host}:{$port} ...");

        $socket = $this->connect($host, $port, $key);

        if ($socket === null) {
            return self::FAILURE;
        }

        $socketId = $this->waitForSocketId($socket);

        if ($socketId === null) {
            $this->error('Tidak menerima pusher:connection_established.');

            return self::FAILURE;
        }

        $this->info("Tersambung. socket_id = {$socketId}");

        $channel = 'private-'.$this->option('channel');
        $auth = $this->authorizeChannel($socketId, $channel);

        if ($auth === null) {
            return self::FAILURE;
        }

        $this->info("Otorisasi channel {$channel} berhasil.");

        $this->send($socket, json_encode([
            'event' => 'pusher:subscribe',
            'data' => ['auth' => $auth, 'channel' => $channel],
        ]));

        return $this->listen($socket, $channel);
    }

    /** @return resource|null */
    private function connect(string $host, int $port, string $key)
    {
        $socket = @fsockopen($host, $port, $errno, $errstr, 10);

        if ($socket === false) {
            $this->error("Gagal menyambung: {$errstr} ({$errno}). Apakah `php artisan reverb:start` jalan?");

            return null;
        }

        $nonce = base64_encode(random_bytes(16));
        $path = "/app/{$key}?protocol=7&client=php-cli&version=1.0";

        fwrite($socket, implode("\r\n", [
            "GET {$path} HTTP/1.1",
            "Host: {$host}:{$port}",
            'Upgrade: websocket',
            'Connection: Upgrade',
            "Sec-WebSocket-Key: {$nonce}",
            'Sec-WebSocket-Version: 13',
            '', '',
        ]));

        $header = '';
        while (($line = fgets($socket)) !== false) {
            $header .= $line;
            if (rtrim($line) === '') {
                break;
            }
        }

        if (! str_contains($header, '101')) {
            $this->error('Handshake WebSocket ditolak server.');
            $this->line(strtok($header, "\r\n"));

            return null;
        }

        return $socket;
    }

    /** @param  resource  $socket */
    private function waitForSocketId($socket): ?string
    {
        $deadline = microtime(true) + 10;

        while (microtime(true) < $deadline) {
            $frame = $this->receive($socket);

            if ($frame === null) {
                continue;
            }

            $pesan = json_decode($frame, true);

            if (($pesan['event'] ?? null) === 'pusher:connection_established') {
                $data = json_decode($pesan['data'] ?? '{}', true);

                return $data['socket_id'] ?? null;
            }
        }

        return null;
    }

    /**
     * Menempuh jalur yang sama dengan Flutter: login dapat token, lalu minta
     * tanda tangan channel ke `POST /broadcasting/auth` dengan Bearer token.
     *
     * Sengaja TIDAK menghitung tanda tangannya sendiri dari app secret —
     * justru endpoint itu yang perlu dibuktikan bekerja.
     */
    private function authorizeChannel(string $socketId, string $channel): ?string
    {
        $base = rtrim((string) $this->option('api'), '/');

        $login = $this->postJson("{$base}/api/v1/auth/login", [
            'username' => $this->option('username'),
            'password' => $this->option('password'),
        ]);

        $token = $login['data']['token'] ?? null;

        if ($token === null) {
            $this->error("Login gagal. Apakah `php artisan serve` jalan di {$base}?");

            return null;
        }

        $auth = $this->postJson("{$base}/broadcasting/auth", [
            'socket_id' => $socketId,
            'channel_name' => $channel,
        ], $token);

        if (! isset($auth['auth'])) {
            $this->error('Otorisasi channel ditolak: '.json_encode($auth));

            return null;
        }

        return $auth['auth'];
    }

    /** @param  resource  $socket */
    private function listen($socket, string $channel): int
    {
        $seconds = (int) $this->option('seconds');
        $deadline = microtime(true) + $seconds;
        $subscribed = false;
        $jumlah = 0;

        $this->line("Mendengarkan {$seconds} detik. Lakukan sesuatu di tablet atau lewat curl...");
        $this->newLine();

        while (microtime(true) < $deadline) {
            $frame = $this->receive($socket);

            if ($frame === null) {
                continue;
            }

            $pesan = json_decode($frame, true);
            $event = $pesan['event'] ?? '?';

            if ($event === 'pusher:ping') {
                $this->send($socket, json_encode(['event' => 'pusher:pong', 'data' => new \stdClass]));

                continue;
            }

            if ($event === 'pusher_internal:subscription_succeeded') {
                $subscribed = true;
                $this->info("Berhasil subscribe ke {$channel}.");

                continue;
            }

            if ($event === 'pusher:error') {
                $this->error('Error dari server: '.($pesan['data']['message'] ?? $frame));

                continue;
            }

            $jumlah++;
            $this->newLine();
            $this->info("EVENT #{$jumlah}: {$event}");
            $this->line('  channel: '.($pesan['channel'] ?? '-'));
            $this->line('  '.substr((string) ($pesan['data'] ?? ''), 0, 400));
        }

        fclose($socket);
        $this->newLine();

        if (! $subscribed) {
            $this->error('Tidak pernah berhasil subscribe.');

            return self::FAILURE;
        }

        $this->info("Selesai. {$jumlah} event diterima.");

        // Tidak menerima event bukan kegagalan — mungkin memang tidak ada yang
        // terjadi selama jendela waktunya.
        return self::SUCCESS;
    }

    /** @param  resource  $socket */
    private function send($socket, string $payload): void
    {
        $len = strlen($payload);
        $mask = random_bytes(4);

        // Client WAJIB me-mask frame-nya (RFC 6455 §5.3); server tidak.
        $header = chr(0x81);

        if ($len < 126) {
            $header .= chr(0x80 | $len);
        } elseif ($len < 65536) {
            $header .= chr(0x80 | 126).pack('n', $len);
        } else {
            $header .= chr(0x80 | 127).pack('J', $len);
        }

        $masked = '';
        for ($i = 0; $i < $len; $i++) {
            $masked .= $payload[$i] ^ $mask[$i % 4];
        }

        fwrite($socket, $header.$mask.$masked);
    }

    /** @param  resource  $socket */
    private function receive($socket): ?string
    {
        stream_set_timeout($socket, 1);

        $header = fread($socket, 2);

        if ($header === false || strlen($header) < 2) {
            return null;
        }

        $opcode = ord($header[0]) & 0x0f;
        $len = ord($header[1]) & 0x7f;

        if ($len === 126) {
            $len = unpack('n', fread($socket, 2))[1];
        } elseif ($len === 127) {
            $len = unpack('J', fread($socket, 8))[1];
        }

        $payload = '';
        while (strlen($payload) < $len) {
            $potongan = fread($socket, $len - strlen($payload));

            if ($potongan === false || $potongan === '') {
                break;
            }

            $payload .= $potongan;
        }

        // 0x9 ping di level WebSocket — dibalas pong supaya tidak diputus.
        if ($opcode === 0x9) {
            fwrite($socket, chr(0x8a).chr(0x80).random_bytes(4));

            return null;
        }

        return $opcode === 0x1 ? $payload : null;
    }

    private function postJson(string $url, array $body, ?string $token = null): array
    {
        $headers = ['Content-Type: application/json', 'Accept: application/json'];

        if ($token !== null) {
            $headers[] = "Authorization: Bearer {$token}";
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_TIMEOUT => 10,
        ]);

        $raw = curl_exec($ch);
        curl_close($ch);

        return json_decode((string) $raw, true) ?? [];
    }
}
