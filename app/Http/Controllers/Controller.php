<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(title: "Internal Procurement API", version: "1.0.0", description: "API documentation for the Internal Procurement System Technical Test.")]
#[OA\Server(url: "http://localhost:8000", description: "Local API Server")]
#[OA\SecurityScheme(securityScheme: "sanctum", type: "http", bearerFormat: "JWT", scheme: "bearer")]
abstract class Controller
{
}
