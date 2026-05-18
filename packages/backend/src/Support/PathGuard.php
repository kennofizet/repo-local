<?php declare(strict_types=1);

namespace Kennofizet\RepoLocal\Support;

use InvalidArgumentException;

final class PathGuard
{
    public static function workspaceRoot(string $configuredRoot): string
    {
        $resolved = realpath($configuredRoot);
        if ($resolved === false || !is_dir($resolved)) {
            throw new InvalidArgumentException('Workspace root is not a valid directory.');
        }

        return self::normalize($resolved);
    }

    public static function resolveInside(string $workspaceRoot, string $relativePath = ''): string
    {
        $root = self::workspaceRoot($workspaceRoot);
        $relativePath = trim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $relativePath), DIRECTORY_SEPARATOR);

        if ($relativePath === '' || $relativePath === '.') {
            return $root;
        }

        $candidate = $root . DIRECTORY_SEPARATOR . $relativePath;
        $resolved = realpath($candidate);

        if ($resolved === false) {
            $parent = realpath(dirname($candidate));
            if ($parent === false || !self::isInside($parent, $root)) {
                throw new InvalidArgumentException('Path escapes workspace root.');
            }

            return self::normalize($candidate);
        }

        if (!self::isInside($resolved, $root)) {
            throw new InvalidArgumentException('Path escapes workspace root.');
        }

        return self::normalize($resolved);
    }

    public static function relativePath(string $workspaceRoot, string $absolutePath): string
    {
        $root = self::workspaceRoot($workspaceRoot);
        $absolute = self::normalize(realpath($absolutePath) ?: $absolutePath);

        if (!self::isInside($absolute, $root)) {
            throw new InvalidArgumentException('Path escapes workspace root.');
        }

        if ($absolute === $root) {
            return '';
        }

        return ltrim(substr($absolute, strlen($root)), DIRECTORY_SEPARATOR);
    }

    public static function isInside(string $path, string $root): bool
    {
        $path = self::normalize($path);
        $root = self::normalize($root);

        if ($path === $root) {
            return true;
        }

        return str_starts_with($path, $root . DIRECTORY_SEPARATOR);
    }

    private static function normalize(string $path): string
    {
        return rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
    }
}
