<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Dashboard (only admin role can see this)
Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified', 'role:admin'])->name('dashboard');

// Routes for authenticated users
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Example: admin-only group
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/admin/users', function () {
        return 'Only admins can see this page';
    })->name('admin.users');
});


// routes/web.php
use Illuminate\Support\Facades\Auth;

Route::get('/_who', function () {
    return [
        'url'       => request()->fullUrl(),
        'guard'     => 'web',
        'check'     => Auth::guard('web')->check(),
        'user_id'   => optional(Auth::guard('web')->user())->id,
        'intended'  => session('url.intended'),
        'cookies'   => array_keys(request()->cookies->all()),
    ];
});

// FORCE login as first user (LOCAL ONLY)
Route::get('/_force-login', function () {
    $u = \App\Models\User::first();
    Auth::login($u);
    return ['logged_in_as' => $u?->id];
});

// Log out
Route::get('/_logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return 'logged out';
});


require __DIR__.'/auth.php';
