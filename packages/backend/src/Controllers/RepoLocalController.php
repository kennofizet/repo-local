<?php declare(strict_types=1);

namespace Kennofizet\RepoLocal\Controllers;

use Kennofizet\RepoLocal\Services\GitWorkspaceService;
use Kennofizet\RepoLocal\Services\LaravelTinkerRunner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;

class RepoLocalController
{
    public function __construct(
        private readonly GitWorkspaceService $workspace,
        private readonly LaravelTinkerRunner $tinker,
    ) {
    }

    public function workspace(): JsonResponse
    {
        return $this->ok($this->workspace->workspaceSummary($this->root(), $this->depth()));
    }

    public function project(string $projectId): JsonResponse
    {
        try {
            $path = $this->workspace->decodeProjectId($projectId);

            return $this->ok($this->workspace->projectMeta($this->root(), $path));
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 404);
        }
    }

    public function tree(Request $request, string $projectId): JsonResponse
    {
        try {
            $path = $this->workspace->decodeProjectId($projectId);
            $treePath = (string) $request->query('path', '');

            return $this->ok($this->workspace->listTree($this->root(), $path, $treePath));
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 404);
        }
    }

    public function file(Request $request, string $projectId): JsonResponse
    {
        try {
            $projectPath = $this->workspace->decodeProjectId($projectId);
            $filePath = (string) $request->query('path', '');
            if ($filePath === '') {
                return $this->error('File path is required.', 422);
            }

            return $this->ok($this->workspace->readFile($this->root(), $projectPath, $filePath));
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 404);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    public function commits(Request $request, string $projectId): JsonResponse
    {
        try {
            $path = $this->workspace->decodeProjectId($projectId);
            $limit = (int) $request->query('limit', 50);

            return $this->ok($this->workspace->listCommits($this->root(), $path, $limit));
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 404);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    public function commit(string $projectId, string $sha): JsonResponse
    {
        try {
            $path = $this->workspace->decodeProjectId($projectId);

            return $this->ok($this->workspace->commitDetail($this->root(), $path, $sha));
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 404);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    public function changes(string $projectId): JsonResponse
    {
        try {
            $path = $this->workspace->decodeProjectId($projectId);

            return $this->ok($this->workspace->workingChanges($this->root(), $path));
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 404);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    public function diff(Request $request, string $projectId): JsonResponse
    {
        try {
            $path = $this->workspace->decodeProjectId($projectId);
            $mode = (string) $request->query('mode', 'unstaged');
            $filePath = $request->query('path');
            $sha = $request->query('sha');

            if (!in_array($mode, ['unstaged', 'staged', 'commit'], true)) {
                return $this->error('Invalid diff mode.', 422);
            }

            return $this->ok($this->workspace->diff(
                $this->root(),
                $path,
                $mode,
                is_string($filePath) ? $filePath : null,
                is_string($sha) ? $sha : null,
            ));
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 404);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    public function tinkerRun(Request $request, string $projectId): JsonResponse
    {
        try {
            $path = $this->workspace->decodeProjectId($projectId);
            $code = trim((string) $request->input('code', ''));
            if ($code === '') {
                return $this->error('Code is required.', 422);
            }

            $userId = $request->input('user_id');
            $userId = is_numeric($userId) ? (int) $userId : null;

            return $this->ok($this->tinker->run($this->root(), $path, $code, $userId));
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 404);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    private function root(): string
    {
        return (string) config('repo-local.workspace_root');
    }

    private function depth(): int
    {
        return (int) config('repo-local.scan_depth', 4);
    }

    private function ok(array $data, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
        ], $status);
    }

    private function error(string $message, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }
}
