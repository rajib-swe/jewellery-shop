<?php

namespace App\Swagger;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    description: 'Jewellery Shop Management API',
    title: 'Jewellery Shop API',
    termsOfService: 'https://jewellery-shop.test/terms',
    contact: new OA\Contact(
        email: 'admin@jewellery-shop.test'
    ),
    license: new OA\License(
        name: 'MIT',
        url: 'https://opensource.org/licenses/MIT'
    )
)]
#[OA\Server(
    url: '/api/v1',
    description: 'API Server'
)]
#[OA\SecurityScheme(
    securityScheme: 'sanctum',
    type: 'apiKey',
    description: 'Enter token in format (Bearer <token>)',
    name: 'Authorization',
    in: 'header'
)]
class OpenApi {}
