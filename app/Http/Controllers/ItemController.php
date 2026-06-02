<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\Item;
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
}