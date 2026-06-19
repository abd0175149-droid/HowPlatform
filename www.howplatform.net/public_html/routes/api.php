<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Performance\PerformanceApiController;
use App\Http\Controllers\Performance\PerformanceReportApiController as Reports;

Route::prefix('performance')->group(function () {

    // ===== Meta =====
    Route::get('/weeks',    [PerformanceApiController::class,'weeks']);
    Route::get('/tasks',    [PerformanceApiController::class,'tasks']);
    Route::get('/groups',   [PerformanceApiController::class,'groups']);   // ?with=members
    Route::get('/students', [PerformanceApiController::class,'students']); // مع group_id

    // ===== Create (المسارات المفقودة) =====
    Route::post('/weeks/create', [PerformanceApiController::class,'createWeek']);
    Route::post('/tasks/create', [PerformanceApiController::class,'createTask']);

    // ===== Groups =====
    Route::patch('/groups/{id}',       [PerformanceApiController::class,'renameGroup']);
    Route::delete('/groups/{id}',      [PerformanceApiController::class,'deleteGroup']);
    Route::get('/groups/{id}/members', [PerformanceApiController::class,'groupMembers']);
    Route::post('/groups/assign',      [PerformanceApiController::class,'assignMany']);
    Route::post('/groups/unassign',    [PerformanceApiController::class,'unassignMany']);
    Route::post('/groups/assign-bulk', [PerformanceApiController::class,'assignBulk']);

    // ===== Students import =====
    Route::post('/students/preview',     [PerformanceApiController::class,'previewStudents']); // إن كانت لديك
    Route::post('/students/import',      [PerformanceApiController::class,'importStudents']);  // إن كانت لديك
    Route::post('/students/import-text', [Reports::class,'importStudentsText']);               // بقيت ضمن Reports

    // ===== Weekly tracking =====
    Route::get('/tracking',     [PerformanceApiController::class,'tracking']);   // ?week_id=&group_id=
    Route::post('/bulk/update', [PerformanceApiController::class,'bulkUpdate']); // حالات/علامات

    // ===== Reports =====
    Route::prefix('reports')->group(function () {
        Route::get('/summary',       [Reports::class,'reportsSummary']);
        Route::get('/aggregates',    [Reports::class,'reportsAggregates']);
        Route::get('/print',         [Reports::class,'printView']);
        Route::get('/export/excel',  [Reports::class,'exportExcel']);
        Route::get('/summary-legacy',[Reports::class,'reportSummary']);
        Route::get('/progress-legacy',[Reports::class,'reportProgress']);
    });
});
