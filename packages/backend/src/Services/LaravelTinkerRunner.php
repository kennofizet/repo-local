<?php declare(strict_types=1);

namespace Kennofizet\RepoLocal\Services;

use InvalidArgumentException;
use Kennofizet\RepoLocal\Support\PathGuard;
use RuntimeException;
use Symfony\Component\Process\Process;

final class LaravelTinkerRunner
{
    private const MAX_CODE_BYTES = 48_000;

    private const TIMEOUT_SECONDS = 90;

    /**
     * @return array{
     *   output: string,
     *   result: mixed,
     *   result_text?: string,
     *   result_type?: string,
     *   auth_user_id?: int|null,
     *   error: array<string, mixed>|null,
     *   duration_ms: int,
     *   exit_code: int|null,
     *   stderr?: string
     * }
     */
    public function run(string $workspaceRoot, string $projectRelativePath, string $code, ?int $userId = null): array
    {
        $code = trim($code);
        if ($code === '') {
            throw new InvalidArgumentException('Code is required.');
        }

        if (strlen($code) > self::MAX_CODE_BYTES) {
            throw new InvalidArgumentException('Code exceeds maximum length.');
        }

        $projectRoot = PathGuard::resolveInside($workspaceRoot, $projectRelativePath);
        if (!is_file($projectRoot . DIRECTORY_SEPARATOR . 'artisan')) {
            throw new RuntimeException('Project is not a Laravel app (artisan missing).');
        }

        $script = $this->isolateScriptPath();
        $payload = base64_encode(json_encode([
            'code' => $code,
            'user_id' => $userId !== null && $userId > 0 ? $userId : null,
        ], JSON_THROW_ON_ERROR));

        $started = hrtime(true);
        $process = new Process(
            [PHP_BINARY, $script, $projectRoot, $payload],
            $projectRoot,
            null,
            null,
            self::TIMEOUT_SECONDS,
        );
        $process->run();

        $durationMs = (int) ((hrtime(true) - $started) / 1_000_000);
        $stdout = trim($process->getOutput());
        $stderr = trim($process->getErrorOutput());

        if ($stdout === '') {
            throw new RuntimeException($stderr !== '' ? $stderr : 'Tinker runner produced no output.');
        }

        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new RuntimeException(
                'Invalid runner response: ' . $e->getMessage() . ($stderr !== '' ? ' — ' . $stderr : ''),
            );
        }

        $payload = [
            'output' => (string) ($decoded['output'] ?? ''),
            'result' => $decoded['result'] ?? null,
            'error' => isset($decoded['error']) && is_array($decoded['error']) ? $decoded['error'] : null,
            'duration_ms' => $durationMs,
            'exit_code' => $process->getExitCode(),
        ];

        if (isset($decoded['result_text']) && is_string($decoded['result_text'])) {
            $payload['result_text'] = $decoded['result_text'];
        }
        if (isset($decoded['result_type']) && is_string($decoded['result_type'])) {
            $payload['result_type'] = $decoded['result_type'];
        }
        if (array_key_exists('auth_user_id', $decoded)) {
            $payload['auth_user_id'] = is_numeric($decoded['auth_user_id'] ?? null)
                ? (int) $decoded['auth_user_id']
                : null;
        }
        if ($stderr !== '') {
            $payload['stderr'] = $stderr;
        }

        return $payload;
    }

    private function isolateScriptPath(): string
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'tinker-isolate.php';
        if (!is_file($path)) {
            throw new RuntimeException('Tinker isolate script is missing.');
        }

        return $path;
    }
}
