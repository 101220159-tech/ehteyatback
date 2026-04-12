<?php

use App\Http\Controllers\Api\V1\Admin\AdminBackupStatusController;
use App\Http\Controllers\Api\V1\Admin\AdminBookingController;
use App\Http\Controllers\Api\V1\Admin\AdminCategoryController;
use App\Http\Controllers\Api\V1\Admin\AdminDashboardController;
use App\Http\Controllers\Api\V1\Admin\AdminNotificationController;
use App\Http\Controllers\Api\V1\Admin\AdminPermissionController;
use App\Http\Controllers\Api\V1\Admin\AdminProviderController;
use App\Http\Controllers\Api\V1\Admin\AdminProviderProjectController;
use App\Http\Controllers\Api\V1\Admin\AdminReviewController;
use App\Http\Controllers\Api\V1\Admin\AdminRoleController;
use App\Http\Controllers\Api\V1\Admin\AdminServiceController;
use App\Http\Controllers\Api\V1\Admin\AdminUserController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\MapsController;
use App\Http\Controllers\Api\V1\Customer\CustomerBookingController;
use App\Http\Controllers\Api\V1\Customer\CustomerChatController;
use App\Http\Controllers\Api\V1\Customer\CustomerDashboardController;
use App\Http\Controllers\Api\V1\Customer\CustomerAddressController;
use App\Http\Controllers\Api\V1\Customer\CustomerNotificationController;
use App\Http\Controllers\Api\V1\Customer\CustomerReviewController;
use App\Http\Controllers\Api\V1\Customer\SearchController;
use App\Http\Controllers\Api\V1\Customer\CustomerProfileController;
use App\Http\Controllers\Api\V1\Provider\ProviderAvailabilityController;
use App\Http\Controllers\Api\V1\Provider\ProviderBookingController;
use App\Http\Controllers\Api\V1\Provider\ProviderChatController;
use App\Http\Controllers\Api\V1\Provider\ProviderDashboardController;
use App\Http\Controllers\Api\V1\Provider\ProviderEarningsController;
use App\Http\Controllers\Api\V1\Provider\ProviderProfileController;
use App\Http\Controllers\Api\V1\Provider\ProviderProjectController;
use App\Http\Controllers\Api\V1\Provider\ProviderReviewController;
use App\Http\Controllers\Api\V1\Provider\ProviderServiceController;
use App\Http\Controllers\Api\V1\PublicProviderController;
use App\Http\Controllers\Api\V1\ServiceController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    try {
        DB::connection()->getPdo();
        $database = 'connected';
    } catch (\Throwable) {
        $database = 'error';
    }

    try {
        Cache::put('health_check', 'ok', 60);
        $cache = Cache::get('health_check') === 'ok' ? 'connected' : 'error';
    } catch (\Throwable) {
        $cache = 'error';
    }

    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toIso8601String(),
        'environment' => app()->environment(),
        'database' => $database,
        'cache' => $cache,
        'app' => config('app.name'),
    ]);
});

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::middleware(['throttle:auth'])->group(function () {
        Route::post('auth/register', [AuthController::class, 'register']);
        Route::post('auth/login', [AuthController::class, 'login']);
        Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('auth/reset-password', [AuthController::class, 'resetPassword']);
    });

    Route::match(['get', 'post'], 'auth/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::middleware(['throttle:public'])->group(function () {
        Route::get('services', [ServiceController::class, 'index']);
        Route::get('services/{id}', [ServiceController::class, 'show'])->whereNumber('id');
        Route::get('providers', [PublicProviderController::class, 'index']);
        Route::get('providers/{id}', [PublicProviderController::class, 'show'])->whereNumber('id');

        // Google Maps APIs (backend proxy).
        Route::get('maps/geocode', [MapsController::class, 'geocode']);
        Route::get('maps/places-autocomplete', [MapsController::class, 'placesAutocomplete']);
        Route::get('maps/distance-matrix', [MapsController::class, 'distanceMatrix']);
    });

    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::get('auth/permissions', [AuthController::class, 'permissions'])->name('auth.permissions');
        Route::get('test/permissions', [AuthController::class, 'permissionsTest'])->name('test.permissions');
        Route::put('auth/profile', [AuthController::class, 'updateProfile']);
        Route::post('auth/change-password', [AuthController::class, 'changePassword']);
        Route::post('user/fcm-token', [AuthController::class, 'saveFcmToken']);
    });

    Route::middleware(['auth:sanctum', 'role:customer', 'throttle:api'])->prefix('customer')->group(function () {
        Route::get('dashboard', [CustomerDashboardController::class, 'dashboard']);
        Route::get('bookings', [CustomerBookingController::class, 'index']);
        Route::post('bookings', [CustomerBookingController::class, 'store'])->middleware('permission:create_bookings');
        Route::get('bookings/{id}', [CustomerBookingController::class, 'show'])->whereNumber('id');
        Route::put('bookings/{id}/cancel', [CustomerBookingController::class, 'cancel'])->middleware('permission:cancel_own_bookings')->whereNumber('id');
        Route::get('history', [CustomerDashboardController::class, 'history']);
        Route::post('reviews', [CustomerReviewController::class, 'store'])->middleware('permission:create_reviews');
        Route::put('reviews/{id}', [CustomerReviewController::class, 'update'])->middleware('permission:edit_own_reviews')->whereNumber('id');
        Route::get('chats', [CustomerChatController::class, 'index']);
        Route::post('chats', [CustomerChatController::class, 'store']);
        Route::get('chats/{id}/messages', [CustomerChatController::class, 'messages'])->whereNumber('id');
        Route::post('chats/{id}/messages', [CustomerChatController::class, 'sendMessage'])->middleware('permission:send_messages')->whereNumber('id');
        Route::post('chats/{id}/typing', [CustomerChatController::class, 'typing'])->whereNumber('id');
        Route::put('chats/{id}/messages/{messageId}/read', [CustomerChatController::class, 'markMessageRead'])->middleware('permission:send_messages')->whereNumber('id')->whereNumber('messageId');
        Route::get('notifications', [CustomerNotificationController::class, 'index']);
        Route::put('notifications/{id}/read', [CustomerNotificationController::class, 'markRead'])->whereNumber('id');
        Route::get('providers/search', [SearchController::class, 'providers'])->middleware('permission:view_provider_profiles');
        Route::get('search/suggestions', [SearchController::class, 'getSearchSuggestions']);
        Route::get('search/categories', [SearchController::class, 'getServiceCategories']);
        Route::get('search/categories/{categoryId}/services', [SearchController::class, 'getServicesByCategory'])->whereNumber('categoryId');

        Route::get('addresses', [CustomerAddressController::class, 'index'])->middleware('permission:manage_own_addresses');
        Route::post('addresses', [CustomerAddressController::class, 'store'])->middleware('permission:manage_own_addresses');
        Route::get('addresses/{id}', [CustomerAddressController::class, 'show'])->middleware('permission:manage_own_addresses')->whereNumber('id');
        Route::put('addresses/{id}', [CustomerAddressController::class, 'update'])->middleware('permission:manage_own_addresses')->whereNumber('id');
        Route::delete('addresses/{id}', [CustomerAddressController::class, 'destroy'])->middleware('permission:manage_own_addresses')->whereNumber('id');

        Route::get('profile/location', [CustomerProfileController::class, 'getLocation']);
        Route::post('profile/location', [CustomerProfileController::class, 'updateLocation']);
        Route::put('profile', [CustomerProfileController::class, 'update']);
    });

    Route::middleware(['auth:sanctum', 'verified', 'role:provider', 'throttle:api'])->prefix('provider')->group(function () {
        Route::get('dashboard', [ProviderDashboardController::class, 'dashboard']);
        Route::get('bookings', [ProviderBookingController::class, 'index']);
        Route::put('bookings/{id}/status', [ProviderBookingController::class, 'updateStatus'])->middleware('permission:manage_own_bookings')->whereNumber('id');
        Route::get('bookings/{id}', [ProviderBookingController::class, 'show'])->whereNumber('id');
        Route::get('profile', [ProviderProfileController::class, 'show']);
        Route::put('profile', [ProviderProfileController::class, 'update'])->middleware('permission:manage_own_profile');
        Route::get('services', [ProviderServiceController::class, 'index']);
        Route::post('services', [ProviderServiceController::class, 'store'])->middleware('permission:manage_services');
        Route::put('services/{id}', [ProviderServiceController::class, 'update'])->middleware('permission:manage_services')->whereNumber('id');
        Route::delete('services/{id}', [ProviderServiceController::class, 'destroy'])->middleware('permission:manage_services')->whereNumber('id');
        Route::get('projects', [ProviderProjectController::class, 'index']);
        Route::post('projects', [ProviderProjectController::class, 'store'])->middleware('permission:manage_portfolio');
        Route::put('projects/{id}', [ProviderProjectController::class, 'update'])->middleware('permission:manage_portfolio')->whereNumber('id');
        Route::delete('projects/{id}', [ProviderProjectController::class, 'destroy'])->middleware('permission:manage_portfolio')->whereNumber('id');
        Route::post('projects/{id}/images', [ProviderProjectController::class, 'uploadImages'])->middleware('permission:manage_portfolio')->whereNumber('id');
        Route::get('availability', [ProviderAvailabilityController::class, 'index']);
        Route::post('availability', [ProviderAvailabilityController::class, 'store'])->middleware('permission:manage_own_availability');
        Route::put('availability/{id}', [ProviderAvailabilityController::class, 'update'])->middleware('permission:manage_own_availability')->whereNumber('id');
        Route::get('reviews', [ProviderReviewController::class, 'index'])->middleware('permission:view_own_reviews');
        Route::get('chats', [ProviderChatController::class, 'index']);
        Route::get('chats/{id}/messages', [ProviderChatController::class, 'messages'])->whereNumber('id');
        Route::post('chats/{id}/messages', [ProviderChatController::class, 'sendMessage'])->middleware('permission:send_messages')->whereNumber('id');
        Route::post('chats/{id}/typing', [ProviderChatController::class, 'typing'])->whereNumber('id');
        Route::put('chats/{id}/messages/{messageId}/read', [ProviderChatController::class, 'markMessageRead'])->middleware('permission:send_messages')->whereNumber('id')->whereNumber('messageId');
        Route::get('earnings', [ProviderEarningsController::class, 'index'])->middleware('permission:view_own_earnings');
        Route::get('public-profile', [ProviderProfileController::class, 'publicProfile']);
        Route::get('profile/location', [ProviderProfileController::class, 'getLocation']);
        Route::put('profile/location', [ProviderProfileController::class, 'updateLocation']);
    });

    Route::middleware(['auth:sanctum', 'verified', 'role:super_admin,admin', 'throttle:api'])->prefix('admin')->group(function () {
        Route::get('dashboard/stats', [AdminDashboardController::class, 'stats'])->middleware('permission:view_admin_dashboard');
        Route::get('users', [AdminUserController::class, 'index'])->middleware('permission:view_users');
        Route::post('users', [AdminUserController::class, 'store'])->middleware('permission:create_users');
        Route::get('users/{id}', [AdminUserController::class, 'show'])->middleware('permission:view_users')->whereNumber('id');
        Route::put('users/{id}', [AdminUserController::class, 'update'])->middleware('permission:edit_users')->whereNumber('id');
        Route::delete('users/{id}', [AdminUserController::class, 'destroy'])->middleware('permission:delete_users')->whereNumber('id');
        Route::post('users/{id}/assign-role', [AdminUserController::class, 'assignRole'])->middleware('permission:manage_roles')->whereNumber('id');
        Route::post('users/{id}/grant-permission', [AdminUserController::class, 'grantPermission'])->middleware('permission:manage_permissions')->whereNumber('id');
        Route::get('providers', [AdminProviderController::class, 'index'])->middleware('permission:view_providers');
        Route::get('providers/{id}', [AdminProviderController::class, 'show'])->middleware('permission:view_providers')->whereNumber('id');
        Route::post('providers/{id}/verify', [AdminProviderController::class, 'verify'])->middleware('permission:verify_providers')->whereNumber('id');
        Route::post('providers/{id}/approve', [AdminProviderController::class, 'approve'])->middleware('permission:verify_providers')->whereNumber('id');
        Route::get('providers/{providerId}/projects', [AdminProviderProjectController::class, 'index'])->middleware('permission:manage_portfolio')->whereNumber('providerId');
        Route::post('providers/{providerId}/projects', [AdminProviderProjectController::class, 'store'])->middleware('permission:manage_portfolio')->whereNumber('providerId');
        Route::put('providers/{providerId}/projects/{projectId}', [AdminProviderProjectController::class, 'update'])->middleware('permission:manage_portfolio')->whereNumber('providerId')->whereNumber('projectId');
        Route::delete('providers/{providerId}/projects/{projectId}', [AdminProviderProjectController::class, 'destroy'])->middleware('permission:manage_portfolio')->whereNumber('providerId')->whereNumber('projectId');
        Route::post('providers/{providerId}/projects/{projectId}/images', [AdminProviderProjectController::class, 'uploadImages'])->middleware('permission:manage_portfolio')->whereNumber('providerId')->whereNumber('projectId');
        Route::get('services', [AdminServiceController::class, 'index']);
        Route::post('services', [AdminServiceController::class, 'store'])->middleware('permission:manage_services');
        Route::put('services/{id}', [AdminServiceController::class, 'update'])->middleware('permission:manage_services')->whereNumber('id');
        Route::delete('services/{id}', [AdminServiceController::class, 'destroy'])->middleware('permission:manage_services')->whereNumber('id');
        Route::get('categories', [AdminCategoryController::class, 'index']);
        Route::post('categories', [AdminCategoryController::class, 'store']);
        Route::put('categories/{id}', [AdminCategoryController::class, 'update'])->whereNumber('id');
        Route::delete('categories/{id}', [AdminCategoryController::class, 'destroy'])->whereNumber('id');
        Route::get('bookings', [AdminBookingController::class, 'index'])->middleware('permission:view_all_bookings');
        Route::get('reviews', [AdminReviewController::class, 'index']);
        Route::get('roles', [AdminRoleController::class, 'index'])->middleware('permission:manage_roles');
        Route::post('roles', [AdminRoleController::class, 'store'])->middleware('permission:manage_roles');
        Route::put('roles/{id}', [AdminRoleController::class, 'update'])->middleware('permission:manage_roles')->whereNumber('id');
        Route::delete('roles/{id}', [AdminRoleController::class, 'destroy'])->middleware('permission:manage_roles')->whereNumber('id');
        Route::get('permissions', [AdminPermissionController::class, 'index'])->middleware('permission:manage_permissions');
        Route::post('permissions', [AdminPermissionController::class, 'store'])->middleware('permission:manage_permissions');
        Route::get('notifications', [AdminNotificationController::class, 'index']);
        Route::post('notifications/send', [AdminNotificationController::class, 'send'])->middleware('permission:send_system_notifications');
        Route::get('system/backup-status', AdminBackupStatusController::class)->middleware('permission:view_admin_dashboard');
    });
});
