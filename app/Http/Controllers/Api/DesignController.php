<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Design;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'images' => 'required|array|min:1',
            'images.*' => 'required|mimes:png,jpg,jpeg,webp|max:102400',
        ]);

        $uploadedDesigns = [];

        foreach ($request->file('images') as $index => $file) {
            if (!$file || !$file->isValid()) {
                return response()->json([
                    'message' => 'Upload file failed',
                    'errors' => [
                        'images.' . $index => [
                            $file ? $file->getErrorMessage() : 'File not found',
                        ],
                    ],
                ], 422);
            }

            $originalName = $file->getClientOriginalName();
            $nameOnly = pathinfo($originalName, PATHINFO_FILENAME);
            $extension = strtolower($file->getClientOriginalExtension());

            $safeName = Str::slug($nameOnly) ?: 'design';
            $filename = $safeName . '-' . time() . '-' . $index . '.' . $extension;

            $directory = storage_path('app/public/designs');

            if (!is_dir($directory)) {
                mkdir($directory, 0775, true);
            }

            $file->move($directory, $filename);

            $path = 'designs/' . $filename;

            $design = Design::create([
                'category_id' => $validated['category_id'],
                'name' => $nameOnly,
                'image_url' => url('storage/' . $path),
                'image_public_id' => $path,
                'sort_order' => 0,
                'is_featured' => false,
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
            if (
                $design->image_public_id &&
                Storage::disk('public')->exists($design->image_public_id)
            ) {
                Storage::disk('public')->delete($design->image_public_id);
            }

            $file = $request->file('image');

            if ($file && $file->isValid()) {
                $originalName = $file->getClientOriginalName();
                $nameOnly = pathinfo($originalName, PATHINFO_FILENAME);
                $extension = strtolower($file->getClientOriginalExtension());

                $safeName = Str::slug($nameOnly) ?: 'design';
                $filename = $safeName . '-' . time() . '.' . $extension;

                $directory = storage_path('app/public/designs');

                if (!is_dir($directory)) {
                    mkdir($directory, 0775, true);
                }

                $file->move($directory, $filename);

                $path = 'designs/' . $filename;

                $imageUrl = url('storage/' . $path);
                $imagePublicId = $path;
            }
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
        if (
            $design->image_public_id &&
            Storage::disk('public')->exists($design->image_public_id)
        ) {
            Storage::disk('public')->delete($design->image_public_id);
        }

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