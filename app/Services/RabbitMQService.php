<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RabbitMQService
{
    private string $publishUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->publishUrl = env('RABBITMQ_PUBLISH_URL', 'https://iae-sso.virtualfri.id/api/v1/messages/publish');
        $this->apiKey     = env('IAE_M2M_API_KEY', 'KEY-MHS-243');
    }

    public function publish(string $event, array $data): bool
{
    try {
        $token = $this->getM2MToken();

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/json',
        ])->post($this->publishUrl, [
            'message' => [           
                'event'   => $event,
                'service' => 'Katalog-Service',
                'data'    => $data,
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
        'nim'     => env('IAE_NIM', '102022400192'),
    ]);

    return $response->json('token');
    }
}