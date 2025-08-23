<?php

use Illuminate\Support\Facades\Route;

// Facebook Dashboard routes
Route::middleware(['auth', 'verified'])->prefix('facebook')->name('facebook.')->group(function () {
    Route::get('overview', [App\Http\Controllers\FacebookDashboardController::class, 'overview'])
        ->middleware(['permission.404:facebook.overview'])
        ->name('overview');

    Route::get('hierarchy', [App\Http\Controllers\FacebookDashboardController::class, 'hierarchy'])
        ->middleware(['permission.404:facebook.hierarchy'])
        ->name('hierarchy');

    Route::get('analytics', [App\Http\Controllers\FacebookDashboardController::class, 'analytics'])
        ->middleware(['permission.404:facebook.analytics'])
        ->name('analytics');

    Route::get('data-raw', [App\Http\Controllers\FacebookDashboardController::class, 'dataRaw'])
        ->middleware(['permission.404:facebook.data_raw'])
        ->name('data-raw');
});

// Facebook sync routes
Route::middleware(['auth', 'verified', 'permission.404:facebook.sync'])->group(function () {
    Route::post('dashboard/sync-facebook', [App\Http\Controllers\DashboardController::class, 'syncFacebook'])
        ->name('dashboard.sync-facebook');
    
    Route::match(['get', 'post'], 'facebook/sync/ads', [App\Http\Controllers\FacebookSyncController::class, 'syncAds'])
        ->name('facebook.sync.ads');
    
    Route::post('facebook/sync/ads-direct', [App\Http\Controllers\FacebookSyncController::class, 'syncAdsDirect'])
        ->name('facebook.sync.ads-direct');
    
    Route::get('facebook/sync/test', function() {
        return view('facebook.sync-test');
    })->name('facebook.sync.test');
    
    Route::get('facebook/sync/status', [App\Http\Controllers\FacebookSyncController::class, 'getSyncStatus'])
        ->name('facebook.sync.status');
    
    Route::get('facebook/sync/progress', [App\Http\Controllers\FacebookSyncController::class, 'getSyncProgress'])
        ->name('facebook.sync.progress');
    
    Route::post('facebook/sync/stop', [App\Http\Controllers\FacebookSyncController::class, 'stopSync'])
        ->name('facebook.sync.stop');
    
    Route::post('facebook/sync/reset', [App\Http\Controllers\FacebookSyncController::class, 'resetSync'])
        ->name('facebook.sync.reset');
});
