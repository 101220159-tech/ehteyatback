<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProviderResource;
use App\Jobs\SendEmailJob;
use App\Mail\ProviderVerification;
use App\Models\Provider;
use App\Models\ProviderCertification;
use App\Models\ProviderDocument;
use App\Models\ProviderService;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules\Password;

class AdminProviderController extends Controller
{
    /**
     * Admin creates a provider account directly.
     *
     * POST /api/v1/admin/providers
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            // User fields
            'name'                  => ['required', 'string', 'max:255'],
            'email'                 => ['required', 'email', 'unique:users,email'],
            'password'              => ['required', 'confirmed', Password::defaults()],
            'password_confirmation' => ['required', 'string'],
            'phone'                 => ['nullable', 'string', 'max:50'],
            'latitude'              => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'             => ['nullable', 'numeric', 'between:-180,180'],
            'address'               => ['nullable', 'string', 'max:2048'],
            // Provider fields
            'bio'                   => ['nullable', 'string'],
            'experience_years'      => ['nullable', 'integer', 'min:0'],
            // Optional: verify immediately
            'is_verified'           => ['sometimes', 'boolean'],
        ]);

        $providerRole = Role::query()->where('name', 'provider')->firstOrFail();

        // Create the user account
        $user = User::query()->create([
            'name'              => $data['name'],
            'email'             => $data['email'],
            'password'          => $data['password'],
            'phone'             => $data['phone'] ?? null,
            'latitude'          => $data['latitude'] ?? null,
            'longitude'         => $data['longitude'] ?? null,
            'address'           => $data['address'] ?? null,
            'role_id'           => $providerRole->id,
            'email_verified_at' => now(), // admin-created accounts are pre-verified
        ]);

        // Create the provider profile
        $shouldVerify = (bool) ($data['is_verified'] ?? false);

        $provider = Provider::query()->create([
            'user_id'          => $user->id,
            'bio'              => $data['bio'] ?? null,
            'experience_years' => $data['experience_years'] ?? 0,
            'is_verified'      => $shouldVerify,
            'verified_at'      => $shouldVerify ? now() : null,
            'status'           => $shouldVerify ? 'active' : 'pending',
        ]);

        $provider->load(['user', 'services.category']);
        $provider->loadCount('reviews');

        return response()->json([
            'success' => true,
            'message' => 'Provider created successfully.',
            'data'    => new ProviderResource($provider),
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $query = Provider::query()
            ->with(['user', 'zones'])
            ->withCount(['reviews', 'zones'])
            ->orderByDesc('created_at');

        if ($request->filled('search')) {
            $raw = $request->string('search')->trim()->toString();
            if ($raw !== '') {
                $term = '%'.$raw.'%';
                $query->where(function ($q) use ($term) {
                    $q->where('bio', 'LIKE', $term)
                        ->orWhere('google_place_id', 'LIKE', $term)
                        ->orWhereHas('user', function ($uq) use ($term) {
                            $uq->where('name', 'LIKE', $term)
                                ->orWhere('email', 'LIKE', $term)
                                ->orWhere('phone', 'LIKE', $term)
                                ->orWhere('address', 'LIKE', $term);
                        })
                        ->orWhereHas('services', fn ($sq) => $sq->where('name', 'LIKE', $term));
                });
            }
        }

        $items = $query->paginate($request->integer('per_page', 15));

        return ProviderResource::collection($items)->response();
    }

    /**
     * List providers that offer a specific service.
     *
     * GET /api/v1/admin/providers/by-service?service_id=uuid
     */
    public function byService(Request $request): JsonResponse
    {
        $data = $request->validate([
            'service_id' => ['required', 'uuid', 'exists:services,id'],
        ]);

        $items = Provider::query()
            ->with(['user', 'zones'])
            ->withCount(['reviews', 'zones'])
            ->whereHas('providerServices', fn ($q) => $q->where('service_id', $data['service_id']))
            ->with(['providerServices' => fn ($q) => $q->where('service_id', $data['service_id'])->with('service.category')])
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return ProviderResource::collection($items)->response();
    }

    /**
     * List providers who have no active subscription (never paid or subscription expired).
     *
     * GET /api/v1/admin/providers/unpaid
     */
    public function unpaid(Request $request): JsonResponse
    {
        $items = Provider::query()
            ->with(['user', 'zones', 'payments'])
            ->withCount(['reviews', 'zones'])
            ->where(function ($q) {
                $q->whereNull('subscription_expires_at')           // never paid
                  ->orWhere('subscription_expires_at', '<', now()); // subscription expired
            })
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return ProviderResource::collection($items)->response();
    }

    public function show(string $id): ProviderResource
    {
        $provider = Provider::query()
            ->with([
                'user',
                'zones',
                'services.category',
                'certifications',
                'documents',
                'projects.images',
                'availabilitySlots',
                'payments',
            ])
            ->withCount(['reviews', 'zones'])
            ->findOrFail($id);

        return new ProviderResource($provider);
    }

    /**
     * Verify / unverify a provider (toggle verification + role).
     *
     * POST /api/v1/admin/providers/{id}/verify
     * Body: { "is_verified": true|false, "verification_notes"?: string }
     */
    public function verify(Request $request, string $id): JsonResponse
    {
        $provider = Provider::query()->with('user')->findOrFail($id);

        $data = $request->validate([
            'is_verified' => ['required', 'boolean'],
            // Accepted for frontend compatibility; we don't store it in the current schema.
            'verification_notes' => ['nullable', 'string'],
        ]);

        $shouldVerify = (bool) $data['is_verified'];

        // Role assignment drives permissions (role has provider permissions via RolePermissionSeeder).
        $provider->user?->assignRole($shouldVerify ? 'provider' : 'customer');

        // Login requires email verification (AuthController checks hasVerifiedEmail()).
        // When the admin verifies the provider, we also mark their email as verified.
        if ($provider->user) {
            if ($shouldVerify) {
                // Use forceFill so it works even if `email_verified_at` is not mass-assignable.
                $provider->user->forceFill(['email_verified_at' => now()])->save();
            } else {
                $provider->user->forceFill(['email_verified_at' => null])->save();
            }
        }

        $updates = [];
        if (Schema::hasColumn('providers', 'is_verified')) {
            $updates['is_verified'] = $shouldVerify;
        }
        if (Schema::hasColumn('providers', 'verified_at')) {
            $updates['verified_at'] = $shouldVerify ? now() : null;
        }
        if (Schema::hasColumn('providers', 'status')) {
            $updates['status'] = $shouldVerify ? 'active' : 'pending';
        }

        if ($updates !== []) {
            $provider->update($updates);
        }

        $provider = $provider->fresh(['user', 'services.category'])->loadCount('reviews');

        if ($shouldVerify && $provider->user) {
            SendEmailJob::dispatchSync(
                $provider->user->email,
                new ProviderVerification($provider->user->name)
            );
        }

        return response()->json([
            'success' => true,
            'message' => $shouldVerify ? 'Provider verified successfully' : 'Provider unverified',
            'data' => new ProviderResource($provider),
        ]);
    }

    /**
     * Approve a provider (verified + provider role).
     *
     * POST /api/v1/admin/providers/{id}/approve
     */
    public function approve(string $id): JsonResponse
    {
        $provider = Provider::query()->with('user')->findOrFail($id);

        // Role assignment + permissions
        $provider->user?->assignRole('provider');

        // Login requires email verification (AuthController checks hasVerifiedEmail()).
        if ($provider->user) {
            // Use forceFill so it works even if `email_verified_at` is not mass-assignable.
            $provider->user->forceFill(['email_verified_at' => now()])->save();
        }

        $updates = [];
        if (Schema::hasColumn('providers', 'is_verified')) {
            $updates['is_verified'] = true;
        }
        if (Schema::hasColumn('providers', 'verified_at')) {
            $updates['verified_at'] = now();
        }
        if (Schema::hasColumn('providers', 'status')) {
            $updates['status'] = 'active';
        }

        if ($updates !== []) {
            $provider->update($updates);
        }

        $provider = $provider->fresh(['user', 'services.category'])->loadCount('reviews');

        if ($provider->user) {
            SendEmailJob::dispatchSync(
                $provider->user->email,
                new ProviderVerification($provider->user->name)
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Provider approved and verified successfully',
            'data' => new ProviderResource($provider),
        ]);
    }

    /**
     * List all services assigned to a provider.
     *
     * GET /api/v1/admin/providers/{id}/services
     */
    public function listServices(string $id): JsonResponse
    {
        $provider = Provider::query()->findOrFail($id);

        $services = ProviderService::query()
            ->where('provider_id', $provider->id)
            ->with('service.category')
            ->orderBy('created_at')
            ->get()
            ->map(fn ($ps) => [
                'id'         => $ps->id,
                'price'      => $ps->price,
                'service'    => [
                    'id'          => $ps->service->id,
                    'name'        => $ps->service->name,
                    'description' => $ps->service->description,
                    'category'    => [
                        'id'   => $ps->service->category?->id,
                        'name' => $ps->service->category?->name,
                    ],
                ],
                'created_at' => $ps->created_at,
            ]);

        return response()->json(['data' => $services]);
    }

    /**
     * Assign a service to a provider (or update price if already assigned).
     *
     * POST /api/v1/admin/providers/{id}/services
     * Body: { "service_id": "uuid", "price": 65.00 }
     */
    public function assignService(Request $request, string $id): JsonResponse
    {
        $provider = Provider::query()->findOrFail($id);

        $data = $request->validate([
            'service_id' => ['required', 'uuid', 'exists:services,id'],
            'price'      => ['required', 'numeric', 'min:0'],
        ]);

        $providerService = ProviderService::query()->updateOrCreate(
            [
                'provider_id' => $provider->id,
                'service_id'  => $data['service_id'],
            ],
            ['price' => $data['price']]
        );

        $providerService->load('service.category');

        return response()->json([
            'message' => $providerService->wasRecentlyCreated ? 'Service assigned to provider.' : 'Service price updated.',
            'data'    => [
                'id'      => $providerService->id,
                'price'   => $providerService->price,
                'service' => [
                    'id'       => $providerService->service->id,
                    'name'     => $providerService->service->name,
                    'category' => $providerService->service->category?->name,
                ],
            ],
        ], $providerService->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Remove a service from a provider.
     *
     * DELETE /api/v1/admin/providers/{id}/services/{serviceId}
     */
    public function removeService(string $id, string $serviceId): JsonResponse
    {
        $provider = Provider::query()->findOrFail($id);

        ProviderService::query()
            ->where('provider_id', $provider->id)
            ->where('service_id', $serviceId)
            ->firstOrFail()
            ->delete();

        return response()->json(['message' => 'Service removed from provider.']);
    }

    /**
     * View / download a provider's document (admin).
     *
     * GET /api/v1/admin/providers/{id}/documents/{docId}/view
     */
    public function viewDocument(string $id, string $docId): Response
    {
        $provider = Provider::query()->findOrFail($id);

        $doc = ProviderDocument::query()
            ->where('provider_id', $provider->id)
            ->findOrFail($docId);

        abort_if(empty($doc->file_url), 404, 'No file attached to this document.');

        if (preg_match('/^data:([^;]+);base64,(.+)$/s', $doc->file_url, $matches)) {
            $mime     = $matches[1];
            $binary   = base64_decode($matches[2]);
            $ext      = match (true) {
                str_contains($mime, 'pdf')  => 'pdf',
                str_contains($mime, 'png')  => 'png',
                str_contains($mime, 'jpeg') => 'jpg',
                str_contains($mime, 'jpg')  => 'jpg',
                default                     => 'bin',
            };
            $filename = str($doc->title)->slug()->append('.'.$ext)->toString();

            $disposition = in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'])
                ? "inline; filename=\"{$filename}\""
                : "attachment; filename=\"{$filename}\"";

            return response($binary, 200, [
                'Content-Type'        => $mime,
                'Content-Disposition' => $disposition,
                'Content-Length'      => strlen($binary),
            ]);
        }

        abort(422, 'File data is corrupted.');
    }

    /**
     * View / download a provider's certification (admin).
     *
     * GET /api/v1/admin/providers/{id}/certifications/{certId}/view
     */
    public function viewCertification(string $id, string $certId): Response
    {
        $provider = Provider::query()->findOrFail($id);

        $cert = ProviderCertification::query()
            ->where('provider_id', $provider->id)
            ->findOrFail($certId);

        abort_if(empty($cert->file_url), 404, 'No file attached to this certification.');

        if (preg_match('/^data:([^;]+);base64,(.+)$/s', $cert->file_url, $matches)) {
            $mime     = $matches[1];
            $binary   = base64_decode($matches[2]);
            $ext      = match (true) {
                str_contains($mime, 'pdf')  => 'pdf',
                str_contains($mime, 'png')  => 'png',
                str_contains($mime, 'jpeg') => 'jpg',
                str_contains($mime, 'jpg')  => 'jpg',
                default                     => 'bin',
            };
            $filename = str($cert->title)->slug()->append('.'.$ext)->toString();

            $disposition = in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'])
                ? "inline; filename=\"{$filename}\""
                : "attachment; filename=\"{$filename}\"";

            return response($binary, 200, [
                'Content-Type'        => $mime,
                'Content-Disposition' => $disposition,
                'Content-Length'      => strlen($binary),
            ]);
        }

        abort(422, 'File data is corrupted.');
    }
}
