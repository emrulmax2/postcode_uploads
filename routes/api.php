<?php

use App\Http\Controllers\PostcodeRecordController;
use Illuminate\Support\Facades\Route;

Route::get('/postcode-records', [PostcodeRecordController::class, 'showByPostcode']);
