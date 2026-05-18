<?php declare(strict_types=1);

use Kennofizet\RepoLocal\Services\GitWorkspaceService;

require dirname(__DIR__) . '/vendor/autoload.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$workspaceRoot = getenv('REPO_LOCAL_WORKSPACE_ROOT') ?: dirname(__DIR__, 2);
$scanDepth = (int) (getenv('REPO_LOCAL_SCAN_DEPTH') ?: 4);
$service = new GitWorkspaceService();

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$uri = rtrim($uri, '/') ?: '/';
$base = '/api/repo-local';
if (!str_starts_with($uri, $base)) {
    if ($uri === '/' || $uri === '/index.html') {
        $demo = dirname(__DIR__) . '/demo/dist/index.html';
        if (is_file($demo)) {
            header('Content-Type: text/html; charset=utf-8');
            readfile($demo);
            exit;
        }
    }
    respond(['success' => false, 'message' => 'Not found'], 404);
}

$path = substr($uri, strlen($base)) ?: '/';
$segments = array_values(array_filter(explode('/', $path)));

try {
    $payload = route($service, $workspaceRoot, $scanDepth, $segments, $_GET);
    respond(['success' => true, 'data' => $payload]);
} catch (InvalidArgumentException $e) {
    respond(['success' => false, 'message' => $e->getMessage()], 404);
} catch (Throwable $e) {
    respond(['success' => false, 'message' => $e->getMessage()], 500);
}

/**
 * @param array<string, mixed> $query
 */
function route(GitWorkspaceService $service, string $root, int $depth, array $segments, array $query): array
{
    if ($segments === ['workspace']) {
        return $service->workspaceSummary($root, $depth);
    }

    if (count($segments) >= 2 && $segments[0] === 'projects') {
        $projectId = $segments[1];
        $projectPath = $service->decodeProjectId($projectId);

        if (count($segments) === 2) {
            return $service->projectMeta($root, $projectPath);
        }

        $action = $segments[2] ?? '';
        if ($action === 'tree') {
            return $service->listTree($root, $projectPath, (string) ($query['path'] ?? ''));
        }
        if ($action === 'file') {
            $filePath = (string) ($query['path'] ?? '');
            if ($filePath === '') {
                throw new InvalidArgumentException('File path is required.');
            }

            return $service->readFile($root, $projectPath, $filePath);
        }
        if ($action === 'commits') {
            if (isset($segments[3])) {
                return $service->commitDetail($root, $projectPath, $segments[3]);
            }

            return $service->listCommits($root, $projectPath, (int) ($query['limit'] ?? 50));
        }
        if ($action === 'changes') {
            return $service->workingChanges($root, $projectPath);
        }
        if ($action === 'diff') {
            $mode = (string) ($query['mode'] ?? 'unstaged');
            if (!in_array($mode, ['unstaged', 'staged', 'commit'], true)) {
                throw new InvalidArgumentException('Invalid diff mode.');
            }

            return $service->diff(
                $root,
                $projectPath,
                $mode,
                isset($query['path']) ? (string) $query['path'] : null,
                isset($query['sha']) ? (string) $query['sha'] : null,
            );
        }
    }

    throw new InvalidArgumentException('Not found');
}

/**
 * @param array<string, mixed> $body
 */
function respond(array $body, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
