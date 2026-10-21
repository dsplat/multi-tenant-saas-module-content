<?php

use Illuminate\Support\Facades\Route;
use MultiTenantSaas\Modules\Content\Http\Controllers\MaterialController;
use MultiTenantSaas\Modules\Content\Http\Controllers\QualityInspectionController;
use MultiTenantSaas\Modules\Content\Http\Controllers\ScriptController;
use MultiTenantSaas\Modules\Content\Http\Controllers\SensitiveWordController;

/*
|--------------------------------------------------------------------------
| 内容中心路由（运营面）
|--------------------------------------------------------------------------
|
| 前缀由 ContentServiceProvider::loadModuleRoutes() 从 config('content.route_prefix')
| 读取（框架默认 api/v1；下游设 CONTENT_ROUTE_PREFIX=api/v1/biz 沿用既有契约）。
|
| 读写分离：素材/话术/敏感词读端点 material.view、写端点 material.manage；
| 质检读端点 quality.view、写端点 quality.manage。RBAC 只作用于 Operator。
|
*/

Route::middleware('rbac.permission:material.view')->group(function () {
    Route::get('materials', [MaterialController::class, 'index']);
    Route::get('materials/{id}/compliance', [MaterialController::class, 'checkCompliance']);
    Route::get('materials/{id}', [MaterialController::class, 'show']);

    Route::get('sensitive-words', [SensitiveWordController::class, 'index']);
    Route::get('sensitive-words/{id}', [SensitiveWordController::class, 'show']);

    Route::get('scripts/categories', [ScriptController::class, 'categories']);
    Route::get('scripts/stats', [ScriptController::class, 'stats']);
    Route::get('scripts/search', [ScriptController::class, 'search']);
    Route::post('scripts/recommend', [ScriptController::class, 'recommend']);
    Route::get('scripts', [ScriptController::class, 'index']);
    Route::get('scripts/{id}', [ScriptController::class, 'show']);
});

Route::middleware('rbac.permission:material.manage')->group(function () {
    Route::post('materials', [MaterialController::class, 'store']);
    Route::patch('materials/{id}/categorize', [MaterialController::class, 'categorize']);
    Route::post('materials/{id}/duplicate', [MaterialController::class, 'duplicate']);
    Route::put('materials/{id}', [MaterialController::class, 'update']);
    Route::delete('materials/{id}', [MaterialController::class, 'destroy']);

    Route::post('sensitive-words/import', [SensitiveWordController::class, 'import']);
    Route::post('sensitive-words', [SensitiveWordController::class, 'store']);
    Route::put('sensitive-words/{id}', [SensitiveWordController::class, 'update']);
    Route::delete('sensitive-words/{id}', [SensitiveWordController::class, 'destroy']);

    Route::post('scripts', [ScriptController::class, 'store']);
    Route::post('scripts/{id}/use', [ScriptController::class, 'recordUsage']);
    Route::post('scripts/{id}/duplicate', [ScriptController::class, 'duplicate']);
    Route::put('scripts/{id}', [ScriptController::class, 'update']);
    Route::delete('scripts/{id}', [ScriptController::class, 'destroy']);
});

Route::middleware('rbac.permission:quality.view')->group(function () {
    Route::get('inspection-rules', [QualityInspectionController::class, 'rules']);
    Route::get('inspection-results/stats', [QualityInspectionController::class, 'resultStats']);
    Route::get('inspection-results', [QualityInspectionController::class, 'results']);
    Route::get('inspection-results/{id}', [QualityInspectionController::class, 'showResult']);
    Route::get('inspection-alerts', [QualityInspectionController::class, 'alerts']);
});

Route::middleware('rbac.permission:quality.manage')->group(function () {
    Route::post('inspection-rules', [QualityInspectionController::class, 'storeRule']);
    Route::put('inspection-rules/{id}', [QualityInspectionController::class, 'updateRule']);
    Route::delete('inspection-rules/{id}', [QualityInspectionController::class, 'destroyRule']);
    Route::patch('inspection-rules/{id}/toggle', [QualityInspectionController::class, 'toggleRule']);

    Route::patch('inspection-results/{id}/review', [QualityInspectionController::class, 'reviewResult']);
    Route::patch('inspection-alerts/{id}/handle', [QualityInspectionController::class, 'handleAlert']);
});
