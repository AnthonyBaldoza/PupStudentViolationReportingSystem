<?php

use App\Http\Controllers\AppealController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DisciplinaryActionController;
use App\Http\Controllers\EvidenceController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OffenseRuleController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ViolationCategoryController;
use App\Http\Controllers\ViolationRecordController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

// ---------------------------------------------------------------- guests
Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:30,1')->name('login.store');
});

// ---------------------------------------------------------------- signed-in (both roles)
Route::middleware(['auth', 'active'])->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    // Outside "password.changed" so a forced password change can actually happen.
    Route::get('account/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('account/password', [PasswordController::class, 'update'])->name('password.update');

    Route::middleware('password.changed')->group(function () {
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // Notifications
        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.readAll');
        Route::post('notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');

        // Students: read access for officers too
        Route::get('students', [StudentController::class, 'index'])->name('students.index');
        Route::get('students/lookup', [StudentController::class, 'lookup'])->middleware('throttle:60,1')->name('students.lookup');

        // Violation reports (scoping/authorisation enforced in controller + policy)
        Route::get('records', [ViolationRecordController::class, 'index'])->name('records.index');
        Route::get('evidence/{evidence}', [EvidenceController::class, 'show'])->whereNumber('evidence')->name('evidence.show');

        // ------------------------------------------------------------ officers only
        Route::middleware('role:officer')->group(function () {
            Route::get('records/create', [ViolationRecordController::class, 'create'])->name('records.create');
            Route::post('records', [ViolationRecordController::class, 'store'])->name('records.store');
            Route::get('records/{record}/edit', [ViolationRecordController::class, 'edit'])->whereNumber('record')->name('records.edit');
            Route::put('records/{record}', [ViolationRecordController::class, 'update'])->whereNumber('record')->name('records.update');
        });

        // Declared after /create so "create" is never treated as a record id.
        Route::get('records/{record}', [ViolationRecordController::class, 'show'])->whereNumber('record')->name('records.show');
        Route::get('students/{student}', [StudentController::class, 'show'])->whereNumber('student')->withTrashed()->name('students.show');

        // ------------------------------------------------------------ admins only
        Route::middleware('role:admin')->group(function () {
            // Students
            Route::get('students/create', [StudentController::class, 'create'])->name('students.create');
            Route::post('students', [StudentController::class, 'store'])->name('students.store');
            Route::get('students/{student}/edit', [StudentController::class, 'edit'])->whereNumber('student')->name('students.edit');
            Route::put('students/{student}', [StudentController::class, 'update'])->whereNumber('student')->name('students.update');
            Route::delete('students/{student}', [StudentController::class, 'archive'])->whereNumber('student')->name('students.archive');
            Route::post('students/{student}/restore', [StudentController::class, 'restore'])->whereNumber('student')->withTrashed()->name('students.restore');

            // Review workflow
            Route::post('records/{record}/approve', [ReviewController::class, 'approve'])->whereNumber('record')->name('records.approve');
            Route::post('records/{record}/reject', [ReviewController::class, 'reject'])->whereNumber('record')->name('records.reject');
            Route::post('records/{record}/return', [ReviewController::class, 'return'])->whereNumber('record')->name('records.return');

            // Appeals
            Route::post('records/{record}/appeals', [AppealController::class, 'store'])->whereNumber('record')->name('appeals.store');
            Route::post('appeals/{appeal}/decision', [AppealController::class, 'decide'])->whereNumber('appeal')->name('appeals.decide');

            // Violation categories + offense rules
            Route::get('categories', [ViolationCategoryController::class, 'index'])->name('categories.index');
            Route::get('categories/create', [ViolationCategoryController::class, 'create'])->name('categories.create');
            Route::post('categories', [ViolationCategoryController::class, 'store'])->name('categories.store');
            Route::get('categories/{category}/edit', [ViolationCategoryController::class, 'edit'])->whereNumber('category')->name('categories.edit');
            Route::put('categories/{category}', [ViolationCategoryController::class, 'update'])->whereNumber('category')->name('categories.update');
            Route::post('categories/{category}/toggle', [ViolationCategoryController::class, 'toggle'])->whereNumber('category')->name('categories.toggle');
            Route::post('categories/{category}/rules', [OffenseRuleController::class, 'store'])->whereNumber('category')->name('rules.store');
            Route::put('rules/{rule}', [OffenseRuleController::class, 'update'])->whereNumber('rule')->name('rules.update');
            Route::post('rules/{rule}/toggle', [OffenseRuleController::class, 'toggle'])->whereNumber('rule')->name('rules.toggle');

            // Disciplinary actions
            Route::get('disciplinary-actions', [DisciplinaryActionController::class, 'index'])->name('actions.index');
            Route::post('disciplinary-actions', [DisciplinaryActionController::class, 'store'])->name('actions.store');
            Route::put('disciplinary-actions/{disciplinaryAction}', [DisciplinaryActionController::class, 'update'])->whereNumber('disciplinaryAction')->name('actions.update');
            Route::post('disciplinary-actions/{disciplinaryAction}/toggle', [DisciplinaryActionController::class, 'toggle'])->whereNumber('disciplinaryAction')->name('actions.toggle');

            // Courses + sections
            Route::get('courses', [CourseController::class, 'index'])->name('courses.index');
            Route::post('courses', [CourseController::class, 'store'])->name('courses.store');
            Route::put('courses/{course}', [CourseController::class, 'update'])->whereNumber('course')->name('courses.update');
            Route::post('courses/{course}/sections', [SectionController::class, 'store'])->whereNumber('course')->name('sections.store');
            Route::delete('sections/{section}', [SectionController::class, 'destroy'])->whereNumber('section')->name('sections.destroy');

            // Officer / admin accounts
            Route::get('users', [UserController::class, 'index'])->name('users.index');
            Route::get('users/create', [UserController::class, 'create'])->name('users.create');
            Route::post('users', [UserController::class, 'store'])->name('users.store');
            Route::get('users/{user}/edit', [UserController::class, 'edit'])->whereNumber('user')->name('users.edit');
            Route::put('users/{user}', [UserController::class, 'update'])->whereNumber('user')->name('users.update');
            Route::post('users/{user}/toggle', [UserController::class, 'toggle'])->whereNumber('user')->name('users.toggle');
            Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])->whereNumber('user')->name('users.resetPassword');

            // Reports & statistics
            Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
            Route::get('reports/print', [ReportController::class, 'print'])->name('reports.print');
            Route::get('reports/csv', [ReportController::class, 'csv'])->name('reports.csv');
            Route::get('reports/pdf', [ReportController::class, 'pdf'])->name('reports.pdf');

            // Audit trail + settings
            Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit.index');
            Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
            Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
        });
    });
});
