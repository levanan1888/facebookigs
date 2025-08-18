<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('landing');
})->name('home');

Route::get('dashboard', [App\Http\Controllers\DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

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
