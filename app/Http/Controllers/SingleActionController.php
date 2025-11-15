<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Single Action Controller Example
 * 
 * This controller has only one method: __invoke()
 * Used when a controller handles only one action.
 * Can be referenced directly without specifying a method name.
 */
class SingleActionController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        return response()->json([
            'message' => 'This is a single action controller',
            'route' => $request->path(),
            'method' => $request->method()
        ]);
    }
}

