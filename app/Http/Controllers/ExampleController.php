<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Example Controller - Demonstrates various controller methods
 * 
 * This controller shows different ways to handle routes:
 * - Simple methods returning views
 * - Methods with parameters
 * - Methods with route model binding
 * - Methods returning JSON/data
 */
class ExampleController extends Controller
{
    /**
     * Simple method - returns a view
     */
    public function index()
    {
        return view('example.index', ['message' => 'Welcome to the example index']);
    }

    /**
     * Method with required parameter
     */
    public function show($id)
    {
        return response()->json([
            'id' => $id,
            'message' => 'This is a show method with ID: ' . $id
        ]);
    }

    /**
     * Method with optional parameter
     */
    public function optional($id = null)
    {
        return response()->json([
            'id' => $id ?? 'not provided',
            'message' => 'Optional parameter example'
        ]);
    }

    /**
     * Method with multiple parameters
     */
    public function multiple($category, $id)
    {
        return response()->json([
            'category' => $category,
            'id' => $id,
            'message' => 'Multiple parameters example'
        ]);
    }

    /**
     * Method with route model binding (User model)
     */
    public function user(\App\Models\User $user)
    {
        return response()->json([
            'user' => $user->name,
            'email' => $user->email,
            'message' => 'Route model binding example'
        ]);
    }

    /**
     * Method that accepts a request object
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
        ]);

        return response()->json([
            'message' => 'Data stored successfully',
            'data' => $validated
        ], 201);
    }

    /**
     * Method demonstrating different HTTP methods
     */
    public function update(Request $request, $id)
    {
        return response()->json([
            'message' => 'Update method called',
            'id' => $id,
            'data' => $request->all()
        ]);
    }

    /**
     * Method for DELETE requests
     */
    public function destroy($id)
    {
        return response()->json([
            'message' => 'Delete method called',
            'id' => $id
        ]);
    }
}

