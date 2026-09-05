<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\OtpController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\SwapController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\CommunityController;
use App\Http\Controllers\QualificationController;
use App\Http\Controllers\AdminQualificationController;
use App\Http\Controllers\CommunityInviteController;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MessageController;
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

    Route::get('/otp', [OtpController::class, 'show'])->name('otp.show');
    Route::post('/otp', [OtpController::class, 'verify'])->name('otp.verify');
    Route::post('/otp/resend', [OtpController::class, 'resend'])->name('otp.resend');
});

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/search', [DashboardController::class, 'search'])->name('search');

    Route::post('/apply-to-teach', [QualificationController::class, 'store'])->name('qualifications.store');

    Route::post('/favorite/toggle', [FavoriteController::class, 'toggleFavorite'])->name('favorite.toggle');
    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');

    Route::post('/likes/toggle', [LikeController::class, 'toggle'])->name('likes.toggle');

    Route::post('/swap/{swap}/respond', [SwapController::class, 'respond'])->name('swap.respond');
    Route::post('/swap/add', [SwapController::class, 'add'])->name('swap.add');
    Route::get('/swap', [SwapController::class, 'index'])->name('swap');
    Route::delete('/swap/{swap}', [SwapController::class, 'destroy'])->name('swap.destroy');

    Route::get('/schedule/data', [ScheduleController::class, 'getSchedule'])->name('schedule.data');
    Route::post('/schedule', [ScheduleController::class, 'store'])->name('schedule.store');
    Route::delete('/schedule', [ScheduleController::class, 'destroy'])->name('schedule.destroy');

    Route::get('/community', [CommunityController::class, 'index'])->name('community');
    Route::get('/community/{community}', [CommunityController::class, 'show'])->name('community.show');
    Route::post('/community', [CommunityController::class, 'store'])->name('community.store');
    Route::post('/community/{community}/join', [CommunityController::class, 'join'])->name('community.join');
    Route::delete('/community/{community}', [CommunityController::class, 'destroy'])->name('community.destroy');
    Route::put('/community/{community}', [CommunityController::class, 'update'])->name('community.update');
    Route::put('/community/{id}/tags', [CommunityController::class, 'updateTags'])->name('community.tags.update');
    Route::post('/community/{id}/posts', [CommunityController::class, 'storePost'])->name('community.posts.store');
    Route::post('/communities/{community}/invite', [CommunityInviteController::class, 'store'])->name('community.invite.store');
    Route::post('/invites/{invite}/accept', [CommunityInviteController::class, 'accept'])->name('community.invite.accept');
    Route::post('/invites/{invite}/decline', [CommunityInviteController::class, 'decline'])->name('community.invite.decline');
    Route::post('/invite', [CommunityInviteController::class, 'sendInvite'])->name('invite.send');
    Route::get('/users/search', [CommunityInviteController::class, 'searchUsers'])->name('users.search');
    Route::delete('/community/{community}/member/{user}', [CommunityController::class, 'removeMember'])->name('community.removeMember');
    Route::delete('/community/{community}/leave', [CommunityController::class, 'leaveCommunity'])->name('community.leave');
    Route::post('/posts/{id}/comments', [CommunityController::class, 'storeComment'])->name('comments.store');
    Route::delete('/posts/{id}', [CommunityController::class, 'destroyPost'])->name('posts.destroy');
    Route::delete('/comments/{id}', [CommunityController::class, 'destroyComment'])->name('comments.destroy');

    Route::view('/schedule', 'schedule')->name('schedule');
    Route::get('/messages', [MessageController::class, 'index'])->name('messages');
    Route::get('/messages/unread-count', [MessageController::class, 'unreadCount'])->name('messages.unread-count');
        Route::get('/messages/conversations', [MessageController::class, 'conversations'])->name('messages.conversations');
            Route::post('/messages/conversations', [MessageController::class, 'store'])->name('messages.conversations.store');
                Route::get('/messages/conversations/{conversation}', [MessageController::class, 'fetch'])->name('messages.conversations.fetch');
                    Route::post('/messages/conversations/{conversation}', [MessageController::class, 'storeMessage'])->name('messages.conversations.message');
                        Route::post('/messages/conversations/{conversation}/members', [MessageController::class, 'addMember'])->name('messages.conversations.members.add');
                            Route::delete('/messages/conversations/{conversation}/members/{user}', [MessageController::class, 'removeMember'])->name('messages.conversations.members.remove');
    Route::view('/history', 'history')->name('history');

    Route::get('/profile', [ProfileController::class, 'index'])->name('profile');
    Route::get('/profile/show', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile/picture', [ProfileController::class, 'removePicture'])->name('profile.picture.remove');

    // Public profile page for any user
    Route::get('/users/{user}/profile', [ProfileController::class, 'showUser'])->name('users.profile');

    Route::get('/users', [UserController::class, 'index'])->name('users');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/data', [UserController::class, 'data'])->name('users.data');
    Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

    Route::get('/admin/qualifications', [AdminQualificationController::class, 'index'])->name('admin.qualifications'); 
    Route::post('/admin/qualifications/{id}/respond', [AdminQualificationController::class, 'respond'])->name('admin.qualifications.respond');
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
