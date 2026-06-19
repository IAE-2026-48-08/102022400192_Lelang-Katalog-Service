<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SoapAuditService
{
    private string $soapUrl;
    private string $teamId;

    public function __construct()
    {
        $this->soapUrl = env('SOAP_URL', 'https://iae-sso.virtualfri.id/soap/v1/audit');
        $this->teamId  = env('SOAP_TEAM_ID', 'TEAM-02');
    }

    public function audit(string $activityName, array $data): ?string
    {
        $logContent = json_encode($data);

        $xmlEnvelope = $this->buildXmlEnvelope($activityName, $logContent);

        try {
            $token = $this->getM2MToken();

            $response = Http::withHeaders([
                'Content-Type'  => 'text/xml; charset=UTF-8',
                'Authorization' => 'Bearer ' . $token,
                'SOAPAction'    => 'audit',
            ])->withBody($xmlEnvelope, 'text/xml')->post($this->soapUrl);

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
        'api_key' => env('IAE_M2M_API_KEY', 'KEY-MHS-243'),
        'nim'     => env('IAE_NIM', '102022400192'),
    ]);

    return $response->json('token');
    }

    private function parseReceiptNumber(string $xmlResponse): ?string
    {
        preg_match('/<iae:ReceiptNumber>(.*?)<\/iae:ReceiptNumber>/', $xmlResponse, $matches);
        return $matches[1] ?? null;
    }
    public function store(Request $request)
    {
    $validator = validator($request->all(), [
        'name'             => 'required|string|max:255',
        'description'      => 'required|string',
        'starting_price'   => 'required|integer|min:0',
        'auction_deadline' => 'required|date',
        'image_url'        => 'nullable|url',
    ]);

    if ($validator->fails()) {
        return ApiResponse::error('Validasi gagal.', 422, $validator->errors());
    }

    $item = Item::create([
        'name'               => $request->name,
        'description'        => $request->description,
        'starting_price'     => $request->starting_price,
        'current_highest_bid'=> 0,
        'auction_status'     => 'OPEN',
        'auction_deadline'   => $request->auction_deadline,
        'image_url'          => $request->image_url,
    ]);

    $soapService   = new SoapAuditService();
    $receiptNumber = $soapService->audit('ItemCreated', [
        'item_id'          => $item->id,
        'name'             => $item->name,
        'starting_price'   => $item->starting_price,
        'auction_status'   => $item->auction_status,
        'auction_deadline' => $item->auction_deadline,
    ]);

    return ApiResponse::success(
        array_merge($item->toArray(), ['receipt_number' => $receiptNumber]),
        'Item berhasil ditambahkan.',
        201
    );
    }
}