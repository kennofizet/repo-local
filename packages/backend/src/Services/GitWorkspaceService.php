<?php declare(strict_types=1);

namespace Kennofizet\RepoLocal\Services;

use Kennofizet\RepoLocal\Support\PathGuard;
use InvalidArgumentException;
use RuntimeException;

final class GitWorkspaceService
{
    public function __construct(
        private readonly GitCommandRunner $git = new GitCommandRunner(),
    ) {
    }

    public function workspaceSummary(string $workspaceRoot, int $maxDepth = 4): array
    {
        $root = PathGuard::workspaceRoot($workspaceRoot);

        return [
            'root' => $root,
            'root_name' => basename($root),
            'projects' => $this->discoverProjects($root, $maxDepth),
        ];
    }

    /**
     * @return list<array{id: string, name: string, path: string, branch: ?string, is_git: bool, has_changes: ?bool}>
     */
    public function discoverProjects(string $workspaceRoot, int $maxDepth = 4): array
    {
        $root = PathGuard::workspaceRoot($workspaceRoot);
        $found = [];
        $this->walkForGitRepos($root, $root, $maxDepth, 0, $found);

        usort($found, fn (array $a, array $b) => strcmp($a['path'], $b['path']));

        return array_values($found);
    }

    public function projectMeta(string $workspaceRoot, string $projectPath): array
    {
        $repoPath = PathGuard::resolveInside($workspaceRoot, $projectPath);
        $isGit = $this->git->isGitRepository($repoPath);

        return [
            'id' => $this->encodeProjectId($projectPath),
            'name' => basename($repoPath),
            'path' => PathGuard::relativePath($workspaceRoot, $repoPath),
            'branch' => $isGit ? $this->currentBranch($repoPath) : null,
            'is_git' => $isGit,
        ];
    }

    public function listTree(string $workspaceRoot, string $projectPath, string $relativePath = ''): array
    {
        $repoPath = PathGuard::resolveInside($workspaceRoot, $projectPath);
        $targetPath = $relativePath === ''
            ? $repoPath
            : PathGuard::resolveInside($repoPath, $relativePath);

        if (!is_dir($targetPath)) {
            throw new InvalidArgumentException('Path is not a directory.');
        }

        $statusMap = $this->git->isGitRepository($repoPath)
            ? $this->statusMap($repoPath)
            : [];

        $entries = [];
        foreach (scandir($targetPath) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === '.git') {
                continue;
            }

            $full = $targetPath . DIRECTORY_SEPARATOR . $entry;
            $entryRelative = PathGuard::relativePath($repoPath, $full);
            $entries[] = [
                'name' => $entry,
                'path' => str_replace('\\', '/', $entryRelative),
                'type' => is_dir($full) ? 'dir' : 'file',
                'size' => is_file($full) ? filesize($full) : null,
                'git_status' => $statusMap[str_replace('\\', '/', $entryRelative)] ?? null,
            ];
        }

        usort($entries, function (array $a, array $b) {
            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'dir' ? -1 : 1;
            }

            return strcasecmp($a['name'], $b['name']);
        });

        return [
            'path' => str_replace('\\', '/', PathGuard::relativePath($repoPath, $targetPath)),
            'entries' => $entries,
        ];
    }

    public function readFile(string $workspaceRoot, string $projectPath, string $filePath): array
    {
        $repoPath = PathGuard::resolveInside($workspaceRoot, $projectPath);
        $absolute = PathGuard::resolveInside($repoPath, $filePath);

        if (!is_file($absolute)) {
            throw new InvalidArgumentException('File not found.');
        }

        $size = filesize($absolute) ?: 0;
        $maxBytes = 1024 * 1024;
        $binary = $this->looksBinary($absolute);

        if ($binary || $size > $maxBytes) {
            return [
                'path' => str_replace('\\', '/', PathGuard::relativePath($repoPath, $absolute)),
                'name' => basename($absolute),
                'size' => $size,
                'binary' => true,
                'content' => null,
                'message' => $binary
                    ? 'Binary file cannot be displayed.'
                    : 'File is too large to display (max 1 MB).',
            ];
        }

        $content = file_get_contents($absolute);
        if ($content === false) {
            throw new RuntimeException('Unable to read file.');
        }

        return [
            'path' => str_replace('\\', '/', PathGuard::relativePath($repoPath, $absolute)),
            'name' => basename($absolute),
            'size' => $size,
            'binary' => false,
            'content' => $content,
            'encoding' => 'utf-8',
        ];
    }

    public function listCommits(string $workspaceRoot, string $projectPath, int $limit = 50): array
    {
        $repoPath = $this->requireGit($workspaceRoot, $projectPath);
        if ($this->git->tryRun($repoPath, ['rev-parse', '--verify', 'HEAD']) === null) {
            return ['commits' => []];
        }

        $output = $this->git->run($repoPath, [
            'log',
            '--format=%H%x1f%an%x1f%ae%x1f%at%x1f%s',
            '-n',
            (string) max(1, min($limit, 200)),
        ]);

        $commits = [];
        foreach (array_filter(explode("\n", trim($output))) as $line) {
            $parts = explode("\x1f", $line, 5);
            if (count($parts) < 5) {
                continue;
            }
            $commits[] = [
                'sha' => $parts[0],
                'short_sha' => substr($parts[0], 0, 7),
                'author' => $parts[1],
                'email' => $parts[2],
                'date' => (int) $parts[3],
                'message' => $parts[4],
            ];
        }

        return ['commits' => $commits];
    }

    public function commitDetail(string $workspaceRoot, string $projectPath, string $sha): array
    {
        $repoPath = $this->requireGit($workspaceRoot, $projectPath);
        $sha = trim($sha);
        if ($sha === '') {
            throw new InvalidArgumentException('Commit SHA is required.');
        }

        $this->assertCommitExists($repoPath, $sha);

        $header = trim($this->git->run($repoPath, [
            'show',
            '-s',
            '--format=%H%x1f%an%x1f%ae%x1f%at%x1f%s',
            $sha,
        ]));
        $parts = explode("\x1f", $header, 5);
        if (count($parts) < 5) {
            throw new InvalidArgumentException('Commit not found.');
        }

        $nameStatus = trim($this->git->run($repoPath, ['show', '--name-status', '--format=', $sha]));
        $files = [];
        foreach (array_filter(explode("\n", $nameStatus)) as $line) {
            $segments = preg_split('/\s+/', $line, 3);
            if (!$segments || count($segments) < 2) {
                continue;
            }
            $files[] = [
                'status' => $segments[0],
                'path' => $segments[count($segments) - 1],
            ];
        }

        return [
            'commit' => [
                'sha' => $parts[0],
                'short_sha' => substr($parts[0], 0, 7),
                'author' => $parts[1],
                'email' => $parts[2],
                'date' => (int) $parts[3],
                'message' => $parts[4],
            ],
            'files' => $files,
        ];
    }

    public function workingChanges(string $workspaceRoot, string $projectPath): array
    {
        $repoPath = $this->requireGit($workspaceRoot, $projectPath);
        $branch = $this->currentBranch($repoPath);
        $porcelain = rtrim($this->git->run($repoPath, ['status', '--porcelain', '-u']), "\r\n");
        $files = [];

        foreach (array_filter(explode("\n", $porcelain), static fn (string $l) => $l !== '') as $line) {
            $line = rtrim($line, "\r");
            if (strlen($line) < 4) {
                continue;
            }
            $indexStatus = $line[0];
            $worktreeStatus = $line[1];
            $path = substr($line, 3);
            if (str_contains($path, ' -> ')) {
                $path = trim(substr($path, strrpos($path, ' -> ') + 4));
            }

            $files[] = [
                'path' => str_replace('\\', '/', $path),
                'index_status' => $indexStatus === ' ' ? null : $indexStatus,
                'worktree_status' => $worktreeStatus === ' ' ? null : $worktreeStatus,
                'staged' => $indexStatus !== ' ' && $indexStatus !== '?',
                'unstaged' => $worktreeStatus !== ' ' && $worktreeStatus !== '?',
                'untracked' => $indexStatus === '?' && $worktreeStatus === '?',
            ];
        }

        return [
            'branch' => $branch,
            'files' => $files,
            'counts' => [
                'total' => count($files),
                'staged' => count(array_filter($files, fn (array $f) => $f['staged'])),
                'unstaged' => count(array_filter($files, fn (array $f) => $f['unstaged'] || $f['untracked'])),
            ],
        ];
    }

    public function diff(
        string $workspaceRoot,
        string $projectPath,
        string $mode,
        ?string $path = null,
        ?string $sha = null,
    ): array {
        $repoPath = $this->requireGit($workspaceRoot, $projectPath);

        if ($mode === 'commit') {
            if ($sha === null || trim($sha) === '') {
                throw new InvalidArgumentException('Commit SHA is required for commit diff.');
            }
            $sha = trim($sha);
            $this->assertCommitExists($repoPath, $sha);
            $args = ['show', '--no-color', '--format=', $sha];
        } elseif ($mode === 'staged') {
            $args = ['diff', '--no-color', '--cached'];
        } else {
            $args = ['diff', '--no-color'];
        }

        if ($path !== null && $path !== '') {
            $args[] = '--';
            $args[] = $path;
        }

        $patch = $this->git->run($repoPath, $args);

        return [
            'mode' => $mode,
            'path' => $path,
            'sha' => $sha,
            'patch' => $patch,
            'stats' => $this->parseDiffStats($patch),
        ];
    }

    public function decodeProjectId(string $id): string
    {
        $decoded = base64_decode(strtr($id, '-_', '+/'), true);
        if ($decoded === false) {
            throw new InvalidArgumentException('Invalid project id.');
        }

        return $decoded;
    }

    public function encodeProjectId(string $projectPath): string
    {
        return rtrim(strtr(base64_encode($projectPath), '+/', '-_'), '=');
    }

    private function assertCommitExists(string $repoPath, string $sha): void
    {
        if ($this->git->tryRun($repoPath, ['rev-parse', '--verify', $sha . '^{commit}']) === null) {
            throw new InvalidArgumentException('Commit not found in this repository.');
        }
    }

    private function requireGit(string $workspaceRoot, string $projectPath): string
    {
        $repoPath = PathGuard::resolveInside($workspaceRoot, $projectPath);
        if (!$this->git->isGitRepository($repoPath)) {
            throw new InvalidArgumentException('Project is not a git repository.');
        }

        return $repoPath;
    }

    private function currentBranch(string $repoPath): ?string
    {
        $branch = $this->git->tryRun($repoPath, ['rev-parse', '--abbrev-ref', 'HEAD']);

        return $branch !== null ? trim($branch) : null;
    }

    private function hasWorkingChanges(string $repoPath): ?bool
    {
        $output = $this->git->tryRun($repoPath, ['status', '--porcelain', '-u']);
        if ($output === null) {
            return null;
        }

        return trim($output) !== '';
    }

    /**
     * @param array<string, array{id: string, name: string, path: string, branch: ?string, is_git: bool, has_changes: ?bool}> $found
     */
    private function walkForGitRepos(
        string $workspaceRoot,
        string $currentDir,
        int $maxDepth,
        int $depth,
        array &$found,
    ): void {
        if ($this->git->isGitRepository($currentDir)) {
            $relative = PathGuard::relativePath($workspaceRoot, $currentDir);
            $key = $relative === '' ? '.' : $relative;
            if (!isset($found[$key])) {
                $found[$key] = [
                    'id' => $this->encodeProjectId(str_replace('\\', '/', $relative)),
                    'name' => basename($currentDir),
                    'path' => str_replace('\\', '/', $relative),
                    'branch' => $this->currentBranch($currentDir),
                    'is_git' => true,
                    'has_changes' => $this->hasWorkingChanges($currentDir),
                ];
            }

            return;
        }

        if ($depth >= $maxDepth) {
            return;
        }

        foreach (scandir($currentDir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === '.git' || $entry === 'node_modules' || $entry === 'vendor') {
                continue;
            }
            $child = $currentDir . DIRECTORY_SEPARATOR . $entry;
            if (!is_dir($child)) {
                continue;
            }
            $this->walkForGitRepos($workspaceRoot, $child, $maxDepth, $depth + 1, $found);
        }

        if ($depth === 0) {
            foreach (scandir($currentDir) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $child = $currentDir . DIRECTORY_SEPARATOR . $entry;
                if (!is_dir($child) || isset($found[PathGuard::relativePath($workspaceRoot, $child)])) {
                    continue;
                }
                $relative = PathGuard::relativePath($workspaceRoot, $child);
                $key = str_replace('\\', '/', $relative);
                if (!isset($found[$key])) {
                    $found[$key] = [
                        'id' => $this->encodeProjectId($relative),
                        'name' => basename($child),
                        'path' => $key,
                        'branch' => null,
                        'is_git' => false,
                        'has_changes' => null,
                    ];
                }
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private function statusMap(string $repoPath): array
    {
        $map = [];
        $porcelain = rtrim($this->git->tryRun($repoPath, ['status', '--porcelain', '-u']) ?? '', "\r\n");
        foreach (array_filter(explode("\n", $porcelain), static fn (string $l) => $l !== '') as $line) {
            $line = rtrim($line, "\r");
            if (strlen($line) < 4) {
                continue;
            }
            $indexStatus = $line[0];
            $worktreeStatus = $line[1];
            $path = substr($line, 3);
            if (str_contains($path, ' -> ')) {
                $path = trim(substr($path, strrpos($path, ' -> ') + 4));
            }
            $code = $worktreeStatus !== ' ' ? $worktreeStatus : $indexStatus;
            $map[str_replace('\\', '/', $path)] = $code;
        }

        return $map;
    }

    private function looksBinary(string $absolutePath): bool
    {
        $handle = fopen($absolutePath, 'rb');
        if ($handle === false) {
            return true;
        }
        $chunk = fread($handle, 8192);
        fclose($handle);
        if ($chunk === false) {
            return true;
        }

        return str_contains($chunk, "\0");
    }

  /**
     * @return array{files: int, insertions: int, deletions: int}
     */
    private function parseDiffStats(string $patch): array
    {
        $files = 0;
        $insertions = 0;
        $deletions = 0;

        foreach (explode("\n", $patch) as $line) {
            if (str_starts_with($line, '+++ ') || str_starts_with($line, '--- ')) {
                continue;
            }
            if (str_starts_with($line, 'diff --git')) {
                $files++;
                continue;
            }
            if (str_starts_with($line, '+') && !str_starts_with($line, '+++')) {
                $insertions++;
            } elseif (str_starts_with($line, '-') && !str_starts_with($line, '---')) {
                $deletions++;
            }
        }

        return compact('files', 'insertions', 'deletions');
    }
}
