<?php

use App\Http\Controllers\ExampleController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SingleActionController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

// ============================================================================
// COMPREHENSIVE ROUTE EXAMPLES - ALL TYPES OF ROUTES IN LARAVEL
// ============================================================================

/*
 * ========================================================================
 * 1. BASIC ROUTES (Closure-based routes)
 * ========================================================================
 * These are routes that use anonymous functions (closures) directly.
 * Good for simple, one-off routes.
 */

// GET route - Most common, retrieves data
Route::get('/examples/basic-get', function () {
    return response()->json(['message' => 'This is a GET route']);
});

// POST route - Used for creating/submitting data
Route::post('/examples/basic-post', function () {
    return response()->json(['message' => 'This is a POST route']);
});

// PUT route - Used for updating entire resources
Route::put('/examples/basic-put', function () {
    return response()->json(['message' => 'This is a PUT route']);
});

// PATCH route - Used for partial updates
Route::patch('/examples/basic-patch', function () {
    return response()->json(['message' => 'This is a PATCH route']);
});

// DELETE route - Used for deleting resources
Route::delete('/examples/basic-delete', function () {
    return response()->json(['message' => 'This is a DELETE route']);
});

// OPTIONS route - Used for CORS preflight requests
Route::options('/examples/basic-options', function () {
    return response()->json(['message' => 'This is an OPTIONS route']);
});

// Match multiple HTTP methods
Route::match(['get', 'post'], '/examples/match', function () {
    return response()->json(['message' => 'This route accepts both GET and POST']);
});

// Accept any HTTP method
Route::any('/examples/any', function () {
    return response()->json(['message' => 'This route accepts any HTTP method']);
});

/*
 * ========================================================================
 * 2. ROUTE PARAMETERS
 * ========================================================================
 * Routes can accept parameters from the URL.
 */

// Required parameter - {id} must be present
Route::get('/examples/required-param/{id}', function ($id) {
    return response()->json(['id' => $id, 'message' => 'Required parameter example']);
});

// Optional parameter - {id?} may be omitted
Route::get('/examples/optional-param/{id?}', function ($id = null) {
    return response()->json(['id' => $id ?? 'not provided', 'message' => 'Optional parameter example']);
});

// Multiple parameters
Route::get('/examples/multiple-params/{category}/{id}', function ($category, $id) {
    return response()->json([
        'category' => $category,
        'id' => $id,
        'message' => 'Multiple parameters example'
    ]);
});

// Parameter with constraint (regex validation)
Route::get('/examples/constrained/{id}', function ($id) {
    return response()->json(['id' => $id, 'message' => 'ID must be numeric']);
})->where('id', '[0-9]+'); // Only accepts numeric IDs

// Multiple constraints
Route::get('/examples/user/{name}/{id}', function ($name, $id) {
    return response()->json(['name' => $name, 'id' => $id]);
})->where(['name' => '[a-zA-Z]+', 'id' => '[0-9]+']);

/*
 * ========================================================================
 * 3. CONTROLLER ROUTES
 * ========================================================================
 * Routes that point to controller methods instead of closures.
 * Better for organization and reusability.
 */

// Single controller method
Route::get('/examples/controller', [ExampleController::class, 'index']);

// Controller method with parameter
Route::get('/examples/controller/{id}', [ExampleController::class, 'show']);

// Controller method with optional parameter
Route::get('/examples/controller-optional/{id?}', [ExampleController::class, 'optional']);

// Controller method with multiple parameters
Route::get('/examples/controller/{category}/{id}', [ExampleController::class, 'multiple']);

// Using string syntax (alternative to array syntax)
Route::get('/examples/controller-string', 'App\Http\Controllers\ExampleController@index');

/*
 * ========================================================================
 * 4. SINGLE ACTION CONTROLLERS
 * ========================================================================
 * Controllers with only __invoke() method can be referenced directly.
 */

Route::get('/examples/single-action', SingleActionController::class);
// Equivalent to: Route::get('/examples/single-action', [SingleActionController::class, '__invoke']);

/*
 * ========================================================================
 * 5. RESOURCE ROUTES
 * ========================================================================
 * Automatically creates multiple routes for CRUD operations.
 * Follows RESTful conventions.
 */

// Full resource controller - creates all 7 routes:
// GET    /products              -> index()    (list all)
// GET    /products/create        -> create()   (show create form)
// POST   /products              -> store()    (save new)
// GET    /products/{id}         -> show()     (show one)
// GET    /products/{id}/edit    -> edit()     (show edit form)
// PUT    /products/{id}         -> update()   (update)
// DELETE /products/{id}         -> destroy()  (delete)
Route::resource('products', ProductController::class);

// Resource with only specific actions
Route::resource('products-limited', ProductController::class)->only(['index', 'show']);

// Resource excluding specific actions
Route::resource('products-except', ProductController::class)->except(['create', 'edit']);

// API Resource - excludes 'create' and 'edit' routes (no forms needed in APIs)
Route::apiResource('api-products', ProductController::class);

// Multiple resources at once
Route::resources([
    'products' => ProductController::class,
    'orders' => ProductController::class, // Example - would use OrderController in real app
]);

/*
 * ========================================================================
 * 6. ROUTE MODEL BINDING
 * ========================================================================
 * Automatically injects model instances based on route parameters.
 */

// Implicit binding - Laravel automatically resolves User model
Route::get('/examples/user/{user}', function (User $user) {
    return response()->json([
        'user' => $user->name,
        'email' => $user->email,
        'message' => 'Route model binding automatically fetched the user'
    ]);
});

// Custom key binding (if you want to use a different column)
Route::get('/examples/user-email/{user:email}', function (User $user) {
    return response()->json([
        'user' => $user->name,
        'email' => $user->email,
        'message' => 'Bound by email instead of ID'
    ]);
});

// With controller
Route::get('/examples/controller-user/{user}', [ExampleController::class, 'user']);

/*
 * ========================================================================
 * 7. NAMED ROUTES
 * ========================================================================
 * Give routes names for easy reference in code.
 */

Route::get('/examples/named-route', function () {
    return response()->json(['message' => 'This route has a name']);
})->name('examples.named');

// Usage: route('examples.named') or url()->route('examples.named')

/*
 * ========================================================================
 * 8. ROUTE GROUPS
 * ========================================================================
 * Group routes to apply common attributes (middleware, prefix, etc.)
 */

// Group with middleware
Route::middleware(['auth'])->group(function () {
    Route::get('/examples/protected-1', function () {
        return response()->json(['message' => 'Protected route 1']);
    });
    Route::get('/examples/protected-2', function () {
        return response()->json(['message' => 'Protected route 2']);
    });
});

// Group with prefix
Route::prefix('admin')->group(function () {
    Route::get('/users', function () {
        return response()->json(['message' => 'Admin users']);
    });
    Route::get('/settings', function () {
        return response()->json(['message' => 'Admin settings']);
    });
    // These become: /admin/users and /admin/settings
});

// Group with name prefix
Route::name('admin.')->group(function () {
    Route::get('/examples/admin-route', function () {
        return response()->json(['message' => 'Admin route']);
    })->name('dashboard'); // Full name: admin.dashboard
});

// Group with domain (useful for subdomains)
Route::domain('api.example.com')->group(function () {
    Route::get('/examples', function () {
        return response()->json(['message' => 'API subdomain route']);
    });
});

// Multiple group attributes combined
Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/examples/combined', function () {
        return response()->json(['message' => 'Combined group attributes']);
    })->name('example'); // Full: admin.example, URL: /admin/examples/combined
});

/*
 * ========================================================================
 * 9. ROUTE PREFIXES
 * ========================================================================
 * Add a prefix to all routes in a group.
 */

Route::prefix('api/v1')->group(function () {
    Route::get('/examples', function () {
        return response()->json(['message' => 'API v1 route']);
    });
    // This becomes: /api/v1/examples
});

/*
 * ========================================================================
 * 10. ROUTE MIDDLEWARE
 * ========================================================================
 * Apply middleware to routes for authentication, authorization, etc.
 */

// Single middleware
Route::get('/examples/middleware-single', function () {
    return response()->json(['message' => 'Single middleware']);
})->middleware('auth');

// Multiple middleware
Route::get('/examples/middleware-multiple', function () {
    return response()->json(['message' => 'Multiple middleware']);
})->middleware(['auth', 'verified']);

// Middleware alias
Route::get('/examples/middleware-alias', function () {
    return response()->json(['message' => 'Middleware alias']);
})->middleware('throttle:60,1'); // Rate limiting: 60 requests per minute

/*
 * ========================================================================
 * 11. ROUTE REDIRECTS
 * ========================================================================
 * Redirect one route to another.
 */

// Simple redirect
Route::redirect('/examples/old-url', '/examples/new-url', 301);

// Redirect with default status (302)
Route::permanentRedirect('/examples/temp-old', '/examples/temp-new');

/*
 * ========================================================================
 * 12. VIEW ROUTES
 * ========================================================================
 * Return a view directly without a controller.
 */

Route::view('/examples/view-route', 'home', ['greeting' => 'Hello from view route']);

// View route with middleware
Route::view('/examples/view-protected', 'dashboard')
    ->middleware(['auth'])
    ->name('examples.view');

/*
 * ========================================================================
 * 13. FALLBACK ROUTES
 * ========================================================================
 * Catch-all route for undefined routes (404 handling).
 */

Route::fallback(function () {
    return response()->json([
        'message' => 'Route not found',
        'status' => 404
    ], 404);
});

/*
 * ========================================================================
 * 14. ROUTE CACHING (Performance)
 * ========================================================================
 * 
 * To cache routes for better performance:
 * php artisan route:cache
 * 
 * To clear route cache:
 * php artisan route:clear
 * 
 * Note: Don't use route caching in development!
 */

/*
 * ========================================================================
 * 15. ROUTE LISTING
 * ========================================================================
 * 
 * View all routes:
 * php artisan route:list
 * 
 * Filter routes:
 * php artisan route:list --name=examples
 * php artisan route:list --path=admin
 */

/*
 * ========================================================================
 * 16. ADVANCED: ROUTE BINDING WITH CUSTOM LOGIC
 * ========================================================================
 */

// Custom resolution logic (in RouteServiceProvider or here)
Route::bind('customUser', function ($value) {
    return User::where('email', $value)->firstOrFail();
});

Route::get('/examples/custom-binding/{customUser}', function (User $customUser) {
    return response()->json([
        'user' => $customUser->name,
        'message' => 'Custom binding example'
    ]);
});

/*
 * ========================================================================
 * 17. ADVANCED: ROUTE MODEL BINDING WITH SCOPES
 * ========================================================================
 */

// In your model, you can add:
// public function getRouteKeyName() { return 'slug'; } // Use slug instead of ID
// Or in the route:
Route::get('/examples/user-slug/{user:slug}', function (User $user) {
    return response()->json(['user' => $user]);
});

/*
 * ========================================================================
 * 18. ADVANCED: CONDITIONAL ROUTES
 * ========================================================================
 */

// Using when() helper for conditional middleware
Route::get('/examples/conditional', function () {
    return response()->json(['message' => 'Conditional route']);
})->middleware(
    when(app()->environment('production'), ['throttle:60,1'], [])
);

/*
 * ========================================================================
 * SUMMARY OF ROUTE TYPES:
 * ========================================================================
 * 
 * 1. Basic Routes: get(), post(), put(), patch(), delete(), options()
 * 2. Match Routes: match(['get', 'post'], ...)
 * 3. Any Routes: any()
 * 4. Controller Routes: Route::get(..., [Controller::class, 'method'])
 * 5. Single Action Controllers: Route::get(..., Controller::class)
 * 6. Resource Routes: Route::resource()
 * 7. API Resource Routes: Route::apiResource()
 * 8. Named Routes: ->name('route.name')
 * 9. Route Groups: ->group(function() { ... })
 * 10. Route Prefixes: ->prefix('admin')
 * 11. Route Middleware: ->middleware(['auth'])
 * 12. Route Redirects: Route::redirect()
 * 13. View Routes: Route::view()
 * 14. Fallback Routes: Route::fallback()
 * 15. Route Model Binding: {model} parameter
 * 16. Route Parameters: {id}, {id?}
 * 17. Route Constraints: ->where('id', '[0-9]+')
 */
