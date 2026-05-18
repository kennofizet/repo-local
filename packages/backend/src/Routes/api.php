<?php declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Kennofizet\RepoLocal\Controllers\RepoLocalController;
use Kennofizet\RepoLocal\Middleware\LocalhostOnly;

$prefix = config('repo-local.api_prefix', 'repo-local');

Route::prefix('api/' . $prefix)
    ->middleware(['api', LocalhostOnly::class])
    ->group(function () {
        Route::get('workspace', [RepoLocalController::class, 'workspace']);
        Route::get('projects/{projectId}', [RepoLocalController::class, 'project']);
        Route::get('projects/{projectId}/tree', [RepoLocalController::class, 'tree']);
        Route::get('projects/{projectId}/file', [RepoLocalController::class, 'file']);
        Route::get('projects/{projectId}/commits', [RepoLocalController::class, 'commits']);
        Route::get('projects/{projectId}/commits/{sha}', [RepoLocalController::class, 'commit']);
        Route::get('projects/{projectId}/changes', [RepoLocalController::class, 'changes']);
        Route::get('projects/{projectId}/diff', [RepoLocalController::class, 'diff']);
    });
