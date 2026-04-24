<?php

namespace App\Http\Controllers\Api\V1\Provider;

use App\Http\Controllers\Controller;
use App\Traits\HandlesImageUpload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProviderCertificationController extends Controller
{
    use HandlesImageUpload;

    public function index(Request $request): JsonResponse
    {
        $certs = $request->user()->provider->certifications()->orderBy('issued_at', 'desc')->get();

        return response()->json(['data' => $certs]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'      => 'required|string|max:255',
            'issuer'     => 'nullable|string|max:255',
            'issued_at'  => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:issued_at',
            'file'       => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        if ($request->hasFile('file')) {
            $data['file_url'] = $this->fileToDataUri($request->file('file'));
        }

        unset($data['file']);

        $cert = $request->user()->provider->certifications()->create($data);

        return response()->json(['message' => 'Certification added.', 'data' => $cert], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $cert = $request->user()->provider->certifications()->findOrFail($id);

        return response()->json(['data' => $cert]);
    }

    /**
     * POST /provider/certifications/{id}
     * Accepts multipart/form-data so file + text fields all work together.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $cert = $request->user()->provider->certifications()->findOrFail($id);

        $data = $request->validate([
            'title'      => 'sometimes|string|max:255',
            'issuer'     => 'sometimes|nullable|string|max:255',
            'issued_at'  => 'sometimes|nullable|date',
            'expires_at' => 'sometimes|nullable|date',
            'file'       => 'sometimes|nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        if ($request->hasFile('file')) {
            $data['file_url'] = $this->fileToDataUri($request->file('file'));
        }

        unset($data['file']);

        $cert->update($data);

        return response()->json(['message' => 'Certification updated.', 'data' => $cert->fresh()]);
    }

    /**
     * GET /provider/certifications/{id}/download
     * Streams the stored file back to the browser as a download.
     */
    public function download(Request $request, string $id): Response
    {
        $cert = $request->user()->provider->certifications()->findOrFail($id);

        abort_if(empty($cert->file_url), 404, 'No file attached to this certification.');

        // Parse the data URI: data:{mime};base64,{data}
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

            return response($binary, 200, [
                'Content-Type'        => $mime,
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                'Content-Length'      => strlen($binary),
            ]);
        }

        abort(422, 'File data is corrupted.');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $request->user()->provider->certifications()->findOrFail($id)->delete();

        return response()->json(['message' => 'Certification deleted.']);
    }
}
