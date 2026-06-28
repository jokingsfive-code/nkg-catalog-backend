<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Design;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class DesignController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->query('search');

        $designs = Design::with('category')
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search . '%')
                    ->orWhereHas('category', function ($categoryQuery) use ($search) {
                        $categoryQuery->where('name', 'like', '%' . $search . '%');
                    });
            })
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->get();

        return response()->json($designs);
    }

    private function uploadToSupabase($file, string $filename): array
    {
        $supabaseUrl = rtrim(env('SUPABASE_URL'), '/');
        $serviceKey = env('SUPABASE_SERVICE_ROLE_KEY');
        $bucket = env('SUPABASE_STORAGE_BUCKET', 'designs');

        if (!$supabaseUrl || !$serviceKey) {
            abort(500, 'Supabase storage is not configured.');
        }

        $path = 'designs/' . $filename;
        $mimeType = $file->getMimeType() ?: 'application/octet-stream';

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $serviceKey,
            'apikey' => $serviceKey,
            'x-upsert' => 'true',
        ])
            ->withBody(file_get_contents($file->getRealPath()), $mimeType)
            ->put($supabaseUrl . '/storage/v1/object/' . $bucket . '/' . $path);

        if (!$response->successful()) {
            return response()->json([
                'message' => 'Failed to upload image to Supabase Storage.',
                'status' => $response->status(),
                'supabase_error' => $response->json() ?: $response->body(),
            ], 500)->throwResponse();
        }

        return [
            'path' => $path,
            'url' => $supabaseUrl . '/storage/v1/object/public/' . $bucket . '/' . $path,
        ];
    }

    private function deleteFromSupabase(?string $path): void
    {
        if (!$path) {
            return;
        }

        $supabaseUrl = rtrim(env('SUPABASE_URL'), '/');
        $serviceKey = env('SUPABASE_SERVICE_ROLE_KEY');
        $bucket = env('SUPABASE_STORAGE_BUCKET', 'designs');

        if (!$supabaseUrl || !$serviceKey) {
            return;
        }

        Http::withHeaders([
            'Authorization' => 'Bearer ' . $serviceKey,
            'apikey' => $serviceKey,
            'Content-Type' => 'application/json',
        ])->delete($supabaseUrl . '/storage/v1/object/' . $bucket, [
            'prefixes' => [$path],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'images' => 'required|array|min:1',
            'images.*' => 'required|mimes:png,jpg,jpeg,webp|max:512000',
        ]);

        $uploadedDesigns = [];

        foreach ($request->file('images') as $index => $file) {
            if (!$file || !$file->isValid()) {
                return response()->json([
                    'message' => 'Upload file failed',
                    'error' => $file ? $file->getErrorMessage() : 'File not found',
                ], 422);
            }

            $originalName = $file->getClientOriginalName();
            $nameOnly = pathinfo($originalName, PATHINFO_FILENAME);
            $extension = strtolower($file->getClientOriginalExtension());

            $safeName = Str::slug($nameOnly) ?: 'design';
            $filename = $safeName . '-' . time() . '-' . $index . '.' . $extension;

            $uploaded = $this->uploadToSupabase($file, $filename);

            $design = Design::create([
    'category_id' => $validated['category_id'],
    'name' => $nameOnly,
    'image_url' => $uploaded['url'],
    'image_public_id' => $uploaded['path'],
    'sort_order' => 0,
    'is_featured' => false,
]);

$design->update([
    'name' => 'NKG-' . str_pad($design->id, 4, '0', STR_PAD_LEFT),
]);

            $uploadedDesigns[] = $design->load('category');
        }

        return response()->json([
            'message' => count($uploadedDesigns) . ' design uploaded successfully',
            'designs' => $uploadedDesigns,
        ], 201);
    }

    public function show(Design $design)
    {
        return response()->json($design->load('category'));
    }

    public function update(Request $request, Design $design)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'nullable|string|max:255',
            'image' => 'nullable|mimes:png,jpg,jpeg,webp|max:512000',
            'sort_order' => 'nullable|integer',
            'is_featured' => 'nullable|boolean',
        ]);

        $imageUrl = $design->image_url;
        $imagePublicId = $design->image_public_id;

        if ($request->hasFile('image')) {
            $this->deleteFromSupabase($design->image_public_id);

            $file = $request->file('image');

            if (!$file || !$file->isValid()) {
                return response()->json([
                    'message' => 'Upload file failed',
                    'error' => $file ? $file->getErrorMessage() : 'File not found',
                ], 422);
            }

            $originalName = $file->getClientOriginalName();
            $nameOnly = pathinfo($originalName, PATHINFO_FILENAME);
            $extension = strtolower($file->getClientOriginalExtension());

            $safeName = Str::slug($nameOnly) ?: 'design';
            $filename = $safeName . '-' . time() . '.' . $extension;

            $uploaded = $this->uploadToSupabase($file, $filename);

            $imageUrl = $uploaded['url'];
            $imagePublicId = $uploaded['path'];
        }

        $design->update([
            'category_id' => $validated['category_id'],
            'name' => $validated['name'] ?? $design->name,
            'image_url' => $imageUrl,
            'image_public_id' => $imagePublicId,
            'sort_order' => $validated['sort_order'] ?? $design->sort_order,
            'is_featured' => $validated['is_featured'] ?? $design->is_featured,
        ]);

        return response()->json($design->load('category'));
    }

    public function destroy(Design $design)
    {
        $this->deleteFromSupabase($design->image_public_id);

        $design->delete();

        return response()->json([
            'message' => 'Design deleted successfully',
        ]);
    }

    public function toggleFeatured(Design $design)
    {
        $design->update([
            'is_featured' => !$design->is_featured,
        ]);

        return response()->json($design);
    }
}