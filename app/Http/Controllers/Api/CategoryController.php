<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = Category::where(function ($q) use ($request) {
            $q->whereNull('user_id')->orWhere('user_id', $request->user()->id);
        });

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        return response()->json($query->orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:income,expense',
            'name' => 'required|string|max:100',
            'icon' => 'nullable|string|max:100',
            'color' => 'nullable|string|max:9|regex:/^#?[0-9A-Fa-f]{6,8}$/',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $category = Category::create([
            'user_id' => $request->user()->id, // custom milik user ini
            'type' => $request->type,
            'name' => $request->name,
            'icon' => $request->icon ?? 'label',
            'color' => $request->color,
        ]);

        return response()->json($category, 201);
    }

    public function destroy(Request $request, Category $category)
    {
        // hanya boleh hapus kategori custom miliknya sendiri, bukan default (user_id null)
        if ($category->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Kategori ini tidak dapat dihapus'], 403);
        }

        $category->delete();

        return response()->json(['message' => 'Kategori dihapus']);
    }
}