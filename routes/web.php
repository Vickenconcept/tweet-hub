<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TwitterAuthController;

Route::get('/', function () {
    return view('welcome');
});



Route::middleware('guest')->group(function () {
    Route::view('/login', 'auth.login')->name('login');
    Route::view('register', 'auth.register')->name('register');
    Route::view('register/success', 'auth.success')->name('register.success');


    Route::controller(AuthController::class)->prefix('auth')->name('auth.')->group(function () {
        Route::post('/register', 'register')->name('register');
        Route::post('/login', 'login')->name('login');
    });
    Route::controller(PasswordResetController::class)->group(function () {
        Route::get('forgot-password', 'index')->name('password.request');
        Route::post('forgot-password', 'store')->name('password.email');
        Route::get('/reset-password/{token}', 'reset')->name('password.reset');
        Route::post('/reset-password', 'update')->name('password.update');
    });
});


Route::middleware(['auth'])->group(function () {
    Route::get('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('/home', function () {
        return view('dashboard');
    })->name('home');

    // Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.markAsRead');
    // // Mark all notifications as read
    // Route::patch('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.markAllAsRead');

    Route::view('profile', 'profile')->name('profile');
    Route::post('profile/name', [ProfileController::class, 'changeName'])->name('changeName');
    Route::post('profile/password', [ProfileController::class, 'changePassword'])->name('changePassword');
    
    // New routes for the Livewire components
    Route::view('daily-post-ideas', 'daily-post-ideas')->name('daily-post-ideas');
    Route::view('generate-post-ideas', 'generate-post-ideas')->name('generate-post-ideas');
    Route::view('queued-posts', 'queued-posts')->name('queued-posts');
    Route::view('twitter-mentions', 'twitter-mentions')->name('twitter-mentions');
    Route::view('keyword-monitoring', 'keyword-monitoring')->name('keyword-monitoring');
    Route::view('tweet-analytics', 'tweet-analytics')->name('tweet-analytics');
    Route::view('bookmarks-management', 'bookmarks-management')->name('bookmarks-management');
    Route::view('user-management', 'user-management')->name('user-management');
    Route::view('assets', 'assets')->name('assets');
    
    // Route::resource('reseller', ResellerController::class);

});

Route::get('/connect/twitter', [TwitterAuthController::class, 'redirectToTwitter'])->name('twitter.connect');
Route::get('/connect/twitter/callback', [TwitterAuthController::class, 'handleTwitterCallback'])->name('twitter.callback');