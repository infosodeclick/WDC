<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ComplaintController;
use App\Http\Controllers\HrController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\TicketController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/', [AuthController::class, 'showLogin'])->name('login');
    Route::get('/login', [AuthController::class, 'showLogin']);
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [PortalController::class, 'dashboard'])->name('dashboard');
    Route::get('/profile', [PortalController::class, 'profile'])->name('profile');
    Route::get('/announcements', [PortalController::class, 'announcements'])->name('announcements.index');
    Route::get('/knowledge', [PortalController::class, 'knowledge'])->name('knowledge.index');
    Route::get('/documents', [PortalController::class, 'documents'])->name('documents.index');
    Route::get('/documents/{document}/download', [PortalController::class, 'downloadDocument'])->name('documents.download');
    Route::get('/payroll', [PortalController::class, 'payroll'])->name('payroll');
    Route::get('/search', [PortalController::class, 'search'])->name('search');
    Route::post('/notifications/read', [PortalController::class, 'markNotificationsRead'])->name('notifications.read');

    Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::post('/tickets', [TicketController::class, 'store'])->name('tickets.store');
    Route::post('/tickets/{ticket}/comments', [TicketController::class, 'comment'])->name('tickets.comments.store');
    Route::patch('/tickets/{ticket}/status', [TicketController::class, 'updateStatus'])->name('tickets.status');

    Route::get('/complaints', [ComplaintController::class, 'index'])->name('complaints.index');
    Route::post('/complaints', [ComplaintController::class, 'store'])->name('complaints.store');
    Route::patch('/complaints/{complaint}/status', [ComplaintController::class, 'updateStatus'])->name('complaints.status');

    Route::get('/hr', [HrController::class, 'index'])->name('hr.index');
    Route::post('/hr/announcements', [HrController::class, 'storeAnnouncement'])->name('hr.announcements.store');
    Route::patch('/hr/employees/{user}/status', [HrController::class, 'updateEmployeeStatus'])->name('hr.employees.status');

    Route::get('/it', [TicketController::class, 'itDashboard'])->name('it.index');

    Route::get('/admin', [AdminController::class, 'index'])->name('admin.index');
    Route::post('/admin/users', [AdminController::class, 'storeUser'])->name('admin.users.store');
    Route::patch('/admin/users/{user}', [AdminController::class, 'updateUser'])->name('admin.users.update');
});
