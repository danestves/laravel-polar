<?php

use Illuminate\Support\Facades\Route;
use Spatie\WebhookClient\Http\Controllers\WebhookController;

Route::group([
    'prefix' => config('polar.path'),
    'as' => 'polar.',
], function () {
    Route::post('webhook', WebhookController::class)->name('webhook-client-polar');
});
