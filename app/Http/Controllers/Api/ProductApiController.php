<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Error;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Exception;


class ProductApiController extends Controller
{
    // public function index()
    // {
    //     $products = Product::orderBy('pid', 'asc')->get()->map(function ($product) {
    //         return $this->formatProductResponse($product);
    //     });

    //     return response()->json($products, 200);
    // }

    // public function show(string $pid)
    // {
    //     $product = Product::find($pid);

    //     if (!$product) {
    //         return response()->json(['message' => 'Product not found'], 404);
    //     }

    //     return response()->json($this->formatProductResponse($product), 200);
    // }

    // public function store(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'name' => 'required|string|max:255',
    //         'description' => 'nullable|string',
    //         'price' => 'required|numeric|min:0',
    //         'stock' => 'required|integer|min:0',
    //         'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json(['errors' => $validator->errors()], 422);
    //     }

    //     $data = $request->except('image');

    //     if ($request->hasFile('image')) {
    //         $image = $request->file('image');
    //         $imageName = time() . '.' . $image->getClientOriginalExtension();
    //         $path = $image->storeAs('products', $imageName, 'public');
    //         $data['image'] = Storage::url($path);
    //     }

    //     $product = Product::create($data);

    //     return response()->json([
    //         'message' => 'Product created successfully',
    //         'data' => $this->formatProductResponse($product)
    //     ], 201);
    // }

    // public function update(Request $request, string $pid)
    // {
    //     $product = Product::find($pid);
    //     if (!$product) return response()->json(['message' => 'Product not found'], 404);

    //     $validator = Validator::make($request->all(), [
    //         'name' => 'sometimes|string|max:255',
    //         'description' => 'sometimes|nullable|string',
    //         'price' => 'sometimes|numeric|min:0',
    //         'stock' => 'sometimes|integer|min:0',
    //         'image' => 'sometimes|nullable|image|mimes:jpeg,png,jpg|max:2048',
    //     ]);
    //     if ($validator->fails()) return response()->json(['errors' => $validator->errors()], 422);

    //     $product->fill($request->only(['name', 'description', 'price', 'stock']));
    //     if ($request->hasFile('image')) {
    //         if ($product->image) Storage::delete(str_replace('/storage/', 'public/', $product->image));
    //         $product->image = $this->getImage($request->file('image'));
    //     }
    //     $product->save();

    //     return response()->json([
    //         'message' => 'Product updated successfully',
    //         'data' => $this->formatProductResponse($product)
    //     ], 200);
    // }

    // public function destroy(string $pid)
    // {
    //     $product = Product::find($pid);

    //     if (!$product) {
    //         return response()->json(['message' => 'Product not found'], 404);
    //     }
    //     $product->delete();

    //     return response()->json(['message' => 'Product deleted successfully'], 200);
    // }

    // public function getImage($pid)
    // {
    //     $product = Product::find($pid);
    //     if (!$product || !$product->image) {
    //         return response()->json(['message' => 'Image not found'], 404);
    //     }

    //     $imagePath = str_replace('/storage/', '', parse_url($product->image, PHP_URL_PATH));
    //     $path = storage_path('app/public/' . $imagePath);

    //     if (!file_exists($path)) {
    //         return response()->json(['message' => 'Image file not found'], 404);
    //     }

    //     return response()->file($path);
    // }

    // protected function formatProductResponse($product)
    // {
    //     return [
    //         'id' => $product->pid,
    //         'name' => $product->name,
    //         'description' => $product->description,
    //         'price' => $product->price,
    //         'stock' => $product->stock,
    //         'image' => $product->image ? url($product->image) : null,
    //         'created_at' => $product->created_at,
    //         'updated_at' => $product->updated_at
    //     ];
    // }

     public function index()
    {
        $products = Product::orderBy('pid', 'asc')->get()->map(function ($product) {
            return $this->formatProductResponse($product);
        });

        return response()->json($products, 200);
    }

    public function show(string $pid)
    {
        $product = Product::find($pid);
        if (!$product) return response()->json(['message' => 'Product not found'], 404);
        return response()->json($this->formatProductResponse($product), 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);
        if ($validator->fails()) return response()->json(['errors' => $validator->errors()], 422);

        $product = new Product($request->only(['name', 'description', 'price', 'stock']));
        if ($request->hasFile('image')) {
            $product->image = $this->storeImage($request->file('image'));
        }
        $product->save();

        return response()->json([
            'message' => 'Product created successfully',
            'data' => $this->formatProductResponse($product)
        ], 201);
    }

    public function update(Request $request, string $pid)
    {
        $product = Product::find($pid);
        if (!$product) return response()->json(['message' => 'Product not found'], 404);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'stock' => 'sometimes|integer|min:0',
            'image' => 'sometimes|nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);
        if ($validator->fails()) return response()->json(['errors' => $validator->errors()], 422);

        $product->fill($request->only(['name', 'description', 'price', 'stock']));
        if ($request->hasFile('image')) {
            if ($product->image) Storage::delete(str_replace('/storage/', 'public/', $product->image));
            $product->image = $this->storeImage($request->file('image'));
        }
        $product->save();

        return response()->json([
            'message' => 'Product updated successfully',
            'data' => $this->formatProductResponse($product)
        ], 200);
    }

    public function destroy(string $pid)
    {
        $product = Product::find($pid);
        if (!$product) return response()->json(['message' => 'Product not found'], 404);
        if ($product->image) Storage::delete(str_replace('/storage/', 'public/', $product->image));
        $product->delete();
        return response()->json(['message' => 'Product deleted successfully'], 200);
    }

    public function getImage($pid)
    {
        $product = Product::find($pid);
        if (!$product || !$product->image) return response()->json(['message' => 'Image not found'], 404);

        $imagePath = str_replace('/storage/', '', parse_url($product->image, PHP_URL_PATH));
        $path = storage_path('app/public/' . $imagePath);
        if (!file_exists($path)) return response()->json(['message' => 'Image file not found'], 404);

        return response()->file($path);
    }

    protected function storeImage($image)
    {
        $imageName = time() . '.' . $image->getClientOriginalExtension();
        $path = $image->storeAs('products', $imageName, 'public');
        return Storage::url($path);
    }

    protected function formatProductResponse($product)
    {
        return [
            'id' => $product->pid,
            'name' => $product->name,
            'description' => $product->description,
            'price' => $product->price,
            'stock' => $product->stock,
            'image' => $product->image ? url($product->image) : null,
            'created_at' => $product->created_at,
            'updated_at' => $product->updated_at
        ];
    }
}
