<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Workspace root
    |--------------------------------------------------------------------------
    | Directory whose projects appear in the sidebar (e.g. packages-builder).
    */
    'workspace_root' => env('REPO_LOCAL_WORKSPACE_ROOT', dirname(__DIR__, 5)),

    /*
    |--------------------------------------------------------------------------
    | Scan depth
    |--------------------------------------------------------------------------
    | How deep to search for nested git repositories under the workspace root.
    */
    'scan_depth' => (int) env('REPO_LOCAL_SCAN_DEPTH', 4),

    /*
    |--------------------------------------------------------------------------
    | API route prefix (relative to /api)
    |--------------------------------------------------------------------------
    */
    'api_prefix' => env('REPO_LOCAL_API_PREFIX', 'repo-local'),

    /*
    |--------------------------------------------------------------------------
    | Local-only mode
    |--------------------------------------------------------------------------
    | When true, API is only reachable from localhost (for local dev tools).
    */
    'local_only' => (bool) env('REPO_LOCAL_LOCAL_ONLY', true),
];
