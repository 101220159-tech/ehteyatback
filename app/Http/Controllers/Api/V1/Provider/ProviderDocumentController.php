<?php

namespace App\Http\Controllers\Api\V1\Provider;

use App\Http\Controllers\Controller;
use App\Traits\HandlesImageUpload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProviderDocumentController extends Controller
{
    use HandlesImageUpload;

    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()->provider->documents()->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type'  => 'required|in:id_card,trade_license,insurance,certificate,other',
            'title' => 'required|string|max:255',
            'file'  => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $data['file_url'] = $this->fileToDataUri($request->file('file'));
        unset($data['file']);

        $doc = $request->user()->provider->documents()->create($data);

        return response()->json(['message' => 'Document uploaded.', 'data' => $doc], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        return response()->json(['data' => $request->user()->provider->documents()->findOrFail($id)]);
    }

    /**
     * GET /provider/documents/{id}/download
     * Streams the stored file back to the browser as a download/inline view.
     */
    public function download(Request $request, string $id): Response
    {
        $doc = $request->user()->provider->documents()->findOrFail($id);

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

            // PDFs and images use inline so the browser can render them directly;
            // everything else forces a download.
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

    public function destroy(Request $request, string $id): JsonResponse
    {
        $request->user()->provider->documents()->findOrFail($id)->delete();

        return response()->json(['message' => 'Document deleted.']);
    }
}
