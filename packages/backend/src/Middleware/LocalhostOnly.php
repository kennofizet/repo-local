<?php declare(strict_types=1);

namespace Kennofizet\RepoLocal\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

class LocalhostOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('repo-local.local_only', true)) {
            return $next($request);
        }

        $ip = $request->ip() ?? '';
        $allowed = ['127.0.0.1', '::1', 'localhost'];

        if (!IpUtils::checkIp($ip, $allowed)) {
            return response()->json([
                'success' => false,
                'message' => 'Repo Local API is restricted to localhost.',
            ], 403);
        }

        return $next($request);
    }
}
