<?php

declare(strict_types=1);

use Igorsgm\GitHooks\Console\Commands\Hooks\PintPreCommitHook;
use Igorsgm\GitHooks\Facades\GitHooks;

$projectRoot = dirname(__DIR__, 3);
$pintBin = $projectRoot.'/vendor/bin/pint';

beforeEach(function () {
    $this->gitInit();
    $this->initializeTempDirectory(base_path('temp'));
});

test('Pint fails when staged PHP file has style issues', function ($pintConfiguration) use ($projectRoot) {
    $this->config->set('git-hooks.code_analyzers.laravel_pint', $pintConfiguration);
    $this->config->set('git-hooks.pre-commit', [PintPreCommitHook::class]);

    $this->makeTempFile(
        'ClassWithFixableIssues.php',
        file_get_contents($projectRoot.'/tests/Fixtures/ClassWithFixableIssues.php')
    );

    GitHooks::shouldReceive('isMergeInProgress')->andReturn(false);
    GitHooks::shouldReceive('getListOfChangedFiles')->andReturn('AM temp/ClassWithFixableIssues.php');

    $this->artisan('git-hooks:pre-commit')
        ->expectsOutputToContain('Pint Failed')
        ->expectsOutputToContain('COMMIT FAILED')
        ->expectsConfirmation('Would you like to attempt to correct files automagically?', 'no')
        ->assertExitCode(1);
})->with('pintConfiguration')->skip(!file_exists($pintBin), 'Laravel Pint binary not found');

test('Pint passes when no PHP files are staged', function ($pintConfiguration) use ($projectRoot) {
    $this->config->set('git-hooks.code_analyzers.laravel_pint', $pintConfiguration);
    $this->config->set('git-hooks.pre-commit', [PintPreCommitHook::class]);

    $this->makeTempFile(
        'sample.js',
        file_get_contents($projectRoot.'/tests/Fixtures/sample.js')
    );

    GitHooks::shouldReceive('isMergeInProgress')->andReturn(false);
    GitHooks::shouldReceive('getListOfChangedFiles')->andReturn('AM temp/sample.js');

    $this->artisan('git-hooks:pre-commit')
        ->doesntExpectOutputToContain('Pint Failed')
        ->assertSuccessful();
})->with('pintConfiguration')->skip(!file_exists($pintBin), 'Laravel Pint binary not found');
