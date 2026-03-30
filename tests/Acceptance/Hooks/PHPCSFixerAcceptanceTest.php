<?php

declare(strict_types=1);

use Igorsgm\GitHooks\Console\Commands\Hooks\PHPCSFixerPreCommitHook;
use Igorsgm\GitHooks\Facades\GitHooks;

$projectRoot = dirname(__DIR__, 3);
$phpCsFixerBin = $projectRoot.'/vendor/bin/php-cs-fixer';

beforeEach(function () {
    $this->gitInit();
    $this->initializeTempDirectory(base_path('temp'));
});

test('PHP CS Fixer fails when staged PHP file has style issues', function ($phpCSFixerConfiguration) use ($projectRoot) {
    $this->config->set('git-hooks.code_analyzers.php_cs_fixer', $phpCSFixerConfiguration);
    $this->config->set('git-hooks.pre-commit', [PHPCSFixerPreCommitHook::class]);

    $this->makeTempFile(
        'ClassWithFixableIssues.php',
        file_get_contents($projectRoot.'/tests/Fixtures/ClassWithFixableIssues.php')
    );

    GitHooks::shouldReceive('isMergeInProgress')->andReturn(false);
    GitHooks::shouldReceive('getListOfChangedFiles')->andReturn('AM temp/ClassWithFixableIssues.php');

    $this->artisan('git-hooks:pre-commit')
        ->expectsOutputToContain('PHP_CS_Fixer Failed')
        ->expectsOutputToContain('COMMIT FAILED')
        ->expectsConfirmation('Would you like to attempt to correct files automagically?', 'no')
        ->assertExitCode(1);
})->with('phpcsFixerConfiguration')->skip(!file_exists($phpCsFixerBin), 'PHP CS Fixer binary not found');

test('PHP CS Fixer passes when no PHP files are staged', function ($phpCSFixerConfiguration) use ($projectRoot) {
    $this->config->set('git-hooks.code_analyzers.php_cs_fixer', $phpCSFixerConfiguration);
    $this->config->set('git-hooks.pre-commit', [PHPCSFixerPreCommitHook::class]);

    $this->makeTempFile(
        'sample.js',
        file_get_contents($projectRoot.'/tests/Fixtures/sample.js')
    );

    GitHooks::shouldReceive('isMergeInProgress')->andReturn(false);
    GitHooks::shouldReceive('getListOfChangedFiles')->andReturn('AM temp/sample.js');

    $this->artisan('git-hooks:pre-commit')
        ->doesntExpectOutputToContain('PHP_CS_Fixer Failed')
        ->assertSuccessful();
})->with('phpcsFixerConfiguration')->skip(!file_exists($phpCsFixerBin), 'PHP CS Fixer binary not found');
