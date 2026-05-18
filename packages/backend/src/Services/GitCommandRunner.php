<?php declare(strict_types=1);

namespace Kennofizet\RepoLocal\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

final class GitCommandRunner
{
    public function run(string $repoPath, array $args, ?string $stdin = null): string
    {
        $command = array_merge(['git', '-C', $repoPath], $args);
        $process = new Process($command);
        $process->setTimeout(120);

        if ($stdin !== null) {
            $process->setInput($stdin);
        }

        $process->run();

        if (!$process->isSuccessful()) {
            $message = trim($process->getErrorOutput() ?: $process->getOutput());
            throw new RuntimeException($message !== '' ? $message : 'Git command failed.');
        }

        return $process->getOutput();
    }

    public function tryRun(string $repoPath, array $args): ?string
    {
        try {
            return $this->run($repoPath, $args);
        } catch (RuntimeException) {
            return null;
        }
    }

    public function isGitRepository(string $path): bool
    {
        return is_dir($path . DIRECTORY_SEPARATOR . '.git')
            || is_file($path . DIRECTORY_SEPARATOR . '.git');
    }
}
