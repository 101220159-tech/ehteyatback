<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Models\ProjectImage;
use App\Models\Provider;
use App\Traits\HandlesImageUpload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminProviderProjectController extends Controller
{
    use HandlesImageUpload;

    protected function provider(int $providerId): Provider
    {
        return Provider::query()->findOrFail($providerId);
    }

    public function index(Request $request, int $providerId): JsonResponse
    {
        $provider = $this->provider($providerId);

        $items = Project::query()
            ->where('provider_id', $provider->id)
            ->with('images')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20));

        return ProjectResource::collection($items)->response();
    }

    public function store(ProjectRequest $request, int $providerId): JsonResponse
    {
        $provider = $this->provider($providerId);
        $data = $request->validated();

        $project = Project::query()->create([
            'provider_id' => $provider->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
        ]);

        $imageUrl = $data['image_url'] ?? null;
        if ($request->hasFile('image')) {
            $path = $this->uploadImage($request->file('image'), 'projects', 1200, 800);
            $imageUrl = $this->publicStorageUrl($path);
        }
        if ($imageUrl) {
            ProjectImage::query()->create([
                'project_id' => $project->id,
                'image_url' => $imageUrl,
            ]);
        }

        return (new ProjectResource($project->load('images')))->response()->setStatusCode(201);
    }

    public function update(ProjectRequest $request, int $providerId, int $projectId): ProjectResource
    {
        $provider = $this->provider($providerId);
        $project = Project::query()
            ->where('provider_id', $provider->id)
            ->findOrFail($projectId);

        $data = $request->validated();

        $updates = [];
        if (array_key_exists('title', $data)) {
            $updates['title'] = $data['title'];
        }
        if (array_key_exists('description', $data)) {
            $updates['description'] = $data['description'];
        }
        if ($updates !== []) {
            $project->update($updates);
        }

        $imageUrl = $data['image_url'] ?? null;
        if ($request->hasFile('image')) {
            $path = $this->uploadImage($request->file('image'), 'projects', 1200, 800);
            $imageUrl = $this->publicStorageUrl($path);
        }
        if ($imageUrl) {
            ProjectImage::query()->create([
                'project_id' => $project->id,
                'image_url' => $imageUrl,
            ]);
        }

        return new ProjectResource($project->fresh()->load('images'));
    }

    public function destroy(int $providerId, int $projectId): JsonResponse
    {
        $provider = $this->provider($providerId);
        Project::query()
            ->where('provider_id', $provider->id)
            ->where('id', $projectId)
            ->delete();

        return response()->json(['message' => 'Project deleted.']);
    }

    public function uploadImages(Request $request, int $providerId, int $projectId): JsonResponse
    {
        $provider = $this->provider($providerId);
        $project = Project::query()
            ->where('provider_id', $provider->id)
            ->findOrFail($projectId);

        $validated = $request->validate([
            'images' => ['required', 'array', 'min:1'],
            'images.*' => ['required', 'image', 'max:5120', 'dimensions:max_width=8000,max_height=8000'],
        ]);

        foreach ($validated['images'] as $file) {
            $path = $this->uploadImage($file, 'projects', 1200, 800);
            ProjectImage::query()->create([
                'project_id' => $project->id,
                'image_url' => $this->publicStorageUrl($path),
            ]);
        }

        return response()->json([
            'message' => 'Images uploaded successfully.',
            'project' => new ProjectResource($project->fresh()->load('images')),
        ]);
    }
}

