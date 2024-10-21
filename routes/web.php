<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\TestController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\ResponseController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::get('/', function () {
    return view('welcome');
})->name('home');

// Authentication routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/forgot-password', [AuthController::class, 'showForgotPasswordForm'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLinkEmail'])->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPasswordForm'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Student routes
Route::prefix('student')->name('student.')->middleware('student.session')->group(function () {
    Route::get('/biodata', [StudentController::class, 'showBiodataForm'])->name('biodata')->withoutMiddleware('student.session');
    Route::post('/biodata', [StudentController::class, 'storeBiodata'])->withoutMiddleware('student.session');
    Route::get('/tests', [StudentController::class, 'showAvailableTests'])->name('tests');
    Route::get('/test/{test}', [StudentController::class, 'startTest'])->name('test.start');
    Route::post('/test/{test}/submit', [StudentController::class, 'submitTest'])->name('test.submit');
    Route::get('/test/{test}/result', [StudentController::class, 'showTestResult'])->name('test.result');
    Route::get('/history', [StudentController::class, 'showTestHistory'])->name('history');
    Route::post('/logout', [StudentController::class, 'logout'])->name('logout');
});

// Admin and Teacher routes
Route::middleware(['auth', 'role:admin,teacher'])->group(function () {
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');

    // User management (admin only)
    Route::middleware(['role:admin'])->prefix('users')->name('users.')->group(function () {
        Route::get('/', [AdminController::class, 'users'])->name('index');
        Route::get('/create', [AdminController::class, 'createUser'])->name('create');
        Route::post('/', [AdminController::class, 'storeUser'])->name('store');
        Route::get('/{user}', [AdminController::class, 'showUser'])->name('show');
        Route::get('/{user}/edit', [AdminController::class, 'editUser'])->name('edit');
        Route::put('/{user}', [AdminController::class, 'updateUser'])->name('update');
        Route::delete('/{user}', [AdminController::class, 'destroyUser'])->name('destroy');
        Route::get('/{user}/change-password', [AdminController::class, 'changePasswordForm'])->name('change-password');
        Route::post('/{user}/change-password', [AdminController::class, 'changePassword'])->name('update-password');
    });

    // Test management
    Route::resource('tests', TestController::class);
    Route::prefix('tests')->name('tests.')->group(function () {
        Route::post('/{test}/duplicate', [TestController::class, 'duplicate'])->name('duplicate');
        Route::post('/{test}/toggle-randomization', [TestController::class, 'toggleRandomization'])->name('toggle-randomization');
        Route::get('/{test}/preview', [TestController::class, 'preview'])->name('preview');
        Route::get('/{test}/results', [TestController::class, 'results'])->name('results');
        Route::get('/{test}/export-results', [TestController::class, 'exportResults'])->name('export-results');
        Route::post('/{test}/send-results', [TestController::class, 'sendTestResults'])->name('send-results');
    });

    // Question management
    Route::resource('tests.questions', QuestionController::class)->shallow();
    Route::post('/questions/{question}/randomize-options', [QuestionController::class, 'randomizeOptions'])->name('questions.randomize-options');
    Route::post('/tests/{test}/questions/bulk-delete', [QuestionController::class, 'bulkDelete'])->name('questions.bulk-delete');

    // Response management
    Route::prefix('tests/{test}/responses')->name('responses.')->group(function () {
        Route::get('/', [ResponseController::class, 'index'])->name('index');
        Route::get('/{student}', [ResponseController::class, 'show'])->name('show');
        Route::post('/bulk-grade', [ResponseController::class, 'bulkGrade'])->name('bulk-grade');
        Route::get('/export', [ResponseController::class, 'export'])->name('export');
        Route::get('/analytics', [ResponseController::class, 'analytics'])->name('analytics');
    });
    Route::post('/responses/{response}/grade', [ResponseController::class, 'grade'])->name('responses.grade');

    // Student management
    Route::get('/students', [AdminController::class, 'students'])->name('students.index');
    Route::get('/students/{student}', [AdminController::class, 'showStudent'])->name('students.show');

    // Reporting
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [AdminController::class, 'reports'])->name('index');
        Route::get('/test/{test}', [AdminController::class, 'generateTestReport'])->name('test');
    });

    // Settings
    Route::get('/settings', [AdminController::class, 'settings'])->name('settings.index');
    Route::post('/settings', [AdminController::class, 'updateSettings'])->name('settings.update');
});

// Fallback route for undefined routes
Route::fallback(function () {
    return view('errors.404');
});