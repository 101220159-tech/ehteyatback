<?php

namespace App\Http\Controllers\Api\V1\Provider;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Models\ProjectImage;
use App\Traits\HandlesImageUpload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderProjectController extends Controller
{
    use HandlesImageUpload;

    protected function provider(Request $request)
    {
        $p = $request->user()->provider;
        abort_if(! $p, 404, 'Provider profile not found.');

        return $p;
    }

    public function index(Request $request): JsonResponse
    {
        $provider = $this->provider($request);
        $items = Project::query()
            ->where('provider_id', $provider->id)
            ->with('images')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20));

        return ProjectResource::collection($items)->response();
    }

    public function store(ProjectRequest $request): JsonResponse
    {
        $provider = $this->provider($request);
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

    public function update(ProjectRequest $request, int $id): ProjectResource
    {
        $provider = $this->provider($request);
        $project = Project::query()->where('provider_id', $provider->id)->findOrFail($id);
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

    public function destroy(Request $request, int $id): JsonResponse
    {
        $provider = $this->provider($request);
        Project::query()->where('provider_id', $provider->id)->where('id', $id)->delete();

        return response()->json(['message' => 'Project deleted.']);
    }
}
