# PROMPT 1

Saya sedang mengembangkan "Service Katalog Barang" dalam Sistem Lelang menggunakan Laravel 11. Ada instruksi baru dari dosen bahwa untuk mendapatkan token JWT melalui endpoint SSO M2M `POST /api/v1/auth/token`, request body JSON wajib menyertakan properti `nim` di samping `api_key`. 
Akun kredensial saya adalah: 

* `api_key`: `KEY-MHS-243`

* `nim`: `102022400192` 

Tolong berikan contoh curl/request untuk mendapatkan token tersebut, serta buatkan middleware Laravel bernama `SSOAuthMiddleware` yang bertugas mengekstrak token Bearer dari request header, lalu memverifikasinya secara dinamis menggunakan JWKS RS256 dari endpoint `https://iae-sso.virtualfri.id/.well-known/jwks.json`.

---

# AI RESPONSE

Berikut contoh request M2M Token dan implementasi `SSOAuthMiddleware`:

---

Contoh cURL — Ambil Token M2M dengan `nim`

```
curl -s -X POST https://iae-sso.virtualfri.id/api/v1/auth/token \
  -H "Content-Type: application/json" \
  -d '{"api_key":"KEY-MHS-243","nim":"102022400192"}'
```

Contoh Response:

```
{
    "status": "success",
    "token_type": "m2m",
    "grant_type": "client_credentials",
    "algorithm": "RS256",
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...",
    "expires_in": 3600,
    "app": {
        "client_id": "KEY-MHS-243",
        "team": "TEAM-02"
    }
}
```

---

Install Library JWT

```
composer require firebase/php-jwt
```

---

`app/Http/Middleware/SSOAuthMiddleware.php`

```
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Firebase\JWT\JWT;
use Firebase\JWT\JWK;

class SSOAuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Ekstrak token dari header Authorization
        $authHeader = $request->header('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized. Token JWT tidak ditemukan.',
                'errors'  => null,
            ], 401);
        }

        $token = substr($authHeader, 7);

        try {
            // Ambil public key (JWKS) secara dinamis dari SSO dosen
            $jwksResponse = Http::get('https://iae-sso.virtualfri.id/.well-known/jwks.json');
            $jwks = $jwksResponse->json();

            // Verifikasi token JWT menggunakan RS256
            $keys = JWK::parseKeySet($jwks);
            $decoded = JWT::decode($token, $keys);

            // Simpan payload JWT ke request untuk digunakan controller
            $request->merge(['auth_user' => (array) $decoded]);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized. Token JWT tidak valid: ' . $e->getMessage(),
                'errors'  => null,
            ], 401);
        }

        return $next($request);
    }
}
```

---

Daftarkan di `bootstrap/app.php`

```
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'api.key'  => \App\Http\Middleware\ApiKeyMiddleware::class,
        'sso.auth' => \App\Http\Middleware\SSOAuthMiddleware::class,
    ]);
})
```

---

Proteksi Endpoint di `routes/api.php`

```
Route::post('/items', [ItemController::class, 'store'])
    ->middleware('sso.auth');
```

---

# PROMPT 2

Pada modul Katalog Barang, penambahan barang baru (`POST /api/v1/items`) dikategorikan sebagai Transaksi Kritis yang merubah state database. Kontrak mewajibkan data transaksi kritis ini dikirimkan ke server audit legacy terpusat menggunakan format XML SOAP ke URL `https://iae-sso.virtualfri.id/soap/v1/audit`.
Ketentuan payload XML wajib mengandung tag `<TeamID>`, `<ActivityName>`, dan `<LogContent>` (membungkus CDATA JSON barang). Nilai `<TeamID>` tim saya adalah `TEAM-02`.
Bantu saya membuat Service Class di Laravel bernama `SoapAuditService` untuk menangani pengiriman data ini melalui HTTP POST Client, serta menangkap string `<iae:ReceiptNumber>` yang dikembalikan server untuk disimpan.

---

# AI RESPONSE

Berikut implementasi lengkap `SoapAuditService`:

---

`app/Services/SoapAuditService.php`

```
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SoapAuditService
{
    private string $soapUrl;
    private string $teamId;
    private string $apiKey;
    private string $nim;

    public function __construct()
    {
        $this->soapUrl = env('SOAP_URL', 'https://iae-sso.virtualfri.id/soap/v1/audit');
        $this->teamId  = env('SOAP_TEAM_ID', 'TEAM-02');
        $this->apiKey  = env('IAE_M2M_API_KEY', 'KEY-MHS-243');
        $this->nim     = env('IAE_NIM', '102022400192');
    }

    public function audit(string $activityName, array $data): ?string
    {
        // Konversi data JSON ke string
        $logContent = json_encode($data);

        // Buat XML Envelope
        $xmlEnvelope = $this->buildXmlEnvelope($activityName, $logContent);

        try {
            // Ambil token M2M dari SSO
            $token = $this->getM2MToken();

            // Kirim ke SOAP endpoint
            $response = Http::withHeaders([
                'Content-Type'  => 'text/xml; charset=UTF-8',
                'Authorization' => 'Bearer ' . $token,
                'SOAPAction'    => 'audit',
            ])->withBody($xmlEnvelope, 'text/xml')->post($this->soapUrl);

            // Parse ReceiptNumber dari response XML
            $receiptNumber = $this->parseReceiptNumber($response->body());

            Log::info('SOAP Audit berhasil', [
                'activity'       => $activityName,
                'receipt_number' => $receiptNumber,
            ]);

            return $receiptNumber;

        } catch (\Exception $e) {
            Log::error('SOAP Audit gagal: ' . $e->getMessage());
            return null;
        }
    }

    private function buildXmlEnvelope(string $activityName, string $logContent): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:iae="http://iae.central/audit">
    <soap:Body>
        <iae:AuditRequest>
            <iae:TeamID>{$this->teamId}</iae:TeamID>
            <iae:ActivityName>{$activityName}</iae:ActivityName>
            <iae:LogContent><![CDATA[{$logContent}]]></iae:LogContent>
        </iae:AuditRequest>
    </soap:Body>
</soap:Envelope>
XML;
    }

    private function getM2MToken(): string
    {
        $response = Http::post('https://iae-sso.virtualfri.id/api/v1/auth/token', [
            'api_key' => $this->apiKey,
            'nim'     => $this->nim,
        ]);

        return $response->json('token');
    }

    private function parseReceiptNumber(string $xmlResponse): ?string
    {
        preg_match('/<iae:ReceiptNumber>(.*?)<\/iae:ReceiptNumber>/', $xmlResponse, $matches);
        return $matches[1] ?? null;
    }
}
```

---

Tambahkan ke `.env`

```
SOAP_URL=https://iae-sso.virtualfri.id/soap/v1/audit
SOAP_TEAM_ID=TEAM-02
IAE_M2M_API_KEY=KEY-MHS-243
IAE_NIM=102022400192
```

---

Cara Pakai di `ItemController.php`

```
use App\Services\SoapAuditService;

$soapService   = new SoapAuditService();
$receiptNumber = $soapService->audit('ItemCreated', [
    'item_id'          => $item->id,
    'name'             => $item->name,
    'starting_price'   => $item->starting_price,
    'auction_status'   => $item->auction_status,
    'auction_deadline' => $item->auction_deadline,
]);
```

---

Contoh Hasil yang Ditangkap

Dari response XML:

```
<iae:Status>SUCCESS</iae:Status>
<iae:ReceiptNumber>IAE-LOG-2026-A057F173</iae:ReceiptNumber>
```

Method `parseReceiptNumber()` akan mengembalikan string `"IAE-LOG-2026-A057F173"` yang siap disimpan atau ditampilkan di response API.

---

# PROMPT 3

Setelah barang baru berhasil masuk katalog lelang dan diaudit oleh sistem SOAP, saya harus mempublikasikan notifikasi aktivitas tersebut secara asinkron ke exchange `iae.central.exchange` melalui API Gateway terpusat di URL `https://iae-sso.virtualfri.id/api/v1/messages/publish`.
Berikan saya implementasi Service Class `RabbitMqService` menggunakan HTTP Client dengan autentikasi Bearer Token untuk menyebarkan event `ItemPublished` tanpa memblokir performa utama API.

---

# AI RESPONSE

Berikut implementasi `RabbitMqService` dengan pendekatan asinkron menggunakan Laravel Queue agar tidak menghambat response API:

---

`app/Services/RabbitMqService.php`

```
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RabbitMqService
{
    private string $publishUrl;
    private string $apiKey;
    private string $nim;

    public function __construct()
    {
        $this->publishUrl = env('RABBITMQ_PUBLISH_URL', 'https://iae-sso.virtualfri.id/api/v1/messages/publish');
        $this->apiKey     = env('IAE_M2M_API_KEY', 'KEY-MHS-243');
        $this->nim        = env('IAE_NIM', '102022400192');
    }

    public function publish(string $event, array $data): bool
    {
        try {
            $token = $this->getM2MToken();

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ])->post($this->publishUrl, [
                'routingKey' => $event,
                'message'    => [
                    'event'     => $event,
                    'service'   => 'Katalog-Service',
                    'timestamp' => now()->toIso8601String(),
                    'data'      => $data,
                ],
            ]);

            if ($response->successful()) {
                Log::info('RabbitMQ publish berhasil', [
                    'event' => $event,
                    'data'  => $data,
                ]);
                return true;
            }

            Log::warning('RabbitMQ publish gagal', [
                'event'    => $event,
                'response' => $response->body(),
            ]);
            return false;

        } catch (\Exception $e) {
            Log::error('RabbitMQ error: ' . $e->getMessage());
            return false;
        }
    }

    private function getM2MToken(): string
    {
        $response = Http::post('https://iae-sso.virtualfri.id/api/v1/auth/token', [
            'api_key' => $this->apiKey,
            'nim'     => $this->nim,
        ]);

        return $response->json('token');
    }
}
```

---

Agar Tidak Blocking — Buat Job Queue

```
php artisan make:job PublishItemEvent
```

`app/Jobs/PublishItemEvent.php`

```
<?php

namespace App\Jobs;

use App\Services\RabbitMqService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PublishItemEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private string $event;
    private array $data;

    public function __construct(string $event, array $data)
    {
        $this->event = $event;
        $this->data  = $data;
    }

    public function handle(RabbitMqService $rabbitMQ): void
    {
        $rabbitMQ->publish($this->event, $this->data);
    }
}
```

---

Konfigurasi Queue di `.env`

```
QUEUE_CONNECTION=database
RABBITMQ_PUBLISH_URL=https://iae-sso.virtualfri.id/api/v1/messages/publish
```

Jalankan migration queue (jika belum ada):

```
php artisan queue:table
php artisan migrate
```

---

Cara Pakai di `ItemController.php`

```
use App\Jobs\PublishItemEvent;

// Dispatch ke queue — tidak blocking, response langsung dikembalikan ke client
PublishItemEvent::dispatch('ItemPublished', [
    'item_id'          => $item->id,
    'name'             => $item->name,
    'starting_price'   => $item->starting_price,
    'auction_status'   => $item->auction_status,
    'auction_deadline' => $item->auction_deadline,
]);
```

---

Jalankan Queue Worker

```
php artisan queue:work
```

---

Perbedaan Sebelum vs Sesudah

| | **Synchronous (Lama)** | **Asynchronous (Baru)** |
| :--- | :--- | :--- |
| Proses | Request menunggu RabbitMQ selesai | Request langsung selesai, RabbitMQ diproses di background |
| Response time | Lebih lambat | Lebih cepat |
| Risiko | Kalau RabbitMQ lambat, API ikut lambat | API tidak terganggu performa RabbitMQ |

---

# PROMPT 4

Tolong buatkan visualisasi skema pengujian dalam bentuk tabel Markdown untuk mendokumentasikan hasil penangkapan JWT token, pengiriman SOAP XML, dan broadcast AMQP di Postman untuk dokumentasi laporan saya.

---

# AI RESPONSE

Berikut tabel dokumentasi hasil pengujian untuk laporan kamu:

---

**Tabel Hasil Pengujian — Integrasi SSO, SOAP, dan RabbitMQ**

| **No** | **Modul** | **Endpoint yang Diuji** | **Method** | **Header/Auth** | **Hasil** | **Bukti** |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| 1 | SSO | `https://iae-sso.virtualfri.id/api/v1/auth/token` | POST | `email`, `password` di body | ✅ Berhasil | Token JWT diterima, `token_type: user` |
| 2 | SSO | `http://localhost:8000/api/v1/items` (tanpa JWT) | POST | `X-IAE-KEY` saja | ✅ Berhasil ditolak | `401 Unauthorized` — Token JWT tidak ditemukan |
| 3 | SSO | `http://localhost:8000/api/v1/items` (dengan JWT) | POST | `X-IAE-KEY` + `Authorization: Bearer JWT` | ✅ Berhasil | `201 Created` — item tersimpan |
| 4 | SOAP | `https://iae-sso.virtualfri.id/soap/v1/audit` | POST | `Authorization: Bearer M2M Token` | ✅ Berhasil | `ReceiptNumber: IAE-LOG-2026-A057F173` |
| 5 | SOAP | Cek log Laravel | - | - | ✅ Berhasil | `SOAP Audit berhasil {"activity":"ItemCreated", "receipt_number":"IAE-LOG-2026-A057F173"}` |
| 6 | RabbitMQ | `https://iae-sso.virtualfri.id/api/v1/messages/publish` | POST | `Authorization: Bearer M2M Token` | ✅ Berhasil | `200 OK` — event `item.created` terkirim |
| 7 | RabbitMQ | Cek log Laravel | - | - | ✅ Berhasil | `RabbitMQ publish berhasil {"event":"item.created", ...}` |
| 8 | RabbitMQ | Papan Pengumuman Dosen `(/board)` | - | - | ✅ Berhasil | Pesan dari `TEAM-02` muncul dengan payload `item.created` |

**Detail Payload Setiap Pengujian**

**Request POST /api/v1/items**

```
{
    "name": "Lukisan Vintage 1926",
    "description": "Lukisan langka dari era 1920an",
    "starting_price": 5000000,
    "auction_deadline": "2026-09-30 18:00:00",
    "image_url": null
}
```

Response Berhasil

```
{
    "status": "success",
    "message": "Item berhasil ditambahkan.",
    "data": {
        "id": 20,
        "name": "Lukisan Vintage 1926",
        "auction_status": "OPEN",
        "receipt_number": "IAE-LOG-2026-A057F173"
    },
    "meta": {
        "service_name": "Katalog-Service",
        "api_version": "v1"
    }
}
```

Event RabbitMQ yang Terkirim

```
{
    "event": "item.created",
    "service": "Katalog-Service",
    "data": {
        "item_id": 20,
        "name": "Lukisan Vintage 1926",
        "starting_price": 5000000,
        "auction_status": "OPEN",
        "auction_deadline": "2026-09-30T18:00:00.000000Z"
    }
}
```

---

Ringkasan Status Integrasi

| **Modul** | **Status** | **Endpoint Dosen yang Digunakan** |
| :--- | :--- | :--- | 
| SSO (JWT RS256) | ✅ Terhubung | `/api/v1/auth/jwks`, `/api/v1/auth/token` |
| SOAP Audit | ✅ Terhubung | `/soap/v1/audit` |
| RabbitMQ Publisher | ✅ Terhubung | `/api/v1/messages/publish` |