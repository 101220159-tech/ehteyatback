<?php

use App\Http\Controllers\Api\V1\Admin\AdminAreaController;
use App\Http\Controllers\Api\V1\Admin\AdminBackupStatusController;
use App\Http\Controllers\Api\V1\Admin\AdminBookingController;
use App\Http\Controllers\Api\V1\Admin\AdminChatController;
use App\Http\Controllers\Api\V1\Admin\AdminCategoryController;
use App\Http\Controllers\Api\V1\Admin\AdminDashboardController;
use App\Http\Controllers\Api\V1\Admin\AdminStatisticsController;
use App\Http\Controllers\Api\V1\Admin\AdminNotificationController;
use App\Http\Controllers\Api\V1\Admin\AdminPaymentController;
use App\Http\Controllers\Api\V1\Admin\AdminPermissionController;
use App\Http\Controllers\Api\V1\Admin\AdminProviderController;
use App\Http\Controllers\Api\V1\Admin\AdminProviderProjectController;
use App\Http\Controllers\Api\V1\Admin\AdminReviewController;
use App\Http\Controllers\Api\V1\Admin\AdminRoleController;
use App\Http\Controllers\Api\V1\Admin\AdminServiceController;
use App\Http\Controllers\Api\V1\Admin\AdminTransportController;
use App\Http\Controllers\Api\V1\Admin\AdminUserController;
use App\Http\Controllers\Api\V1\Admin\AdminZoneController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ChatbotController;
use App\Http\Controllers\Api\V1\Customer\CustomerAddressController;
use App\Http\Controllers\Api\V1\Customer\CustomerZoneController;
use App\Http\Controllers\Api\V1\Customer\CustomerBookingController;
use App\Http\Controllers\Api\V1\Customer\CustomerChatController;
use App\Http\Controllers\Api\V1\Customer\CustomerDashboardController;
use App\Http\Controllers\Api\V1\Customer\CustomerNotificationController;
use App\Http\Controllers\Api\V1\Customer\CustomerProfileController;
use App\Http\Controllers\Api\V1\Customer\CustomerReviewController;
use App\Http\Controllers\Api\V1\Customer\CustomerTransportController;
use App\Http\Controllers\Api\V1\Customer\SearchController;
use App\Http\Controllers\Api\V1\MapsController;
use App\Http\Controllers\Api\V1\Provider\ProviderAvailabilityController;
use App\Http\Controllers\Api\V1\Provider\ProviderBookingController;
use App\Http\Controllers\Api\V1\Provider\ProviderCertificationController;
use App\Http\Controllers\Api\V1\Provider\ProviderChatController;
use App\Http\Controllers\Api\V1\Provider\ProviderDashboardController;
use App\Http\Controllers\Api\V1\Provider\ProviderDocumentController;
use App\Http\Controllers\Api\V1\Provider\ProviderEarningsController;
use App\Http\Controllers\Api\V1\Provider\ProviderProfileController;
use App\Http\Controllers\Api\V1\Provider\ProviderProjectController;
use App\Http\Controllers\Api\V1\Provider\ProviderReviewController;
use App\Http\Controllers\Api\V1\Provider\ProviderScheduleController;
use App\Http\Controllers\Api\V1\Provider\ProviderServiceController;
use App\Http\Controllers\Api\V1\Provider\ProviderStatusController;
use App\Http\Controllers\Api\V1\PublicProviderController;
use App\Http\Controllers\Api\V1\ServiceController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// ── Health check ─────────────────────────────────────────────────────────────

Route::get('/health', function () {
    try { DB::connection()->getPdo(); $db = 'connected'; } catch (\Throwable) { $db = 'error'; }
    try { Cache::put('hc', 'ok', 10); $cache = Cache::get('hc') === 'ok' ? 'connected' : 'error'; } catch (\Throwable) { $cache = 'error'; }

    return response()->json([
        'status'      => 'healthy',
        'timestamp'   => now()->toIso8601String(),
        'environment' => app()->environment(),
        'database'    => $db,
        'cache'       => $cache,
        'app'         => config('app.name'),
    ]);
});

// ── API v1 ───────────────────────────────────────────────────────────────────

Route::prefix('v1')->name('api.v1.')->group(function () {

    // ── Auth (public) ─────────────────────────────────────────────────────

    Route::middleware('throttle:auth')->group(function () {
        Route::post('auth/register',         [AuthController::class, 'register']);
        Route::post('auth/login',            [AuthController::class, 'login']);
        Route::post('auth/forgot-password',  [AuthController::class, 'forgotPassword']);
        Route::post('auth/reset-password',   [AuthController::class, 'resetPassword']);
    });

    // Google OAuth
    Route::get('auth/google',               [AuthController::class, 'googleRedirect']);
    Route::get('auth/google/callback',      [AuthController::class, 'googleCallback']);

    // Email verification (signed URL)
    Route::match(['get', 'post'], 'auth/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    // ── Public catalog & providers ────────────────────────────────────────

    Route::middleware('throttle:public')->group(function () {
        Route::get('categories',                    [AdminCategoryController::class, 'index']);
        Route::get('categories/{id}/services',      [AdminServiceController::class, 'byCategory'])->whereUuid('id');
        Route::get('services',                      [ServiceController::class, 'index']);
        Route::get('services/{id}',                 [ServiceController::class, 'show'])->whereUuid('id');
        Route::get('providers',                     [PublicProviderController::class, 'index']);
        Route::get('providers/{id}',                [PublicProviderController::class, 'show'])->whereUuid('id');

        // Maps proxy
        Route::get('maps/geocode',                  [MapsController::class, 'geocode']);
        Route::get('maps/places-autocomplete',      [MapsController::class, 'placesAutocomplete']);
        Route::get('maps/distance-matrix',          [MapsController::class, 'distanceMatrix']);
    });

    // ── Authenticated (all roles) ─────────────────────────────────────────

    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::post('auth/logout',           [AuthController::class, 'logout']);
        Route::get('auth/me',                [AuthController::class, 'me']);
        Route::get('auth/permissions',       [AuthController::class, 'permissions'])->name('auth.permissions');
        Route::put('auth/profile',           [AuthController::class, 'updateProfile']);
        Route::post('auth/change-password',  [AuthController::class, 'changePassword']);
        Route::post('user/fcm-token',        [AuthController::class, 'saveFcmToken']);
    });

    // ── Customer ──────────────────────────────────────────────────────────

    Route::middleware(['auth:sanctum', 'role:customer', 'throttle:api'])->prefix('customer')->group(function () {
        // Dashboard
        Route::get('dashboard',                     [CustomerDashboardController::class, 'dashboard']);
        Route::get('dashboard/statistics',          [CustomerDashboardController::class, 'statistics']);
        Route::get('history',                       [CustomerDashboardController::class, 'history']);

        // Bookings
        Route::get('bookings',                      [CustomerBookingController::class, 'index']);
        Route::post('bookings',                     [CustomerBookingController::class, 'store']);
        Route::get('bookings/{id}',                 [CustomerBookingController::class, 'show'])->whereUuid('id');
        Route::put('bookings/{id}/cancel',          [CustomerBookingController::class, 'cancel'])->whereUuid('id');
        Route::put('bookings/{id}/reschedule/accept', [CustomerBookingController::class, 'acceptReschedule'])->whereUuid('id');
        Route::put('bookings/{id}/reschedule/reject', [CustomerBookingController::class, 'rejectReschedule'])->whereUuid('id');

        // Reviews
        Route::post('reviews',                      [CustomerReviewController::class, 'store']);
        Route::put('reviews/{id}',                  [CustomerReviewController::class, 'update'])->whereUuid('id');

        // Chat
        Route::get('chats',                         [CustomerChatController::class, 'index']);
        Route::post('chats',                        [CustomerChatController::class, 'store']);
        Route::get('chats/{id}/messages',           [CustomerChatController::class, 'messages'])->whereUuid('id');
        Route::post('chats/{id}/messages',          [CustomerChatController::class, 'sendMessage'])->whereUuid('id');
        Route::post('chats/{id}/typing',            [CustomerChatController::class, 'typing'])->whereUuid('id');
        Route::put('chats/{id}/messages/{msgId}/read', [CustomerChatController::class, 'markMessageRead'])->whereUuid('id')->whereUuid('msgId');

        // Notifications
        Route::get('notifications',                 [CustomerNotificationController::class, 'index']);
        Route::put('notifications/{id}/read',       [CustomerNotificationController::class, 'markRead'])->whereUuid('id');

        // Zone detection
        Route::get('zones/detect',                  [CustomerZoneController::class, 'detect']);

        // Search
        Route::get('providers/search',              [SearchController::class, 'providers']);
        Route::get('search/suggestions',            [SearchController::class, 'getSearchSuggestions']);
        Route::get('search/categories',             [SearchController::class, 'getServiceCategories']);
        Route::get('search/categories/{id}/services', [SearchController::class, 'getServicesByCategory'])->whereUuid('id');

        // Addresses
        Route::apiResource('addresses', CustomerAddressController::class)->whereUuid('addresses');

        // Profile / location
        Route::post('profile/avatar',               [CustomerProfileController::class, 'storeAvatar']);
        Route::get('profile/location',              [CustomerProfileController::class, 'getLocation']);
        Route::post('profile/location',             [CustomerProfileController::class, 'updateLocation']);
        Route::put('profile',                       [CustomerProfileController::class, 'update']);

        // AI chatbot (local Llama via Ollama)
        Route::prefix('chatbot')->group(function () {
            Route::post('/message', [ChatbotController::class, 'message']);
            Route::get('/conversations', [ChatbotController::class, 'conversations']);
            Route::get('/conversations/{id}', [ChatbotController::class, 'showConversation'])->whereUuid('id');
        });

        // Taxi & delivery transport (shared map + pricing on frontend)
        Route::prefix('transport')->group(function () {
            Route::get('route/calculate', [CustomerTransportController::class, 'routeCalculate']);
            Route::get('estimate', [CustomerTransportController::class, 'estimate']);
            Route::post('taxi/book', [CustomerTransportController::class, 'bookTaxi']);
            Route::post('delivery/book', [CustomerTransportController::class, 'bookDelivery']);
            Route::get('live-tracking', [CustomerTransportController::class, 'liveTracking']);
        });
    });

    // Short aliases for mobile / external docs (same customer auth)
    Route::middleware(['auth:sanctum', 'role:customer', 'throttle:api'])->group(function () {
        Route::get('route/calculate', [CustomerTransportController::class, 'routeCalculate']);
        Route::get('booking/estimate-price', [CustomerTransportController::class, 'estimate']);
        Route::get('booking/live-tracking', [CustomerTransportController::class, 'liveTracking']);
        Route::post('taxi/book', [CustomerTransportController::class, 'bookTaxi']);
        Route::post('delivery/book', [CustomerTransportController::class, 'bookDelivery']);
    });

    // ── Provider ──────────────────────────────────────────────────────────

    Route::middleware(['auth:sanctum', 'role:provider', 'throttle:api'])->prefix('provider')->group(function () {
        // Dashboard
        Route::get('dashboard',                     [ProviderDashboardController::class, 'dashboard']);

        // Status (toggle active/inactive)
        Route::get('status',                        [ProviderStatusController::class, 'show']);
        Route::put('status',                        [ProviderStatusController::class, 'update']);

        // Schedule management
        Route::get('schedule',                      [ProviderScheduleController::class, 'index']);
        Route::get('calendar/events',               [ProviderScheduleController::class, 'calendarEvents']);

        // Bookings
        Route::get('bookings',                      [ProviderBookingController::class, 'index']);
        Route::get('bookings/{id}',                 [ProviderBookingController::class, 'show'])->whereUuid('id');
        Route::put('bookings/{id}/accept',          [ProviderBookingController::class, 'accept'])->whereUuid('id');
        Route::post('bookings/{id}/accept',         [ProviderBookingController::class, 'accept'])->whereUuid('id');
        Route::put('bookings/{id}/reject',          [ProviderBookingController::class, 'reject'])->whereUuid('id');
        Route::post('bookings/{id}/reject',         [ProviderBookingController::class, 'reject'])->whereUuid('id');
        Route::put('bookings/{id}/cancel',          [ProviderBookingController::class, 'cancel'])->whereUuid('id');
        Route::post('bookings/{id}/cancel',         [ProviderBookingController::class, 'cancel'])->whereUuid('id');
        Route::post('bookings/{id}/reschedule',     [ProviderBookingController::class, 'requestReschedule'])->whereUuid('id');
        Route::put('bookings/{id}/complete',        [ProviderBookingController::class, 'complete'])->whereUuid('id');
        Route::post('bookings/{id}/complete',        [ProviderBookingController::class, 'complete'])->whereUuid('id');

        // Alias routes (singular booking) for schedule workflow clients
        Route::post('booking/{id}/accept',          [ProviderBookingController::class, 'accept'])->whereUuid('id');
        Route::post('booking/{id}/complete',        [ProviderBookingController::class, 'complete'])->whereUuid('id');
        Route::post('booking/{id}/cancel',          [ProviderBookingController::class, 'cancel'])->whereUuid('id');

        // Profile
        Route::get('profile',                       [ProviderProfileController::class, 'show']);
        Route::put('profile',                       [ProviderProfileController::class, 'update']);
        Route::post('profile/avatar',               [ProviderProfileController::class, 'storeAvatar']);
        Route::get('profile/location',              [ProviderProfileController::class, 'getLocation']);
        Route::put('profile/location',              [ProviderProfileController::class, 'updateLocation']);
        Route::get('public-profile',                [ProviderProfileController::class, 'publicProfile']);

        // Certifications
        Route::get('certifications',                [ProviderCertificationController::class, 'index']);
        Route::post('certifications',               [ProviderCertificationController::class, 'store']);
        Route::get('certifications/{id}',           [ProviderCertificationController::class, 'show'])->whereUuid('id');
        Route::post('certifications/{id}',          [ProviderCertificationController::class, 'update'])->whereUuid('id');
        Route::get('certifications/{id}/download',  [ProviderCertificationController::class, 'download'])->whereUuid('id');
        Route::delete('certifications/{id}',        [ProviderCertificationController::class, 'destroy'])->whereUuid('id');

        // Official documents
        Route::get('documents',                     [ProviderDocumentController::class, 'index']);
        Route::post('documents',                    [ProviderDocumentController::class, 'store']);
        Route::get('documents/{id}',                [ProviderDocumentController::class, 'show'])->whereUuid('id');
        Route::get('documents/{id}/download',       [ProviderDocumentController::class, 'download'])->whereUuid('id');
        Route::delete('documents/{id}',             [ProviderDocumentController::class, 'destroy'])->whereUuid('id');

        // Services
        Route::get('services',                      [ProviderServiceController::class, 'index']);
        Route::post('services',                     [ProviderServiceController::class, 'store']);
        Route::put('services/{id}',                 [ProviderServiceController::class, 'update'])->whereUuid('id');
        Route::delete('services/{id}',              [ProviderServiceController::class, 'destroy'])->whereUuid('id');

        // Projects / portfolio
        Route::get('projects',                      [ProviderProjectController::class, 'index']);
        Route::post('projects',                     [ProviderProjectController::class, 'store']);
        Route::put('projects/{id}',                 [ProviderProjectController::class, 'update'])->whereUuid('id');
        Route::delete('projects/{id}',              [ProviderProjectController::class, 'destroy'])->whereUuid('id');
        Route::post('projects/{id}/images',                      [ProviderProjectController::class, 'uploadImages'])->whereUuid('id');
        Route::post('projects/{id}/images/{imageId}',            [ProviderProjectController::class, 'replaceImage'])->whereUuid('id')->whereUuid('imageId');
        Route::delete('projects/{id}/images/{imageId}',          [ProviderProjectController::class, 'destroyImage'])->whereUuid('id')->whereUuid('imageId');

        // Availability
        Route::get('availability',                  [ProviderAvailabilityController::class, 'index']);
        Route::post('availability',                 [ProviderAvailabilityController::class, 'store']);
        Route::put('availability/{id}',             [ProviderAvailabilityController::class, 'update'])->whereUuid('id');
        Route::delete('availability/{id}',          [ProviderAvailabilityController::class, 'destroy'])->whereUuid('id');

        // Reviews
        Route::get('reviews',                       [ProviderReviewController::class, 'index']);
        Route::get('reviews/analyze',               [ProviderReviewController::class, 'analyze']);

        // Chat
        Route::get('chats',                         [ProviderChatController::class, 'index']);
        Route::get('chats/{id}/messages',           [ProviderChatController::class, 'messages'])->whereUuid('id');
        Route::post('chats/{id}/messages',          [ProviderChatController::class, 'sendMessage'])->whereUuid('id');
        Route::post('chats/{id}/typing',            [ProviderChatController::class, 'typing'])->whereUuid('id');
        Route::put('chats/{id}/messages/{msgId}/read', [ProviderChatController::class, 'markMessageRead'])->whereUuid('id')->whereUuid('msgId');

        // Earnings
        Route::get('earnings',                      [ProviderEarningsController::class, 'index']);
        Route::get('earnings/chart',                [ProviderEarningsController::class, 'chart']);

        // Notifications
        Route::get('notifications',                 [\App\Http\Controllers\Api\V1\Provider\ProviderNotificationController::class, 'index']);
        Route::put('notifications/{id}/read',       [\App\Http\Controllers\Api\V1\Provider\ProviderNotificationController::class, 'markRead'])->whereUuid('id');
        Route::put('notifications/read-all',        [\App\Http\Controllers\Api\V1\Provider\ProviderNotificationController::class, 'markAllRead']);
    });

    // ── Admin / Super Admin ───────────────────────────────────────────────

    Route::middleware(['auth:sanctum', 'role:super_admin,admin', 'throttle:api'])->prefix('admin')->group(function () {
        // Dashboard & statistics
        Route::get('dashboard/stats',               [AdminDashboardController::class, 'stats']);
        Route::get('dashboard/stats/overview',      [AdminStatisticsController::class, 'overview']);
        Route::get('dashboard/stats/bookings-by-day', [AdminStatisticsController::class, 'bookingsByDay']);

        // RECO chatbot (Ollama — admin scope)
        Route::prefix('chatbot')->group(function () {
            Route::post('/message', [ChatbotController::class, 'message']);
            Route::get('/conversations', [ChatbotController::class, 'conversations']);
            Route::get('/conversations/{id}', [ChatbotController::class, 'showConversation'])->whereUuid('id');
        });

        // Chats (moderation — list + messages; Echo channel auth includes admin in routes/channels.php)
        Route::get('chats',                         [AdminChatController::class, 'index']);
        Route::post('chats',                        [AdminChatController::class, 'store']);
        Route::get('chats/{id}/messages',           [AdminChatController::class, 'messages'])->whereUuid('id');
        Route::post('chats/{id}/messages',          [AdminChatController::class, 'sendMessage'])->whereUuid('id');
        Route::put('chats/{id}/messages/{msgId}/read', [AdminChatController::class, 'markMessageRead'])->whereUuid('id')->whereUuid('msgId');

        // Users
        Route::get('users',                         [AdminUserController::class, 'index']);
        Route::post('users',                        [AdminUserController::class, 'store']);
        Route::get('users/{id}',                    [AdminUserController::class, 'show'])->whereUuid('id');
        Route::put('users/{id}',                    [AdminUserController::class, 'update'])->whereUuid('id');
        Route::delete('users/{id}',                 [AdminUserController::class, 'destroy'])->whereUuid('id');
        Route::post('users/{id}/assign-role',       [AdminUserController::class, 'assignRole'])->whereUuid('id');
        Route::post('users/{id}/grant-permission',  [AdminUserController::class, 'grantPermission'])->whereUuid('id');

        // Providers
        Route::get('providers',                          [AdminProviderController::class, 'index']);
        Route::post('providers',                         [AdminProviderController::class, 'store']);
        Route::get('providers/by-service',               [AdminProviderController::class, 'byService']);
        Route::get('providers/unpaid',                   [AdminProviderController::class, 'unpaid']);
        Route::get('providers/{id}',                     [AdminProviderController::class, 'show'])->whereUuid('id');
        Route::post('providers/{id}/verify',        [AdminProviderController::class, 'verify'])->whereUuid('id');
        Route::post('providers/{id}/approve',       [AdminProviderController::class, 'approve'])->whereUuid('id');
        Route::put('providers/{id}/chat',           [AdminPaymentController::class, 'toggleProviderChat'])->whereUuid('id');
        Route::put('providers/{id}/vip',            [AdminPaymentController::class, 'toggleProviderVip'])->whereUuid('id');

        // Provider zone assignments (alternative path — assigns a single provider to zones)
        Route::post('providers/{id}/assign-zones',  [AdminZoneController::class, 'assignProviderToZones'])->whereUuid('id');

        // Provider service management (admin)
        Route::get('providers/{id}/services',                        [AdminProviderController::class, 'listServices'])->whereUuid('id');
        Route::post('providers/{id}/services',                       [AdminProviderController::class, 'assignService'])->whereUuid('id');
        Route::delete('providers/{id}/services/{serviceId}',         [AdminProviderController::class, 'removeService'])->whereUuid('id')->whereUuid('serviceId');

        // Provider document & certification viewer (admin)
        Route::get('providers/{id}/documents/{docId}/view',          [AdminProviderController::class, 'viewDocument'])->whereUuid('id')->whereUuid('docId');
        Route::get('providers/{id}/certifications/{certId}/view',    [AdminProviderController::class, 'viewCertification'])->whereUuid('id')->whereUuid('certId');

        // Provider portfolio (admin view)
        Route::get('providers/{providerId}/projects',         [AdminProviderProjectController::class, 'index'])->whereUuid('providerId');
        Route::post('providers/{providerId}/projects',        [AdminProviderProjectController::class, 'store'])->whereUuid('providerId');
        Route::put('providers/{providerId}/projects/{pId}',   [AdminProviderProjectController::class, 'update'])->whereUuid('providerId')->whereUuid('pId');
        Route::delete('providers/{providerId}/projects/{pId}',[AdminProviderProjectController::class, 'destroy'])->whereUuid('providerId')->whereUuid('pId');
        Route::post('providers/{providerId}/projects/{pId}/images', [AdminProviderProjectController::class, 'uploadImages'])->whereUuid('providerId')->whereUuid('pId');

        // Services & Categories
        Route::get('services',                      [AdminServiceController::class, 'index']);
        Route::post('services',                     [AdminServiceController::class, 'store']);
        Route::put('services/{id}',                 [AdminServiceController::class, 'update'])->whereUuid('id');
        Route::post('services/{id}',                [AdminServiceController::class, 'update'])->whereUuid('id');
        Route::delete('services/{id}',              [AdminServiceController::class, 'destroy'])->whereUuid('id');
        Route::get('categories',                    [AdminCategoryController::class, 'index']);
        Route::post('categories',                   [AdminCategoryController::class, 'store']);
        Route::put('categories/{id}',               [AdminCategoryController::class, 'update'])->whereUuid('id');
        Route::post('categories/{id}',              [AdminCategoryController::class, 'update'])->whereUuid('id');
        Route::delete('categories/{id}',            [AdminCategoryController::class, 'destroy'])->whereUuid('id');

        // Zones & Areas
        Route::get('zones',                         [AdminZoneController::class, 'index']);
        Route::post('zones',                        [AdminZoneController::class, 'store']);
        Route::get('zones/{id}',                    [AdminZoneController::class, 'show'])->whereUuid('id');
        Route::put('zones/{id}',                    [AdminZoneController::class, 'update'])->whereUuid('id');
        Route::delete('zones/{id}',                 [AdminZoneController::class, 'destroy'])->whereUuid('id');
        Route::get('zones/{id}/providers',          [AdminZoneController::class, 'zoneProviders'])->whereUuid('id');
        Route::post('zones/{id}/assign-providers',  [AdminZoneController::class, 'assignProvider'])->whereUuid('id');
        Route::delete('zones/{id}/providers/{pId}', [AdminZoneController::class, 'removeProvider'])->whereUuid('id')->whereUuid('pId');

        Route::get('zones/{zoneId}/areas',          [AdminAreaController::class, 'index'])->whereUuid('zoneId');
        Route::post('zones/{zoneId}/areas',         [AdminAreaController::class, 'store'])->whereUuid('zoneId');
        Route::get('zones/{zoneId}/areas/{id}',     [AdminAreaController::class, 'show'])->whereUuid('zoneId')->whereUuid('id');
        Route::put('zones/{zoneId}/areas/{id}',     [AdminAreaController::class, 'update'])->whereUuid('zoneId')->whereUuid('id');
        Route::delete('zones/{zoneId}/areas/{id}',  [AdminAreaController::class, 'destroy'])->whereUuid('zoneId')->whereUuid('id');

        // Payments & Subscriptions
        Route::get('payments',                      [AdminPaymentController::class, 'index']);
        Route::post('payments',                     [AdminPaymentController::class, 'store']);
        Route::get('payments/{id}',                 [AdminPaymentController::class, 'show'])->whereUuid('id');

        // Bookings
        Route::get('bookings',                      [AdminBookingController::class, 'index']);

        // Taxi & delivery transport bookings
        Route::get('transport/taxi-bookings',              [AdminTransportController::class, 'taxiBookings']);
        Route::put('transport/taxi-bookings/{id}',        [AdminTransportController::class, 'updateTaxiStatus'])->whereUuid('id');
        Route::get('transport/delivery-bookings',         [AdminTransportController::class, 'deliveryBookings']);
        Route::put('transport/delivery-bookings/{id}',    [AdminTransportController::class, 'updateDeliveryStatus'])->whereUuid('id');

        // Reviews
        Route::get('reviews',                       [AdminReviewController::class, 'index']);

        // Roles & Permissions
        Route::get('roles',                         [AdminRoleController::class, 'index']);
        Route::post('roles',                        [AdminRoleController::class, 'store']);
        Route::put('roles/{id}',                    [AdminRoleController::class, 'update'])->whereUuid('id');
        Route::delete('roles/{id}',                 [AdminRoleController::class, 'destroy'])->whereUuid('id');
        Route::get('permissions',                   [AdminPermissionController::class, 'index']);
        Route::post('permissions',                  [AdminPermissionController::class, 'store']);

        // Notifications
        Route::get('notifications',                 [AdminNotificationController::class, 'index']);
        Route::post('notifications/send',           [AdminNotificationController::class, 'send']);

        // System
        Route::get('system/backup-status',          AdminBackupStatusController::class);
    });
});
