<?php

use App\Http\Controllers\UnsubscribeController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn() => view('app'))->name('home');

Route::get('/unsubscribe/{user}/{category}', [UnsubscribeController::class, 'show'])
    ->middleware('signed')
    ->name('unsubscribe.show');

require __DIR__ . '/auth.php';

require __DIR__ . '/settings.php';

Route::fallback(fn() => view('app'));
