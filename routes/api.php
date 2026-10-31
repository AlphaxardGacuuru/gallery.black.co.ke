<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminNoticeController;
use App\Http\Controllers\Admin\AdminPhotoCompetitionController;
use App\Http\Controllers\Admin\AdminPhotoLikeController;
use App\Http\Controllers\Admin\AdminPhotoPurchaseController;
use App\Http\Controllers\Admin\AdminReferralController;
use App\Http\Controllers\FilePondController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\KopokopoRecipientController;
use App\Http\Controllers\KopokopoTransferController;
use App\Http\Controllers\MPESATransactionController;
use App\Http\Controllers\NoticeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PhotoCompetitionController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\ExtraPhotoPurchaseController;
use App\Http\Controllers\PhotoLikeController;
use App\Http\Controllers\PhotoPurchaseController;
use App\Http\Controllers\PhotoSlotPurchaseController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\ReferralController;
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

Route::middleware('auth:sanctum')->group(function () {

    Route::get('auth', [UserController::class, 'auth']);

    // Both routes require the auth:sanctum guard to run so $request->user()
    // resolves (the guard switches the request's default auth guard to
    // "sanctum" as a side effect) — without it, isLikedByViewer always came
    // back false regardless of the viewer's real like state. Every page
    // that calls these already requires login client-side (see PUBLIC/
    // GUEST_ONLY in app.tsx), so this doesn't newly restrict anything.
    Route::get('photos/current', [PhotoCompetitionController::class, 'current']);
    Route::get('photos/discover', [PhotoCompetitionController::class, 'discover']);
    Route::get('leaderboard', [PhotoCompetitionController::class, 'leaderboard']);

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
    Route::post('onboarding/pwa-installed', [OnboardingController::class, 'recordPwaInstalled']);

    Route::get('photos/{id}', [PhotoController::class, 'show']);
    Route::post('photos', [PhotoController::class, 'store']);
    Route::delete('photos/{id}', [PhotoController::class, 'destroy']);
    Route::post('photos/{id}/like', [PhotoLikeController::class, 'store']);
    Route::post('photos/{photo}/images', [PhotoController::class, 'storeImages']);

    Route::post('photo-slot-purchases', [PhotoSlotPurchaseController::class, 'store']);
    Route::get('photo-slot-purchases/{purchase}', [PhotoSlotPurchaseController::class, 'show']);
    Route::post('extra-photo-purchases', [ExtraPhotoPurchaseController::class, 'store']);
    Route::post('photos/{photo}/purchases', [PhotoPurchaseController::class, 'store']);
    Route::get('photos/{photo}/downloads', [PhotoPurchaseController::class, 'downloads']);
    Route::get('photo-purchases/{purchase}', [PhotoPurchaseController::class, 'show']);

    Route::get('referrals/settings', [ReferralController::class, 'settings'])->name('referrals.settings');
    Route::get('referrals/mine', [ReferralController::class, 'mine'])->name('referrals.mine');

    Route::get('notices/next', [NoticeController::class, 'next'])->name('notices.next');
    Route::post('notices/{notice}/events', [NoticeController::class, 'storeEvent'])->name('notices.events.store');
});

Route::middleware(['auth:sanctum', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::patch('users/{id}/verify', [UserController::class, 'update'])->name('users.verify');
        Route::get('photo-competitions', [AdminPhotoCompetitionController::class, 'index'])->name('photo-competitions.index');
        Route::get('photo-competitions/recent', [AdminPhotoCompetitionController::class, 'recent'])->name('photo-competitions.recent');
        Route::get('photo-slot-purchases', [AdminPhotoCompetitionController::class, 'slotPurchases'])->name('photo-slot-purchases.index');
        Route::put('photo-competitions/prize-tiers', [AdminPhotoCompetitionController::class, 'updatePrizeTiers'])->name('photo-competitions.prize-tiers');
        Route::put('photo-competitions/extra-slot', [AdminPhotoCompetitionController::class, 'updateExtraSlot'])->name('photo-competitions.extra-slot');
        Route::put('photo-competitions/extra-photo', [AdminPhotoCompetitionController::class, 'updateExtraPhoto'])->name('photo-competitions.extra-photo');
        Route::put('photo-competitions/schedule', [AdminPhotoCompetitionController::class, 'updateSchedule'])->name('photo-competitions.schedule');
        Route::put('photo-competitions/active', [AdminPhotoCompetitionController::class, 'updateActive'])->name('photo-competitions.update-active');
        Route::post('photo-competition-winners/{winner}/pay', [AdminPhotoCompetitionController::class, 'payWinner'])->name('photo-competition-winners.pay');
        Route::post('photo-competitions/{competition}/load-wallet', [AdminPhotoCompetitionController::class, 'loadWallet'])->name('photo-competitions.load-wallet');
        Route::get('photo-purchases', [AdminPhotoPurchaseController::class, 'index'])->name('photo-purchases.index');
        Route::put('photo-purchases/settings', [AdminPhotoPurchaseController::class, 'updateSettings'])->name('photo-purchases.settings');
        Route::post('photo-purchases/{purchase}/pay', [AdminPhotoPurchaseController::class, 'pay'])->name('photo-purchases.pay');
        Route::get('kopokopo-transfers', [KopokopoTransferController::class, 'index'])->name('kopokopo-transfers.index');
        Route::post('kopokopo-transfers/initiate', [KopokopoTransferController::class, 'initiateTransfer'])->name('kopokopo-transfers.initiate');
        Route::get('kopokopo-recipients', [KopokopoRecipientController::class, 'index'])->name('kopokopo-recipients.index');
        Route::post('kopokopo-recipients', [KopokopoRecipientController::class, 'store'])->name('kopokopo-recipients.store');
        Route::get('referrals', [AdminReferralController::class, 'index'])->name('referrals.index');
        Route::get('referrals/recent', [AdminReferralController::class, 'recent'])->name('referrals.recent');
        Route::put('referrals/settings', [AdminReferralController::class, 'updateSettings'])->name('referrals.settings');
        Route::post('referrals/{user}/pay', [AdminReferralController::class, 'pay'])->name('referrals.pay');
        Route::post('users/{user}/referrer', [AdminReferralController::class, 'attach'])->name('users.referrer');
        Route::get('photos/{photo}/likes', [AdminPhotoLikeController::class, 'index'])->name('photos.likes.index');
        Route::post('photos/{photo}/likes/{user}', [AdminPhotoLikeController::class, 'toggle'])->name('photos.likes.toggle');
        Route::get('notices', [AdminNoticeController::class, 'index'])->name('notices.index');
        Route::post('notices', [AdminNoticeController::class, 'store'])->name('notices.store');
        Route::put('notices/limits', [AdminNoticeController::class, 'updateLimits'])->name('notices.limits');
        Route::get('notices/events', [AdminNoticeController::class, 'events'])->name('notices.events');
        Route::put('notices/{notice}', [AdminNoticeController::class, 'update'])->name('notices.update');
        Route::delete('notices/{notice}', [AdminNoticeController::class, 'destroy'])->name('notices.destroy');
    });

// Signed, short-lived links handed out by PhotoPurchaseController::downloads(),
// so a plain browser download works without the API's bearer token.
Route::get('photos/{photo}/download/{image?}', [PhotoPurchaseController::class, 'download'])
    ->middleware('signed')
    ->name('photos.download');

// Kopokopo's send_money callback — hit by Kopokopo itself, not the admin UI.
Route::post('kopokopo-transfers', [KopokopoTransferController::class, 'store']);

// Kopokopo's STK push (incoming payment) callback, hit by Kopokopo itself.
Route::post('mpesa-transactions', [MPESATransactionController::class, 'store']);

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
