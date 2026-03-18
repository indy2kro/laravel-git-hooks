<?php

declare(strict_types=1);

use Igorsgm\GitHooks\GitHooks;

beforeEach(function () {
    $this->initializeGitAsTempDirectory();
    $this->gitHooks = new GitHooks();
});

afterEach(function () {
    $this->deleteTempDirectory();
});

test('getGitHooksDir returns .git/hooks path in a regular repo', function () {
    chdir(base_path());

    $result = $this->gitHooks->getGitHooksDir();

    expect($result)->toEndWith(DIRECTORY_SEPARATOR.'hooks');
    expect($result)->toContain('.git');
});

test('getGitHooksDir returns the common hooks dir in a git worktree', function () {
    $mainRepoPath = base_path();
    chdir($mainRepoPath);

    // Create an initial commit so we can create a worktree
    shell_exec('git -C '.escapeshellarg($mainRepoPath).' commit --allow-empty -m "init" 2>/dev/null');

    // Create a worktree outside the main repo to avoid ambiguity
    $worktreePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'worktree-test-'.uniqid();
    shell_exec('git -C '.escapeshellarg($mainRepoPath).' worktree add '.escapeshellarg($worktreePath).' -b test-worktree 2>/dev/null');

    // Point base_path() at the worktree
    $this->app->setBasePath($worktreePath);
    chdir($worktreePath);

    $result = $this->gitHooks->getGitHooksDir();

    // Should resolve to the main repo's .git/hooks, NOT the worktree's .git file
    $expectedCommonHooksDir = $mainRepoPath.DIRECTORY_SEPARATOR.'.git'.DIRECTORY_SEPARATOR.'hooks';
    expect($result)->toBe($expectedCommonHooksDir);

    // Clean up worktree
    $this->app->setBasePath($mainRepoPath);
    chdir($mainRepoPath);
    shell_exec('git -C '.escapeshellarg($mainRepoPath).' worktree remove '.escapeshellarg($worktreePath).' 2>/dev/null');
});

test('getGitHooksDir falls back to base_path .git/hooks when git command fails', function () {
    // Use a directory that is not a git repo
    $nonGitDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'non-git-'.uniqid();
    mkdir($nonGitDir, 0755, true);

    $this->app->setBasePath($nonGitDir);

    $result = $this->gitHooks->getGitHooksDir();

    expect($result)->toBe($nonGitDir.DIRECTORY_SEPARATOR.'.git'.DIRECTORY_SEPARATOR.'hooks');

    // Clean up
    rmdir($nonGitDir);
    $this->app->setBasePath(base_path());
});
