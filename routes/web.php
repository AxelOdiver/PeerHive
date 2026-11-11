<?php

use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\OtpController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\GetStartedController;
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
use App\Http\Controllers\MessagingActivityController;
Route::get('/profile/email/confirm', [\App\Http\Controllers\AccountSecurityController::class, 'confirmEmail'])
    ->middleware(['signed', 'throttle:10,1,profile-email-confirm:'])->name('profile.email.confirm');
Route::view('/terms', 'legal.terms')->name('terms');
Route::view('/privacy', 'legal.privacy')->name('privacy');
Route::get('/profile-pictures/{filename}', [\App\Http\Controllers\ProfilePictureController::class, 'show'])
    ->where('filename', '[a-zA-Z0-9_.-]+')->name('profile-picture.show');
// Keep previously shared preview links working after the full modules launch.
Route::get('/feature-preview', fn () => redirect()->route(match (request('tab')) { 'rewards' => 'rewards.index', 'sessions' => 'study-sessions.index', default => 'progress.index' }))->middleware('auth')->name('feature.preview');
use App\Http\Controllers\ReportController;
/*
|--------------------------------------------------------------------------
| Authentication Routes (Guest Only)
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:5,1,login:')->name('login.store');
    Route::get('/register', [RegisterController::class, 'index'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:5,10,register:')->name('register.store');

    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:5,1,password-email:')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:5,1,password-update:')->name('password.update');

    Route::get('/otp', [OtpController::class, 'show'])->name('otp.show');
    Route::post('/otp', [OtpController::class, 'verify'])->middleware('throttle:5,1,otp-verify:')->name('otp.verify');
    Route::post('/otp/resend', [OtpController::class, 'resend'])->middleware('throttle:2,1,otp-resend:')->name('otp.resend');
});

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'auth.session'])->group(function () {
    Route::get('/get-started', [GetStartedController::class, 'show'])->name('onboarding.show');
    Route::post('/get-started/skills', [GetStartedController::class, 'skills'])->name('onboarding.skills');
    Route::post('/get-started/learn', [GetStartedController::class, 'learn'])->name('onboarding.learn');
    Route::post('/get-started/skip', [GetStartedController::class, 'skip'])->name('onboarding.skip');
    Route::get('/progress/skill-tree', [\App\Http\Controllers\SkillTreeController::class, 'index'])->name('progress.skill-tree');
    Route::get('/admin/learning-paths', [\App\Http\Controllers\SkillTreeController::class, 'admin'])->middleware('admin')->name('admin.learning-paths');
    Route::put('/admin/learning-paths/{topic}', [\App\Http\Controllers\SkillTreeController::class, 'update'])->middleware('admin')->name('admin.learning-paths.update');
    Route::get('/messages/conversations/{conversation}/activities', [\App\Http\Controllers\SessionActivityController::class, 'index'])->name('session-activities.index');
    Route::post('/messages/conversations/{conversation}/activities', [\App\Http\Controllers\SessionActivityController::class, 'store'])->middleware('throttle:10,1,activity-store:')->name('session-activities.store');
    Route::post('/messages/conversations/{conversation}/activities/{activity}/submit', [\App\Http\Controllers\SessionActivityController::class, 'submit'])->middleware('throttle:10,1,activity-submit:')->name('session-activities.submit');
    Route::post('/messages/conversations/{conversation}/activities/{activity}/review', [\App\Http\Controllers\SessionActivityController::class, 'review'])->middleware('throttle:10,1,activity-review:')->name('session-activities.review');
    Route::post('/catalog/suggestions', [\App\Http\Controllers\CatalogController::class, 'store'])->middleware('throttle:10,1,catalog-suggest:')->name('catalog.suggest');
    Route::get('/admin/catalog', [\App\Http\Controllers\CatalogController::class, 'index'])->middleware('admin')->name('admin.catalog');
    Route::post('/admin/catalog/{entry}', [\App\Http\Controllers\CatalogController::class, 'review'])->middleware('admin')->name('admin.catalog.review');
    Route::get('/private/messages/{message}/attachment', [\App\Http\Controllers\PrivateFileController::class, 'message'])->name('private.message');
    Route::get('/private/qualifications/{qualification}', [\App\Http\Controllers\PrivateFileController::class, 'qualification'])->name('private.qualification');
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/search', [DashboardController::class, 'search'])->name('search');
    // v4.7.7g.1: privacy-aware global discovery, separate from the legacy /search API.
    Route::get('/discover', [\App\Http\Controllers\SearchDiscoveryController::class, 'index'])->name('discover.index');
    Route::get('/discover/results', [\App\Http\Controllers\SearchDiscoveryController::class, 'results'])->middleware('throttle:60,1,discover-search:')->name('discover.results');
    Route::get('/progress', [\App\Http\Controllers\LearningProgressController::class, 'index'])->name('progress.index');
    Route::put('/progress/goals', [\App\Http\Controllers\LearningProgressController::class, 'goals'])->name('progress.goals');
    Route::put('/progress/settings', [\App\Http\Controllers\LearningProgressController::class, 'settings'])->name('progress.settings');
    Route::get('/rewards', [\App\Http\Controllers\RewardsController::class, 'index'])->name('rewards.index');
    Route::post('/rewards/unlock', [\App\Http\Controllers\RewardsController::class, 'unlock'])->name('rewards.unlock');
    Route::put('/rewards/equip', [\App\Http\Controllers\RewardsController::class, 'equip'])->name('rewards.equip');
    Route::get('/study-sessions', [\App\Http\Controllers\StudySessionController::class, 'index'])->name('study-sessions.index');
    Route::get('/study-sessions/create', [\App\Http\Controllers\StudySessionController::class, 'create'])->name('study-sessions.create');
    Route::post('/study-sessions', [\App\Http\Controllers\StudySessionController::class, 'store'])->name('study-sessions.store');
    Route::get('/study-sessions/{session}', [\App\Http\Controllers\StudySessionController::class, 'show'])->name('study-sessions.show');
    Route::post('/study-sessions/{session}/join', [\App\Http\Controllers\StudySessionController::class, 'join'])->name('study-sessions.join');
    Route::delete('/study-sessions/{session}/leave', [\App\Http\Controllers\StudySessionController::class, 'leave'])->name('study-sessions.leave');
    Route::post('/study-sessions/{session}/cancel', [\App\Http\Controllers\StudySessionController::class, 'cancel'])->name('study-sessions.cancel');
    Route::post('/study-sessions/{session}/complete', [\App\Http\Controllers\StudySessionController::class, 'complete'])->name('study-sessions.complete');
    Route::post('/study-sessions/{session}/confirm', [\App\Http\Controllers\StudySessionController::class, 'confirm'])->name('study-sessions.confirm');

    Route::post('/apply-to-teach', [QualificationController::class, 'store'])->middleware('throttle:5,10,qualification-store:')->name('qualifications.store');

    Route::post('/favorite/toggle', [FavoriteController::class, 'toggleFavorite'])->name('favorite.toggle');
    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');

    Route::post('/likes/toggle', [LikeController::class, 'toggle'])->name('likes.toggle');

    Route::post('/swap/{swap}/respond', [SwapController::class, 'respond'])->name('swap.respond');
    Route::post('/swap/add', [SwapController::class, 'add'])->name('swap.add');
    Route::get('/swap/options/{user}', [SwapController::class, 'options'])->name('swap.options');
    Route::get('/swap', [SwapController::class, 'index'])->name('swap');
    Route::delete('/swap/{swap}', [SwapController::class, 'destroy'])->name('swap.destroy');

    Route::get('/schedule/data', [ScheduleController::class, 'getSchedule'])->name('schedule.data');
    Route::post('/schedule', [ScheduleController::class, 'store'])->name('schedule.store');
    Route::delete('/schedule', [ScheduleController::class, 'destroy'])->name('schedule.destroy');

    Route::get('/community', [CommunityController::class, 'index'])->name('community');
    Route::post('/community/{community}/banner', [\App\Http\Controllers\CommunityBannerController::class, 'store'])->middleware('throttle:5,10,community-banner:')->name('community.banner.store');
    Route::delete('/community/{community}/banner', [\App\Http\Controllers\CommunityBannerController::class, 'destroy'])->middleware('throttle:5,10,community-banner:')->name('community.banner.destroy');
    Route::get('/community-banners/{banner}', [\App\Http\Controllers\CommunityBannerController::class, 'show'])->name('community.banner.show');
    Route::get('/community/{community}', [CommunityController::class, 'show'])->name('community.show');
    Route::post('/community', [CommunityController::class, 'store'])->name('community.store');
    Route::post('/community/{community}/join', [\App\Http\Controllers\CommunityJoinRequestController::class, 'store'])->middleware('throttle:10,1,community-join:')->name('community.join');
    Route::delete('/community/{community}/join-request', [\App\Http\Controllers\CommunityJoinRequestController::class, 'cancel'])->name('community.join.cancel');
    Route::post('/community/{community}/join-requests/{joinRequest}', [\App\Http\Controllers\CommunityJoinRequestController::class, 'respond'])->name('community.join.respond');
    Route::delete('/community/{community}', [CommunityController::class, 'destroy'])->name('community.destroy');
    Route::put('/community/{community}', [CommunityController::class, 'update'])->name('community.update');
    Route::put('/community/{id}/tags', [CommunityController::class, 'updateTags'])->name('community.tags.update');
    Route::post('/community/{id}/posts', [CommunityController::class, 'storePost'])->name('community.posts.store');
    Route::get('/posts/{post}/media', [CommunityController::class, 'postMedia'])->name('posts.media');
    Route::post('/communities/{community}/invite', [CommunityInviteController::class, 'store'])->name('community.invite.store');
    Route::post('/invites/{invite}/accept', [CommunityInviteController::class, 'accept'])->name('community.invite.accept');
    Route::post('/invites/{invite}/decline', [CommunityInviteController::class, 'decline'])->name('community.invite.decline');
    Route::post('/invite', [CommunityInviteController::class, 'sendInvite'])->middleware('throttle:10,1,invite-send:')->name('invite.send');
    Route::get('/users/search', [CommunityInviteController::class, 'searchUsers'])->middleware('throttle:60,1,users-search:')->name('users.search');
    Route::delete('/community/{community}/member/{user}', [CommunityController::class, 'removeMember'])->name('community.removeMember');
    Route::delete('/community/{community}/leave', [CommunityController::class, 'leaveCommunity'])->name('community.leave');
    Route::post('/posts/{id}/comments', [CommunityController::class, 'storeComment'])->name('comments.store');
    Route::delete('/posts/{id}', [CommunityController::class, 'destroyPost'])->name('posts.destroy');
    Route::delete('/comments/{id}', [CommunityController::class, 'destroyComment'])->name('comments.destroy');
    Route::post('/reports', [ReportController::class, 'store'])->name('reports.store');
    Route::post('/users/{user}/block', [\App\Http\Controllers\UserBlockController::class, 'store'])->name('users.block');
    Route::delete('/users/{user}/block', [\App\Http\Controllers\UserBlockController::class, 'destroy'])->name('users.unblock');

    Route::get('/schedule', [ScheduleController::class, 'index'])->name('schedule');
    Route::post('/presence/heartbeat', [MessagingActivityController::class, 'heartbeat'])->middleware('throttle:6,1,presence-heartbeat:')->name('presence.heartbeat');
    Route::get('/messages/conversations/{conversation}/state', [MessagingActivityController::class, 'state'])->name('messages.state');
    Route::post('/messages/conversations/{conversation}/typing', [MessagingActivityController::class, 'typing'])->middleware('throttle:60,1,messages-typing:')->name('messages.typing');
    Route::post('/messages/conversations/{conversation}/read', [MessagingActivityController::class, 'read'])->middleware('throttle:60,1,messages-read:')->name('messages.read');
    Route::post('/messages/conversations/{conversation}/rating', [\App\Http\Controllers\SessionRatingController::class, 'store'])->middleware('throttle:20,1,messages-rating:');
    Route::get('/messages/conversations/{conversation}/session', [\App\Http\Controllers\SessionChatController::class, 'show']);
    Route::post('/messages/conversations/{conversation}/session', [\App\Http\Controllers\SessionChatController::class, 'update']);
    Route::get('/messages', [MessageController::class, 'index'])->name('messages');
    Route::get('/messages/member-search', [MessageController::class, 'searchMembers'])->middleware('throttle:60,1,messages-member-search:')->name('messages.member-search');
    Route::post('/messages/group-invitations/{invitation}/decision', [\App\Http\Controllers\GroupChatController::class, 'decide'])->middleware('throttle:20,1,group-invite-decision:')->name('messages.groups.invitation.decision');
    Route::get('/messages/groups/{conversation}/photo', [\App\Http\Controllers\GroupChatController::class, 'photo'])->name('messages.group.photo');
    Route::patch('/messages/groups/{conversation}/name', [\App\Http\Controllers\GroupChatController::class, 'rename'])->middleware('throttle:10,1,group-rename:')->name('messages.groups.rename');
    Route::post('/messages/groups/{conversation}/photo', [\App\Http\Controllers\GroupChatController::class, 'uploadPhoto'])->middleware('throttle:5,10,group-photo:')->name('messages.groups.photo.update');
    Route::delete('/messages/groups/{conversation}/photo', [\App\Http\Controllers\GroupChatController::class, 'removePhoto'])->name('messages.groups.photo.remove');
    Route::patch('/messages/groups/{conversation}/members/{user}/role', [\App\Http\Controllers\GroupChatController::class, 'changeRole'])->name('messages.groups.role');

    Route::get('/messages/unread-count', [MessageController::class, 'unreadCount'])->name('messages.unread-count');
    Route::post('/notifications/read-all', [\App\Http\Controllers\BellNotificationController::class, 'readAll']);
    Route::post('/notifications/clear-read', [\App\Http\Controllers\BellNotificationController::class, 'clearRead']);
    Route::post('/notifications/{notification}/read', [\App\Http\Controllers\BellNotificationController::class, 'read'])->whereNumber('notification');
    Route::get('/messages/conversations', [MessageController::class, 'conversations'])->name('messages.conversations');
    Route::post('/messages/conversations', [MessageController::class, 'store'])->name('messages.conversations.store');
    Route::delete('/messages/conversations/{conversation}', [MessageController::class, 'destroyConversation'])->name('messages.conversations.destroy');
    Route::get('/messages/conversations/{conversation}', [MessageController::class, 'fetch'])->name('messages.conversations.fetch');
    Route::post('/messages/conversations/{conversation}', [MessageController::class, 'storeMessage'])->middleware('throttle:30,1,messages-send:')->name('messages.conversations.message');
    Route::post('/messages/conversations/{conversation}/members', [MessageController::class, 'addMember'])->name('messages.conversations.members.add');
    Route::delete('/messages/conversations/{conversation}/members/{user}', [MessageController::class, 'removeMember'])->name('messages.conversations.members.remove');
    Route::post('/messages/{message}/reactions', [MessageController::class, 'react'])->name('messages.react');
    Route::get('/messages/conversations/{conversation}/info', [MessageController::class, 'info'])->name('messages.info');
    Route::put('/messages/conversations/{conversation}/mute', [MessageController::class, 'mute'])->name('messages.mute');
    Route::delete('/messages/conversations/{conversation}/leave', [MessageController::class, 'leave'])->name('messages.leave');
    Route::delete('/messages/{message}/for-me', [MessageController::class, 'deleteMessageForMe'])->name('messages.delete-for-me');
    Route::put('/messages/{message}', [MessageController::class, 'editMessage'])->name('messages.edit');
    Route::delete('/messages/{message}', [MessageController::class, 'deleteMessage'])->name('messages.delete');
    Route::get('/history', [\App\Http\Controllers\LearningProgressController::class, 'history'])->name('history');

    Route::post('/profile/email/change', [\App\Http\Controllers\AccountSecurityController::class, 'requestEmail'])
        ->middleware('throttle:3,10,profile-email-change:')->name('profile.email.request');
    Route::post('/profile/password/change', [\App\Http\Controllers\AccountSecurityController::class, 'updatePassword'])
        ->middleware('throttle:5,10,profile-password-change:')->name('profile.password.update');
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile');
    Route::get('/profile/show', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile/picture', [ProfileController::class, 'removePicture'])->name('profile.picture.remove');

    // Public profile page for any user
    Route::get('/users/{user}/profile', [ProfileController::class, 'showUser'])->name('users.profile');
    Route::get('/users/{user}/reviews', [ProfileController::class, 'reviews'])->name('users.reviews');
    Route::patch('/reviews/{rating}', [ProfileController::class, 'updateReview'])->middleware('throttle:10,1,review-edit:')->name('reviews.update');

    Route::middleware('admin')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/data', [UserController::class, 'data'])->name('users.data');
        Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });

    Route::get('/admin/qualifications', [AdminQualificationController::class, 'index'])->name('admin.qualifications'); 
    Route::post('/admin/qualifications/{id}/respond', [AdminQualificationController::class, 'respond'])->name('admin.qualifications.respond');
    Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/reports', [AdminReportController::class, 'index'])->name('reports.index');
    Route::patch('/reports/{report}/dismiss', [AdminReportController::class, 'dismiss'])->name('reports.dismiss');
    Route::delete('/reports/{report}/content', [AdminReportController::class, 'destroyContent'])->name('reports.destroyContent');
});
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
