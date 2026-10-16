<?php

use App\Http\Controllers\Api\PaymentReceiptController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/comprobantes/{id}/verificar', [PaymentReceiptController::class, 'verify'])->whereUuid('id')->middleware('throttle:60,1')->name('receipts.verify');
