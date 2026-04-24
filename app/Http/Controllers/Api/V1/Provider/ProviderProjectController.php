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
            'title'       => $data['title'],
            'description' => $data['description'] ?? null,
        ]);

        if ($request->hasFile('image')) {
            ProjectImage::query()->create([
                'project_id' => $project->id,
                'image_url'  => $this->fileToDataUri($request->file('image')),
            ]);
        }

        return (new ProjectResource($project->load('images')))->response()->setStatusCode(201);
    }

    public function update(ProjectRequest $request, string $id): ProjectResource
    {
        $provider = $this->provider($request);
        $project  = Project::query()->where('provider_id', $provider->id)->findOrFail($id);
        $data     = $request->validated();

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

        if ($request->hasFile('image')) {
            ProjectImage::query()->create([
                'project_id' => $project->id,
                'image_url'  => $this->fileToDataUri($request->file('image')),
            ]);
        }

        return new ProjectResource($project->fresh()->load('images'));
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $provider = $this->provider($request);
        Project::query()->where('provider_id', $provider->id)->where('id', $id)->delete();

        return response()->json(['message' => 'Project deleted.']);
    }

    public function replaceImage(Request $request, string $id, string $imageId): JsonResponse
    {
        $provider = $this->provider($request);
        $project  = Project::query()->where('provider_id', $provider->id)->findOrFail($id);

        $image = ProjectImage::query()->where('project_id', $project->id)->findOrFail($imageId);

        // Accept the file under either 'image' or 'file' field name
        $fileKey = $request->hasFile('image') ? 'image' : 'file';

        $request->validate([
            $fileKey => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
        ]);

        $image->update([
            'image_url' => $this->fileToDataUri($request->file($fileKey)),
        ]);

        return response()->json([
            'message' => 'Image replaced.',
            'project' => new ProjectResource($project->fresh()->load('images')),
        ]);
    }

    public function destroyImage(Request $request, string $id, string $imageId): JsonResponse
    {
        $provider = $this->provider($request);
        $project  = Project::query()->where('provider_id', $provider->id)->findOrFail($id);

        ProjectImage::query()->where('project_id', $project->id)->findOrFail($imageId)->delete();

        return response()->json([
            'message' => 'Image deleted.',
            'project' => new ProjectResource($project->fresh()->load('images')),
        ]);
    }

    public function uploadImages(Request $request, string $id): JsonResponse
    {
        $provider = $this->provider($request);
        $project  = Project::query()->where('provider_id', $provider->id)->findOrFail($id);

        $validated = $request->validate([
            'images'   => ['required', 'array', 'min:1'],
            'images.*' => ['required', 'image', 'max:5120'],
        ]);

        foreach ($validated['images'] as $file) {
            ProjectImage::query()->create([
                'project_id' => $project->id,
                'image_url'  => $this->fileToDataUri($file),
            ]);
        }

        return response()->json([
            'message' => 'Images uploaded successfully.',
            'project' => new ProjectResource($project->fresh()->load('images')),
        ]);
    }
}
