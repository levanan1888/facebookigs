<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('landing');
})->name('home');

Route::get('dashboard', [App\Http\Controllers\DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Analytics overview + APIs
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('analytics', [App\Http\Controllers\AnalyticsController::class, 'index'])
        ->middleware(['permission.404:dashboard.analytics'])
        ->name('analytics');

    Route::prefix('api/analytics')->name('api.analytics.')->group(function () {
        Route::get('options', [App\Http\Controllers\AnalyticsController::class, 'options'])
            ->middleware(['permission.404:analytics.options'])
            ->name('options');
        Route::get('summary', [App\Http\Controllers\AnalyticsController::class, 'summary'])
            ->middleware(['permission.404:analytics.summary'])
            ->name('summary');
        Route::get('breakdown', [App\Http\Controllers\AnalyticsController::class, 'breakdown'])
            ->middleware(['permission.404:analytics.breakdown'])
            ->name('breakdown');
        Route::get('series', [App\Http\Controllers\AnalyticsController::class, 'series'])
            ->middleware(['permission.404:analytics.series'])
            ->name('series');
        Route::get('ad-details', [App\Http\Controllers\AnalyticsController::class, 'adDetails'])
            ->middleware(['permission.404:analytics.ad-details'])
            ->name('ad-details');
    });
});

Route::post('dashboard/sync-facebook', [App\Http\Controllers\DashboardController::class, 'syncFacebook'])
    ->middleware(['auth', 'verified', 'permission.404:facebook.sync'])
    ->name('dashboard.sync-facebook');

// API for async sync progress
Route::middleware(['auth', 'verified', 'permission.404:facebook.sync'])->prefix('api')->group(function () {
    Route::post('sync/facebook/start', [App\Http\Controllers\Api\SyncController::class, 'start'])->name('api.sync.facebook.start');
    Route::get('sync/facebook/status/{id}', [App\Http\Controllers\Api\SyncController::class, 'status'])->name('api.sync.facebook.status');
});

// Test route để kiểm tra trang 404
Route::get('/test-404', function () {
    abort(404);
})->name('test.404');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    // Website settings (require permission)
    Route::middleware(['permission.404:manage settings'])->group(function () {
        Route::get('settings/website', [\App\Http\Controllers\Settings\WebsiteController::class, 'index'])->name('settings.website');
        Route::put('settings/website', [\App\Http\Controllers\Settings\WebsiteController::class, 'update'])->name('settings.website.update');
    });
});

// Admin routes
Route::middleware(['auth', 'verified', 'track.login'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('users', \App\Http\Controllers\Admin\UserController::class);
    Route::resource('roles', \App\Http\Controllers\Admin\RoleController::class);
    Route::resource('permissions', \App\Http\Controllers\Admin\PermissionController::class);
    Route::resource('login-activities', \App\Http\Controllers\Admin\LoginActivityController::class);
    Route::post('login-activities/clear-old', [\App\Http\Controllers\Admin\LoginActivityController::class, 'clearOld'])->name('login-activities.clear-old');
});

require __DIR__.'/auth.php';
