<?php

use Illuminate\Support\Facades\Route;

// The API has no web UI. Plain 200 (hosting health checks may call "/") without the Laravel welcome page,
// which would reveal the framework to anyone scanning the Railway URL.
Route::get('/', fn () => response('ok', 200, ['Content-Type' => 'text/plain; charset=utf-8']));
