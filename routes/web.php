<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\SwapController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication Routes (Guest Only)
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
    Route::get('/register', [RegisterController::class, 'index'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->name('register.store');
});

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', function () {
    $topstudents = User::where('id', '!=', auth()->id())->take(3)->get(); 
    $excludeIds = $topstudents->pluck('id');

    $students = User::where('id', '!=', auth()->id())->whereNotIn('id', $excludeIds)->take(6)->get(); 

    // Grabs an array of just the IDs you have already favorited (e.g., [2, 3])
    $favoritedIds = auth()->user()->favorites->pluck('id')->toArray();

    // Pass both variables to the view
    return view('dashboard', compact('topstudents','students', 'favoritedIds'));
    })->name('dashboard');

    Route::post('/favorite/toggle', [FavoriteController::class, 'toggleFavorite'])->name('favorite.toggle');
    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');  

    Route::post('/swap/add', [SwapController::class, 'add'])->name('swap.add');
    Route::get('/swap', [SwapController::class, 'index'])->name('swap');
    Route::delete('/swap/{swap}', [SwapController::class, 'destroy'])->name('swap.destroy');

    Route::view('/schedule', 'schedule')->name('schedule');
    Route::view('/messages', 'messages')->name('messages');
    Route::view('/history', 'history')->name('history');
    Route::view('/community', 'community')->name('community');

    Route::view('/profile', 'profile.edit')->name('profile.edit');
    Route::put('/profile', [UserController::class, 'update'])->name('profile.update');

    Route::get('/users', [UserController::class, 'index'])->name('users');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/data', [UserController::class, 'data'])->name('users.data');
    Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
});
/*
|--------------------------------------------------------------------------
| Root Redirect
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return auth()->check()
    ? redirect()->route('dashboard')
    : redirect()->route('login');
});
