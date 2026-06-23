<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ComplaintController;
use App\Http\Controllers\DirectoryController;
use App\Http\Controllers\HrController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\WorkflowController;
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
    Route::patch('/profile/contact', [PortalController::class, 'updateProfileContact'])->name('profile.contact.update');
    Route::get('/directory', [DirectoryController::class, 'index'])->name('directory.index');
    Route::get('/announcements', [PortalController::class, 'announcements'])->name('announcements.index');
    Route::get('/announcements/files/{file}', [PortalController::class, 'announcementFile'])->name('announcements.files.show');
    Route::get('/announcements/{announcement}', [PortalController::class, 'showAnnouncement'])->name('announcements.show');
    Route::get('/knowledge', [PortalController::class, 'knowledge'])->name('knowledge.index');
    Route::get('/documents', [PortalController::class, 'documents'])->name('documents.index');
    Route::get('/documents/{document}/download', [PortalController::class, 'downloadDocument'])->name('documents.download');
    Route::get('/meeting-rooms', [PortalController::class, 'meetingRooms'])->name('meeting-rooms.index');
    Route::post('/meeting-rooms', [PortalController::class, 'storeMeetingRoomBooking'])->name('meeting-rooms.store');
    Route::patch('/meeting-rooms/{booking}/cancel', [PortalController::class, 'cancelMeetingRoomBooking'])->name('meeting-rooms.cancel');
    Route::get('/payroll', [PortalController::class, 'payroll'])->name('payroll');
    Route::get('/time-attendance', [PortalController::class, 'timeAttendance'])->name('time-attendance');
    Route::get('/search', [PortalController::class, 'search'])->name('search');
    Route::post('/notifications/read', [PortalController::class, 'markNotificationsRead'])->name('notifications.read');

    Route::get('/workflows', [WorkflowController::class, 'index'])->name('workflows.index');
    Route::get('/workflows/export', [WorkflowController::class, 'export'])->name('workflows.export');
    Route::get('/workflows/import-template', [WorkflowController::class, 'downloadImportTemplate'])->name('workflows.import-template');
    Route::post('/workflows', [WorkflowController::class, 'store'])->name('workflows.store');
    Route::post('/workflows/import', [WorkflowController::class, 'importCsv'])->name('workflows.import');
    Route::post('/workflows/templates', [WorkflowController::class, 'storeTemplate'])->name('workflows.templates.store');
    Route::post('/workflows/templates/sync-smartflow', [WorkflowController::class, 'syncSmartflowCatalog'])->name('workflows.templates.sync-smartflow');
    Route::post('/workflows/templates/{template}/favorite', [WorkflowController::class, 'toggleFavorite'])->name('workflows.templates.favorite');
    Route::patch('/workflows/templates/{template}', [WorkflowController::class, 'updateTemplate'])->name('workflows.templates.update');
    Route::post('/workflows/{workflowRequest}/comments', [WorkflowController::class, 'comment'])->name('workflows.comments.store');
    Route::patch('/workflows/{workflowRequest}/status', [WorkflowController::class, 'updateStatus'])->name('workflows.status');

    Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::post('/tickets', [TicketController::class, 'store'])->name('tickets.store');
    Route::post('/tickets/{ticket}/comments', [TicketController::class, 'comment'])->name('tickets.comments.store');
    Route::patch('/tickets/{ticket}/status', [TicketController::class, 'updateStatus'])->name('tickets.status');

    Route::get('/complaints', [ComplaintController::class, 'index'])->name('complaints.index');
    Route::post('/complaints', [ComplaintController::class, 'store'])->name('complaints.store');
    Route::patch('/complaints/{complaint}/status', [ComplaintController::class, 'updateStatus'])->name('complaints.status');

    Route::get('/hr', [HrController::class, 'index'])->name('hr.index');
    Route::post('/hr/announcements', [HrController::class, 'storeAnnouncement'])->name('hr.announcements.store');
    Route::patch('/hr/profile-requests/{profileChangeRequest}', [HrController::class, 'reviewProfileChangeRequest'])->name('hr.profile-requests.review');
    Route::patch('/hr/employees/{user}/status', [HrController::class, 'updateEmployeeStatus'])->name('hr.employees.status');

    Route::get('/it', [TicketController::class, 'itDashboard'])->name('it.index');

    Route::get('/assets', [AssetController::class, 'index'])->name('assets.index');
    Route::get('/assets/export', [AssetController::class, 'export'])->name('assets.export');
    Route::get('/assets/master-data', [AssetController::class, 'exportMaster'])->name('assets.master-data');
    Route::post('/assets', [AssetController::class, 'store'])->name('assets.store');
    Route::post('/assets/categories', [AssetController::class, 'storeCategory'])->name('assets.categories.store');
    Route::post('/assets/locations', [AssetController::class, 'storeLocation'])->name('assets.locations.store');
    Route::post('/assets/inspections', [AssetController::class, 'storeInspection'])->name('assets.inspections.store');
    Route::post('/assets/sync', [AssetController::class, 'importSync'])->name('assets.sync.import');
    Route::patch('/assets/{asset}/status', [AssetController::class, 'updateStatus'])->name('assets.status');

    Route::get('/admin', [AdminController::class, 'index'])->name('admin.index');
    Route::post('/admin/users', [AdminController::class, 'storeUser'])->name('admin.users.store');
    Route::patch('/admin/users/{user}', [AdminController::class, 'updateUser'])->name('admin.users.update');
    Route::patch('/admin/users/{user}/access', [AdminController::class, 'updateUserAccess'])->name('admin.users.access');
    Route::patch('/admin/roles/{role}/permissions', [AdminController::class, 'updateRolePermissions'])->name('admin.roles.permissions');
});
