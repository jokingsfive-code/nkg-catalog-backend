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
            'images.*' => 'required|file|mimes:png,jpg,jpeg,webp|max:102400',
        ]);

        $uploadedDesigns = [];

        foreach ($request->file('images') as $index => $file) {
            $originalName = $file->getClientOriginalName();
            $nameOnly = pathinfo($originalName, PATHINFO_FILENAME);
            $extension = $file->getClientOriginalExtension();

            $safeName = Str::slug($nameOnly) ?: 'design';
            $filename = $safeName . '-' . time() . '-' . $index . '.' . $extension;

            $path = $file->storeAs('designs', $filename, 'public');

            $design = Design::create([
                'category_id' => $validated['category_id'],
                'name' => $nameOnly,
                'image_url' => asset('storage/' . $path),
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
            'image' => 'nullable|file|mimes:png,jpg,jpeg,webp|max:102400',
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
            $originalName = $file->getClientOriginalName();
            $nameOnly = pathinfo($originalName, PATHINFO_FILENAME);
            $extension = $file->getClientOriginalExtension();

            $safeName = Str::slug($nameOnly) ?: 'design';
            $filename = $safeName . '-' . time() . '.' . $extension;

            $path = $file->storeAs('designs', $filename, 'public');

            $imageUrl = asset('storage/' . $path);
            $imagePublicId = $path;
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