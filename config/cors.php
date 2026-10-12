<?php

/*
 | Only needed when the React site and the API live on different origins.
 | Set CORS_ALLOWED_ORIGINS in .env, comma separated:
 |   CORS_ALLOWED_ORIGINS=https://my-portfolio.vercel.app,http://localhost:5173
 | Tokens are sent in the Authorization header, so cookies/credentials are not used.
 */
return [
    'paths' => ['api/*'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173'))
    ))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'X-Requested-With'],
    'exposed_headers' => [],
    'max_age' => 600,
    'supports_credentials' => false,
];
