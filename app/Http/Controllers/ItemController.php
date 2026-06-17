<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\Item;
use App\Services\SoapAuditService;
use App\Services\RabbitMQService;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ItemController extends Controller
{
    #[OA\Get(
        path: "/api/v1/items",
        summary: "Ambil semua daftar barang lelang",
        tags: ["Katalog"],
        security: [["ApiKeyAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Berhasil mengambil data",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "message", type: "string", example: "Data retrieved successfully"),
                        new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "meta", type: "object"),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthorized - API Key tidak valid")
        ]
    )]
    public function index()
    {
        $items = Item::all();
        return ApiResponse::success($items);
    }

    #[OA\Get(
        path: "/api/v1/items/{id}",
        summary: "Ambil detail spesifik satu barang",
        tags: ["Katalog"],
        security: [["ApiKeyAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "ID barang",
                schema: new OA\Schema(type: "integer", example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Berhasil mengambil data",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "message", type: "string", example: "Data retrieved successfully"),
                        new OA\Property(property: "data", type: "object"),
                        new OA\Property(property: "meta", type: "object"),
                    ]
                )
            ),
            new OA\Response(response: 404, description: "Item tidak ditemukan"),
            new OA\Response(response: 401, description: "Unauthorized - API Key tidak valid")
        ]
    )]
    public function show($id)
    {
        $item = Item::find($id);

        if (!$item) {
            return ApiResponse::error('Item tidak ditemukan.', 404);
        }

        return ApiResponse::success($item);
    }

    #[OA\Post(
        path: "/api/v1/items/filter",
        summary: "Filter barang berdasarkan kriteria tertentu",
        tags: ["Katalog"],
        security: [["ApiKeyAuth" => []]],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "auction_status", type: "string", example: "OPEN"),
                    new OA\Property(property: "min_price", type: "integer", example: 1000000),
                    new OA\Property(property: "max_price", type: "integer", example: 10000000),
                    new OA\Property(property: "keyword", type: "string", example: "lukisan"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Berhasil filter data",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "message", type: "string", example: "Data filtered successfully"),
                        new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "meta", type: "object"),
                    ]
                )
            ),
            new OA\Response(response: 404, description: "Tidak ada item yang sesuai filter"),
            new OA\Response(response: 401, description: "Unauthorized - API Key tidak valid")
        ]
    )]
    public function filter(Request $request)
    {
        $query = Item::query();

        if ($request->has('auction_status')) {
            $query->where('auction_status', $request->auction_status);
        }

        if ($request->has('min_price')) {
            $query->where('starting_price', '>=', $request->min_price);
        }

        if ($request->has('max_price')) {
            $query->where('starting_price', '<=', $request->max_price);
        }

        if ($request->has('keyword')) {
            $query->where('name', 'like', '%' . $request->keyword . '%');
        }

        $items = $query->get();

        if ($items->isEmpty()) {
            return ApiResponse::error('Tidak ada item yang sesuai dengan filter.', 404);
        }

        return ApiResponse::success($items, 'Data filtered successfully');
    }

    #[OA\Post(
    path: "/api/v1/items",
    summary: "Tambah item baru ke katalog lelang",
    tags: ["Katalog"],
    security: [["ApiKeyAuth" => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "name", type: "string", example: "Lukisan Vintage"),
                new OA\Property(property: "description", type: "string", example: "Lukisan langka tahun 1920"),
                new OA\Property(property: "starting_price", type: "integer", example: 5000000),
                new OA\Property(property: "auction_deadline", type: "string", example: "2026-07-01 18:00:00"),
                new OA\Property(property: "image_url", type: "string", example: "https://example.com/image.jpg"),
            ]
        )
    ),
    responses: [
        new OA\Response(
            response: 201,
            description: "Item berhasil ditambahkan",
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "status", type: "string", example: "success"),
                    new OA\Property(property: "message", type: "string", example: "Item berhasil ditambahkan"),
                    new OA\Property(property: "data", type: "object"),
                    new OA\Property(property: "meta", type: "object"),
                ]
            )
        ),
        new OA\Response(response: 422, description: "Validasi gagal"),
        new OA\Response(response: 401, description: "Unauthorized - API Key tidak valid")
    ]
)]
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
        'name'                => $request->name,
        'description'         => $request->description,
        'starting_price'      => $request->starting_price,
        'current_highest_bid' => 0,
        'auction_status'      => 'OPEN',
        'auction_deadline'    => $request->auction_deadline,
        'image_url'           => $request->image_url,
    ]);

    $soapService   = new SoapAuditService();
    $receiptNumber = $soapService->audit('ItemCreated', [
        'item_id'          => $item->id,
        'name'             => $item->name,
        'starting_price'   => $item->starting_price,
        'auction_status'   => $item->auction_status,
        'auction_deadline' => $item->auction_deadline,
    ]);

    $rabbitMQ = new RabbitMQService();
    $rabbitMQ->publish('item.created', [
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