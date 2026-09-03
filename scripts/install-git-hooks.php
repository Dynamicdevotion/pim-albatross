<?php

/**
 * Installs this project's versioned Git hooks into .git/hooks, so every
 * clone gets them without a manual step. Run automatically after every
 * `composer install`/`composer update` (wired into composer.json's
 * post-autoload-dump) — see README.md for what the hooks do.
 *
 * Never fails the surrounding `composer install`: anything that stops this
 * script short of installing (no .git, no source file, an existing custom
 * hook we don't recognise) is reported and skipped, not fatal.
 */

const HOOK_MARKER = '# pim-project-git-hooks:';

const HOOKS = [
    'pre-commit' => __DIR__.'/git-hooks/pre-commit',
];

function findGitHooksDir(string $repoRoot): ?string
{
    // Resolves worktrees/submodules correctly (their .git is a file
    // pointing elsewhere), not just the common case of a plain .git/ dir.
    $gitDir = trim((string) @shell_exec('git -C '.escapeshellarg($repoRoot).' rev-parse --git-dir 2>/dev/null'));

    if ($gitDir === '') {
        return null;
    }

    if (! str_starts_with($gitDir, '/') && ! preg_match('/^[A-Za-z]:[\\\\\/]/', $gitDir)) {
        $gitDir = $repoRoot.DIRECTORY_SEPARATOR.$gitDir;
    }

    $hooksDir = $gitDir.DIRECTORY_SEPARATOR.'hooks';

    return is_dir($hooksDir) ? $hooksDir : null;
}

function isOurHook(string $path): bool
{
    if (! file_exists($path)) {
        return true; // nothing there yet -> safe to install
    }

    $firstLines = @file_get_contents($path, false, null, 0, 200) ?: '';

    return str_contains($firstLines, HOOK_MARKER);
}

function installHooks(): void
{
    $repoRoot = dirname(__DIR__);
    $hooksDir = findGitHooksDir($repoRoot);

    if ($hooksDir === null) {
        fwrite(STDERR, "git-hooks: nessuna cartella .git/hooks trovata, salto l'installazione (non è un checkout Git?).\n");

        return;
    }

    foreach (HOOKS as $name => $source) {
        $target = $hooksDir.DIRECTORY_SEPARATOR.$name;

        if (! file_exists($source)) {
            fwrite(STDERR, "git-hooks: sorgente mancante per '{$name}' ({$source}), salto.\n");

            continue;
        }

        if (! isOurHook($target)) {
            fwrite(STDERR, "git-hooks: '{$name}' esiste già e non sembra installato da questo progetto — lasciato invariato.\n");

            continue;
        }

        if (! @copy($source, $target)) {
            fwrite(STDERR, "git-hooks: impossibile scrivere {$target}.\n");

            continue;
        }

        @chmod($target, 0755);
        echo "git-hooks: hook '{$name}' installato.\n";
    }
}

installHooks();
