<?php declare(strict_types=1);

/**
 * Bootstraps a target Laravel app and runs sandboxed PHP (repo-local dev tool only).
 *
 * argv[1] project root (absolute)
 * argv[2] base64 JSON: { "code": string, "user_id": int|null }
 */

if ($argc < 3) {
    fwrite(STDERR, "Missing arguments.\n");
    exit(2);
}

$projectRoot = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $argv[1]), DIRECTORY_SEPARATOR);
$artisan = $projectRoot . DIRECTORY_SEPARATOR . 'artisan';

if (!is_file($artisan)) {
    fwrite(STDERR, "Not a Laravel project.\n");
    exit(3);
}

try {
    /** @var array{code?: string, user_id?: int|null} $payload */
    $payload = json_decode(base64_decode($argv[2], true) ?: '', true, 512, JSON_THROW_ON_ERROR);
} catch (Throwable) {
    fwrite(STDERR, "Invalid payload.\n");
    exit(4);
}

$userCode = trim((string) ($payload['code'] ?? ''));
if ($userCode === '') {
    fwrite(STDERR, "Empty code.\n");
    exit(5);
}

$userId = isset($payload['user_id']) && $payload['user_id'] !== null
    ? (int) $payload['user_id']
    : null;

require $projectRoot . '/vendor/autoload.php';
$app = require $projectRoot . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$wrapped = <<<PHP
return (function () {
{$userCode}
})();
PHP;

ob_start();
$error = null;
$result = null;
$authUserIdDuringRun = null;

$runBody = static function () use ($wrapped, &$authUserIdDuringRun) {
    $value = eval($wrapped);

    if (function_exists('currentUserId')) {
        try {
            $authUserIdDuringRun = currentUserId();
        } catch (Throwable) {
            $authUserIdDuringRun = null;
        }
    } elseif (Illuminate\Support\Facades\Auth::check()) {
        $authUserIdDuringRun = Illuminate\Support\Facades\Auth::id();
    }

    return $value;
};

try {
    if ($userId !== null && $userId > 0) {
        $userModel = config('auth.providers.users.model');
        if (!is_string($userModel) || !class_exists($userModel)) {
            throw new RuntimeException('Auth user model is not configured.');
        }

        $query = method_exists($userModel, 'queryForSystemJob')
            ? $userModel::queryForSystemJob()
            : $userModel::withoutGlobalScopes();

        $user = $query->find($userId);
        if ($user === null) {
            throw new RuntimeException('User not found: ' . $userId);
        }

        if (class_exists(\App\Core\StatefulGuardActor::class)) {
            $result = \App\Core\StatefulGuardActor::runWithUser($user, $runBody);
        } else {
            Illuminate\Support\Facades\Auth::login($user);
            try {
                $result = $runBody();
            } finally {
                Illuminate\Support\Facades\Auth::logout();
            }
        }
    } else {
        $result = $runBody();
    }
} catch (Throwable $e) {
    $error = [
        'type' => $e::class,
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ];
}

$output = ob_get_clean() ?: '';

/**
 * @param mixed $value
 * @return mixed
 */
function rl_export_value(mixed $value): mixed
{
    if ($value === null || is_scalar($value)) {
        return $value;
    }

    if (is_array($value)) {
        $out = [];
        foreach ($value as $k => $v) {
            $out[$k] = rl_export_value($v);
        }

        return $out;
    }

    if (is_object($value)) {
        if ($value instanceof \Illuminate\Database\Eloquent\Model) {
            return rl_export_value(rl_model_snapshot($value));
        }
        if (method_exists($value, 'toArray')) {
            try {
                return rl_export_value($value->toArray());
            } catch (Throwable) {
                return rl_object_stub($value);
            }
        }
        if ($value instanceof JsonSerializable) {
            return rl_export_value($value->jsonSerialize());
        }

        return rl_object_stub($value);
    }

    return (string) $value;
}

/**
 * @return array<string, mixed>
 */
function rl_model_snapshot(\Illuminate\Database\Eloquent\Model $model): array
{
    $row = [
        '__class' => $model::class,
        'id' => $model->getKey(),
    ];

    foreach (['name', 'nickname', 'email', 'type', 'branch_id', 'created_by', 'slug'] as $attr) {
        if ($model->offsetExists($attr)) {
            $row[$attr] = $model->getAttribute($attr);
        }
    }

    return $row;
}

/**
 * @return array<string, mixed>
 */
function rl_object_stub(object $value): array
{
    return [
        '__class' => $value::class,
        '__id' => method_exists($value, 'getKey') ? $value->getKey() : null,
    ];
}

/**
 * @param mixed $value
 */
function rl_result_text(mixed $value): string
{
    if ($value === null) {
        return 'null';
    }

    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }

    if (is_scalar($value)) {
        return (string) $value;
    }

    $exported = rl_export_value($value);
    $json = json_encode($exported, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

    return $json !== false ? $json : '(unencodable result)';
}

$exportedResult = rl_export_value($result);
$payload = [
    'output' => $output,
    'result' => $exportedResult,
    'result_text' => rl_result_text($result),
    'result_type' => $result === null ? 'null' : get_debug_type($result),
    'auth_user_id' => $authUserIdDuringRun,
    'error' => $error,
];

$json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if ($json === false) {
    $json = json_encode([
        'output' => $output,
        'result' => null,
        'result_text' => 'JSON encode failed: ' . json_last_error_msg(),
        'result_type' => 'error',
        'auth_user_id' => null,
        'error' => $error,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{"error":"encode_failed"}';
}

echo $json;

exit($error !== null ? 1 : 0);
