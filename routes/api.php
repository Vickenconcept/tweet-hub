<?php

use App\Http\Controllers\ZernioWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/zernio/inbox', [ZernioWebhookController::class, 'inbox']);
