# Repo Local

Local **GitHub-style** workspace browser for `packages-builder`: project list from the workspace root, file tree, file viewer, commit history, working-tree changes, and unified diffs.

## Structure

| Path | Role |
|------|------|
| `packages/backend` | Laravel package — git/workspace API |
| `packages/frontend` | Vue 3 UI (`RepoLocalApp`) |
| `public/index.php` | Standalone PHP API (no full Laravel app required for demo) |
| `demo` | Vite dev app |

## Quick start (demo)

**1. API** (port 8090). Default workspace = parent of `repo-local`. To browse a different folder (e.g. `C:\laragon\www`), set `REPO_LOCAL_WORKSPACE_ROOT` in the **same shell** that runs `php -S`.

PowerShell (Windows):

```powershell
cd repo-local
composer install
$env:REPO_LOCAL_WORKSPACE_ROOT = "C:\laragon\www"
php -S 127.0.0.1:8090 -t public public/index.php
```

> In PowerShell, `set FOO=BAR` does **not** create an environment variable (it's an alias for `Set-Variable`). Use `$env:FOO = "BAR"` so PHP's `getenv()` can see it.

cmd.exe (Windows):

```bat
cd repo-local
composer install
set REPO_LOCAL_WORKSPACE_ROOT=C:\laragon\www
php -S 127.0.0.1:8090 -t public public/index.php
```

bash / zsh (macOS, Linux, Git Bash):

```bash
cd repo-local
composer install
export REPO_LOCAL_WORKSPACE_ROOT=/path/to/workspace
php -S 127.0.0.1:8090 -t public public/index.php
```

Verify: open `http://127.0.0.1:8090/api/repo-local/workspace` — the `root` field should match the path you set.

**2. UI** (port 5178, proxies API):

```bash
cd demo
npm install
npm run dev
```

Open **http://127.0.0.1:5178** (not port 80). If that fails, use **http://localhost:5178**.

> You need **both** terminals running: PHP API on **8090** and Vite on **5178**. Opening only `http://127.0.0.1` (port 80) will show “connection refused”.

## Laravel integration

```bash
composer require kennofizet/repo-local-backend
php artisan vendor:publish --tag=repo-local-config
```

`.env`:

```env
REPO_LOCAL_WORKSPACE_ROOT=c:\laragon\www\packages-builder
REPO_LOCAL_SCAN_DEPTH=4
REPO_LOCAL_LOCAL_ONLY=true
```

API base: `/api/repo-local` (see `config/repo-local.php`).

Frontend:

```js
import { installRepoLocalModule } from '@kennofizet/repo-local-frontend'

installRepoLocalModule(app, {
  apiBaseUrl: '/api/repo-local',
  routerBase: '/repo-local',
})
```

```vue
<router-view />
```

## API overview

| Method | Path | Description |
|--------|------|-------------|
| GET | `/workspace` | Root path + discovered projects |
| GET | `/projects/{id}` | Project meta (branch, git flag) |
| GET | `/projects/{id}/tree?path=` | Directory listing + git status hints |
| GET | `/projects/{id}/file?path=` | File contents (max 1 MB, text only) |
| GET | `/projects/{id}/commits` | Recent commits |
| GET | `/projects/{id}/commits/{sha}` | Commit + changed files |
| GET | `/projects/{id}/changes` | `git status` porcelain |
| GET | `/projects/{id}/diff?mode=&path=&sha=` | `unstaged`, `staged`, or `commit` diff |

`{id}` is a URL-safe base64 project path relative to the workspace root.

## Security

- Paths are resolved with `PathGuard` so requests cannot escape the workspace root.
- Laravel routes use `LocalhostOnly` middleware when `REPO_LOCAL_LOCAL_ONLY=true`.
- Intended for **local development only** — do not expose on a public host without hardening.
