<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\CounterController as AdminCounterController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\ServiceScheduleController;
use App\Http\Controllers\Admin\TenantAdminController;
use App\Http\Controllers\Admin\TenantSettingsController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Counter\CounterPageController;
use App\Http\Controllers\Counter\CounterWorkflowController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\Public\QueueDisplayController;
use App\Http\Controllers\Public\QueueTicketController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/daftar', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/daftar', [RegisteredUserController::class, 'store'])->name('register.store');
    Route::get('/masuk', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/masuk', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::post('/keluar', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/tenant/buat', [TenantController::class, 'create'])->name('tenants.create');
    Route::post('/tenant', [TenantController::class, 'store'])->name('tenants.store');
    Route::delete('/tenant/{tenant}', [TenantController::class, 'destroy'])->name('tenants.destroy');
});

Route::get('/tenant/{tenant:code}/antrian', [QueueTicketController::class, 'index'])->name('tenant.queue');
Route::get('/tenant/{tenant:code}/antrian/tiket/{ticket}', [QueueTicketController::class, 'ticket'])->name('tenant.queue.ticket');
Route::get('/tenant/{tenant:code}/antrian/{service}', [QueueTicketController::class, 'show'])->name('tenant.queue.service');
Route::post('/tenant/{tenant:code}/antrian', [QueueTicketController::class, 'store'])->name('tenant.queue.store');

Route::get('/tenant/{tenant:code}/display', [QueueDisplayController::class, 'show'])->name('tenant.display');
Route::get('/tenant/{tenant:code}/display/snapshot', [QueueDisplayController::class, 'snapshot'])->name('tenant.display.snapshot');

Route::middleware(['auth', 'tenant.access:work'])->prefix('/tenant/{tenant}')->group(function () {
    Route::get('/counter', [CounterPageController::class, 'show'])->name('counter.show');
    Route::get('/counter/snapshot', [CounterPageController::class, 'snapshot'])->name('counter.snapshot');
    Route::post('/counter/context', [CounterWorkflowController::class, 'updateContext'])->name('counter.context');
    Route::post('/counter/call-next', [CounterWorkflowController::class, 'callNext'])->name('counter.call-next');
    Route::post('/counter/recall', [CounterWorkflowController::class, 'recall'])->name('counter.recall');
    Route::post('/counter/start-serving', [CounterWorkflowController::class, 'startServing'])->name('counter.start-serving');
    Route::post('/counter/complete', [CounterWorkflowController::class, 'complete'])->name('counter.complete');
    Route::post('/counter/skip', [CounterWorkflowController::class, 'skip'])->name('counter.skip');
    Route::post('/counter/cancel', [CounterWorkflowController::class, 'cancel'])->name('counter.cancel');
});

Route::middleware(['auth', 'tenant.access:manage'])->prefix('/tenant/{tenant}')->group(function () {
    Route::get('/admin', [AdminDashboardController::class, 'show'])->name('admin.show');
    Route::get('/admin/layanan', [AdminDashboardController::class, 'services'])->name('admin.services.page');
    Route::get('/admin/layanan/{service}/jadwal', [AdminDashboardController::class, 'serviceSchedules'])->name('admin.service-schedules.page');
    Route::get('/admin/counter', [AdminDashboardController::class, 'counters'])->name('admin.counters.page');
    Route::get('/admin/akses', [AdminDashboardController::class, 'users'])->name('admin.users.page');
    Route::get('/admin/pengaturan', [AdminDashboardController::class, 'settings'])->name('admin.settings.page');
    Route::get('/admin/snapshot', [AdminDashboardController::class, 'snapshot'])->name('admin.snapshot');
    Route::get('/admin/layanan/{service}/jadwal/snapshot', [AdminDashboardController::class, 'serviceSchedulesSnapshot'])->name('admin.service-schedules.snapshot');
    Route::post('/admin/services', [ServiceController::class, 'store'])->name('admin.services.store');
    Route::put('/admin/services/{service}', [ServiceController::class, 'update'])->name('admin.services.update');
    Route::delete('/admin/services/{service}', [ServiceController::class, 'destroy'])->name('admin.services.destroy');
    Route::post('/admin/schedules', [ServiceScheduleController::class, 'store'])->name('admin.schedules.store');
    Route::put('/admin/schedules/{serviceSchedule}', [ServiceScheduleController::class, 'update'])->name('admin.schedules.update');
    Route::delete('/admin/schedules/{serviceSchedule}', [ServiceScheduleController::class, 'destroy'])->name('admin.schedules.destroy');
    Route::post('/admin/counters', [AdminCounterController::class, 'store'])->name('admin.counters.store');
    Route::put('/admin/counters/{counter}', [AdminCounterController::class, 'update'])->name('admin.counters.update');
    Route::delete('/admin/counters/{counter}', [AdminCounterController::class, 'destroy'])->name('admin.counters.destroy');
    Route::post('/admin/users', [TenantAdminController::class, 'store'])->name('admin.users.store');
    Route::delete('/admin/users/{user}', [TenantAdminController::class, 'destroy'])->name('admin.users.destroy');
    Route::put('/admin/settings', [TenantSettingsController::class, 'update'])->name('admin.settings.update');
});
