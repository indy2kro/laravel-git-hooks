<?php

declare(strict_types=1);

use Igorsgm\GitHooks\Console\Commands\Hooks\LarastanPreCommitHook;
use Igorsgm\GitHooks\Facades\GitHooks;

$projectRoot = dirname(__DIR__, 3);
$larastanBin = $projectRoot.'/vendor/bin/phpstan';

beforeEach(function () {
    $this->gitInit();
    $this->initializeTempDirectory(base_path('temp'));
});

test('Larastan fails when staged PHP file has type errors', function ($larastanConfiguration) use ($projectRoot) {
    $this->config->set('git-hooks.code_analyzers.larastan', $larastanConfiguration);
    $this->config->set('git-hooks.pre-commit', [LarastanPreCommitHook::class]);

    $this->makeTempFile(
        'ClassWithFixableIssues.php',
        file_get_contents($projectRoot.'/tests/Fixtures/ClassWithFixableIssues.php')
    );

    GitHooks::shouldReceive('isMergeInProgress')->andReturn(false);
    GitHooks::shouldReceive('getListOfChangedFiles')->andReturn('AM temp/ClassWithFixableIssues.php');

    $this->artisan('git-hooks:pre-commit')
        ->expectsOutputToContain('Larastan Failed')
        ->expectsOutputToContain('COMMIT FAILED')
        ->assertExitCode(1);
})->with('larastanConfiguration')->skip(!file_exists($larastanBin), 'PHPStan/Larastan binary not found');

test('Larastan passes when staged PHP file has no type errors', function ($larastanConfiguration) use ($projectRoot) {
    $this->config->set('git-hooks.code_analyzers.larastan', $larastanConfiguration);
    $this->config->set('git-hooks.pre-commit', [LarastanPreCommitHook::class]);

    $this->makeTempFile(
        'ClassWithoutFixableIssues.php',
        file_get_contents($projectRoot.'/tests/Fixtures/ClassWithoutFixableIssues.php')
    );

    GitHooks::shouldReceive('isMergeInProgress')->andReturn(false);
    GitHooks::shouldReceive('getListOfChangedFiles')->andReturn('AM temp/ClassWithoutFixableIssues.php');

    $this->artisan('git-hooks:pre-commit')
        ->doesntExpectOutputToContain('Larastan Failed')
        ->assertSuccessful();
})->with('larastanConfiguration')->skip(!file_exists($larastanBin), 'PHPStan/Larastan binary not found');
