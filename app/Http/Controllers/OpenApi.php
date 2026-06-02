<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: "Katalog Service API",
    version: "1.0.0",
    description: "API dokumentasi untuk service Katalog Barang pada Sistem Lelang"
)]
#[OA\SecurityScheme(
    securityScheme: "ApiKeyAuth",
    type: "apiKey",
    in: "header",
    name: "X-IAE-KEY"
)]
class OpenApi {}