<?php

use App\Http\Controllers\Kid\TodayController;
use App\Http\Controllers\OccurrenceController;
use App\Http\Controllers\Parent\AssignmentController;
use App\Http\Controllers\Parent\ChoreController;
use App\Http\Controllers\Parent\DashboardController;
use App\Http\Controllers\Parent\FamilyController;
use App\Http\Controllers\Parent\RewardController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\RedemptionController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware('auth')->group(function () {
    Route::post('push-subscriptions', [PushSubscriptionController::class, 'store'])->name('push.subscribe');
    Route::delete('push-subscriptions', [PushSubscriptionController::class, 'destroy'])->name('push.unsubscribe');
});

Route::middleware(['auth', 'role:parent'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->middleware('verified')->name('dashboard');

    Route::resource('family', FamilyController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->parameters(['family' => 'member']);
    Route::resource('chores', ChoreController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('assignments', AssignmentController::class)->only(['store', 'update', 'destroy']);

    Route::post('occurrences/{occurrence}/approve', [OccurrenceController::class, 'approve'])->name('occurrences.approve');
    Route::post('occurrences/{occurrence}/reject', [OccurrenceController::class, 'reject'])->name('occurrences.reject');

    Route::resource('rewards', RewardController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::post('redemptions/{redemption}/fulfill', [RedemptionController::class, 'fulfill'])->name('redemptions.fulfill');
    Route::post('redemptions/{redemption}/deny', [RedemptionController::class, 'deny'])->name('redemptions.deny');
});

Route::middleware(['auth', 'role:kid'])->group(function () {
    Route::get('today', [TodayController::class, 'index'])->name('kid.today');
    Route::post('occurrences/{occurrence}/submit', [OccurrenceController::class, 'submit'])->name('occurrences.submit');

    Route::get('reward-shop', [RedemptionController::class, 'shop'])->name('kid.rewards');
    Route::post('rewards/{reward}/redeem', [RedemptionController::class, 'redeem'])->name('rewards.redeem');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
