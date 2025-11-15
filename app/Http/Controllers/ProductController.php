<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Resource Controller Example
 * 
 * This demonstrates a typical resource controller with CRUD operations.
 * When used with Route::resource(), Laravel automatically maps HTTP verbs
 * to these methods following RESTful conventions.
 */
class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     * GET /products
     */
    public function index()
    {
        return response()->json([
            'message' => 'Product index - List all products',
            'method' => 'GET',
            'route' => '/products'
        ]);
    }

    /**
     * Show the form for creating a new resource.
     * GET /products/create
     */
    public function create()
    {
        return response()->json([
            'message' => 'Product create form',
            'method' => 'GET',
            'route' => '/products/create'
        ]);
    }

    /**
     * Store a newly created resource in storage.
     * POST /products
     */
    public function store(Request $request)
    {
        return response()->json([
            'message' => 'Product stored',
            'method' => 'POST',
            'route' => '/products',
            'data' => $request->all()
        ], 201);
    }

    /**
     * Display the specified resource.
     * GET /products/{id}
     */
    public function show($id)
    {
        return response()->json([
            'message' => 'Show product',
            'method' => 'GET',
            'route' => '/products/' . $id,
            'id' => $id
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     * GET /products/{id}/edit
     */
    public function edit($id)
    {
        return response()->json([
            'message' => 'Edit product form',
            'method' => 'GET',
            'route' => '/products/' . $id . '/edit',
            'id' => $id
        ]);
    }

    /**
     * Update the specified resource in storage.
     * PUT/PATCH /products/{id}
     */
    public function update(Request $request, $id)
    {
        return response()->json([
            'message' => 'Product updated',
            'method' => 'PUT/PATCH',
            'route' => '/products/' . $id,
            'id' => $id,
            'data' => $request->all()
        ]);
    }

    /**
     * Remove the specified resource from storage.
     * DELETE /products/{id}
     */
    public function destroy($id)
    {
        return response()->json([
            'message' => 'Product deleted',
            'method' => 'DELETE',
            'route' => '/products/' . $id,
            'id' => $id
        ]);
    }
}

