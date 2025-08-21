<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('landing');
})->name('home');

Route::get('dashboard', [App\Http\Controllers\UnifiedDashboardController::class, 'index'])
    ->middleware(['auth', 'verified', 'permission.404:dashboard.view'])
    ->name('dashboard');

Route::get('debug/campaigns', [App\Http\Controllers\DashboardController::class, 'debugCampaigns'])
    ->middleware(['auth', 'verified'])
    ->name('debug.campaigns');

// Analytics routes
Route::middleware(['auth', 'verified', 'permission.404:analytics'])->prefix('analytics')->name('analytics.')->group(function () {
    Route::get('/', [App\Http\Controllers\AnalyticsController::class, 'index'])->name('index');
});

// Facebook Dashboard (module tách biệt)
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
    
    Route::get('facebook/sync/status', [App\Http\Controllers\FacebookSyncController::class, 'getSyncStatus'])
        ->name('facebook.sync.status');
    
    Route::get('facebook/sync/progress', [App\Http\Controllers\FacebookSyncController::class, 'getSyncProgress'])
        ->name('facebook.sync.progress');
    
    Route::post('facebook/sync/stop', [App\Http\Controllers\FacebookSyncController::class, 'stopSync'])
        ->name('facebook.sync.stop');
    
    Route::post('facebook/sync/reset', [App\Http\Controllers\FacebookSyncController::class, 'resetSync'])
        ->name('facebook.sync.reset');
});

// API for sync progress
Route::middleware(['auth', 'verified', 'permission.404:facebook.sync'])->prefix('api')->name('api.')->group(function () {
    // Facebook Ads sync
    Route::post('sync/ads', [App\Http\Controllers\Api\SyncController::class, 'syncAds'])->name('sync.ads');
    Route::get('sync/status/{syncId}', [App\Http\Controllers\Api\SyncController::class, 'syncStatus'])->name('sync.status');
    
    // API status check
    Route::get('sync/status', [App\Http\Controllers\Api\SyncController::class, 'checkApiStatus'])->name('sync.api.status');
});

// API for unified data and comparison
Route::middleware(['auth', 'verified', 'permission.404:dashboard.analytics'])->prefix('api/dashboard')->name('api.dashboard.')->group(function () {
    Route::get('unified-data', [App\Http\Controllers\Api\DashboardApiController::class, 'getUnifiedData'])->name('unified-data');
    Route::get('comparison-data', [App\Http\Controllers\Api\DashboardApiController::class, 'getComparisonData'])->name('comparison-data');
    Route::get('filtered-data', [App\Http\Controllers\Api\DashboardApiController::class, 'getFilteredData'])->name('filtered-data');
    Route::get('data-sources-status', [App\Http\Controllers\Api\DashboardApiController::class, 'getDataSourcesStatus'])->name('data-sources-status');
    Route::post('refresh-cache', [App\Http\Controllers\Api\DashboardApiController::class, 'refreshCache'])->name('refresh-cache');
    Route::get('overview-data', [App\Http\Controllers\Api\DashboardApiController::class, 'getOverviewData'])->name('overview-data');
    Route::get('analytics-data', [App\Http\Controllers\Api\DashboardApiController::class, 'getAnalyticsData'])->name('analytics-data');
    Route::get('hierarchy-data', [App\Http\Controllers\Api\DashboardApiController::class, 'getHierarchyData'])->name('hierarchy-data');
});

// API for hierarchy
Route::middleware(['auth', 'verified', 'permission.404:facebook.hierarchy.api'])->prefix('api/hierarchy')->name('api.hierarchy.')->group(function () {
    Route::get('businesses', [App\Http\Controllers\Api\HierarchyController::class, 'getBusinesses'])->name('businesses');
    Route::get('accounts', [App\Http\Controllers\Api\HierarchyController::class, 'getAccounts'])->name('accounts');
    Route::get('campaigns', [App\Http\Controllers\Api\HierarchyController::class, 'getCampaigns'])->name('campaigns');
    Route::get('adsets', [App\Http\Controllers\Api\HierarchyController::class, 'getAdSets'])->name('adsets');
    Route::get('ads', [App\Http\Controllers\Api\HierarchyController::class, 'getAds'])->name('ads');
});

// Note: The API for hierarchy is defined above using App\Http\Controllers\Api\HierarchyController.
// Avoid defining duplicate routes here to prevent shadowing the API response shape expected by the UI.

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
