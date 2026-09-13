<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminPhotoCompetitionController;
use App\Http\Controllers\FilePondController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PhotoCompetitionController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\PhotoLikeController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SupportTicketController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
 */

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('photos/current', [PhotoCompetitionController::class, 'current']);
Route::get('photos/discover', [PhotoCompetitionController::class, 'discover']);

Route::middleware('auth:sanctum')->group(function () {

    Route::get('auth', [UserController::class, 'auth']);

    Route::apiResources([
        "users" => UserController::class,
        "notifications" => NotificationController::class,
        "integrations" => IntegrationController::class,
        "support-tickets" => SupportTicketController::class,
        "settings" => SettingController::class,
    ]);

    Route::post('push-subscriptions', [PushSubscriptionController::class, 'store']);
    Route::delete('push-subscriptions', [PushSubscriptionController::class, 'destroy']);

    Route::post('onboarding/install', [OnboardingController::class, 'completeInstall']);
    Route::post('onboarding/permissions', [OnboardingController::class, 'completePermissions']);

    Route::post('photos', [PhotoController::class, 'store']);
    Route::delete('photos/{id}', [PhotoController::class, 'destroy']);
    Route::post('photos/{id}/like', [PhotoLikeController::class, 'store']);
});

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::patch('users/{id}/verify', [UserController::class, 'update'])->name('users.verify');
    Route::get('photo-competitions', [AdminPhotoCompetitionController::class, 'index'])->name('photo-competitions.index');
    Route::get('photo-competitions/recent', [AdminPhotoCompetitionController::class, 'recent'])->name('photo-competitions.recent');
    Route::put('photo-competitions/prize-amount', [AdminPhotoCompetitionController::class, 'updatePrizeAmount'])->name('photo-competitions.prize-amount');
    Route::put('photo-competitions/schedule', [AdminPhotoCompetitionController::class, 'updateSchedule'])->name('photo-competitions.schedule');
    Route::put('photo-competitions/active', [AdminPhotoCompetitionController::class, 'updateActive'])->name('photo-competitions.update-active');
});

/*
 * Filepond Controller
 */
Route::prefix('filepond')->group(function () {
    Route::controller(FilePondController::class)->group(function () {
        // User
        Route::post('avatar/{id}', 'updateAvatar');

        // Support Tickets
        Route::post('support-tickets/attachments', 'storeSupportTicketAttachment');
        Route::delete('support-tickets/attachments/{id}', 'destroySupportTicketAttachment');

        Route::post('photos', 'storePhoto')->middleware('auth:sanctum');
        Route::delete('photos/{id}', 'destroyPhoto')->middleware('auth:sanctum');
    });
});
