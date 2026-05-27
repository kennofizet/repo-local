<?php declare(strict_types=1);

namespace Kennofizet\RepoLocal;

use Kennofizet\RepoLocal\Services\GitCommandRunner;
use Kennofizet\RepoLocal\Services\GitWorkspaceService;
use Kennofizet\RepoLocal\Services\LaravelTinkerRunner;
use Illuminate\Support\ServiceProvider;

class RepoLocalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/Config/repo-local.php', 'repo-local');
        $this->app->singleton(GitCommandRunner::class);
        $this->app->singleton(GitWorkspaceService::class);
        $this->app->singleton(LaravelTinkerRunner::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/Config/repo-local.php' => config_path('repo-local.php'),
        ], 'repo-local-config');

        $this->loadRoutesFrom(__DIR__ . '/Routes/api.php');
    }
}
