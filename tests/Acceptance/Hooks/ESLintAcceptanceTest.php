<?php

declare(strict_types=1);

use Igorsgm\GitHooks\Console\Commands\Hooks\ESLintPreCommitHook;
use Igorsgm\GitHooks\Facades\GitHooks;
use Igorsgm\GitHooks\Tests\Acceptance\ToolSandbox;

$projectRoot = dirname(__DIR__, 3);
$sandbox = ToolSandbox::js('eslint', 'eslint', 'eslint');

beforeEach(function () use ($sandbox) {
    try {
        $sandbox->install();
    } catch (Throwable $e) {
        $this->markTestSkipped('ESLint sandbox setup failed: '.$e->getMessage());
    }

    $this->gitInit();
    $this->initializeTempDirectory(base_path('temp'));
});

test('ESLint fails when staged JS file has linting errors', function () use ($projectRoot, $sandbox) {
    $this->config->set('git-hooks.code_analyzers.eslint', [
        'path' => $sandbox->binaryPath(),
        'config' => $projectRoot.'/tests/Fixtures/.eslintrcFixture.js',
        'file_extensions' => '/\.(js|jsx|ts|tsx)$/',
        'run_in_docker' => false,
        'docker_container' => '',
        'additional_params' => '',
    ]);
    $this->config->set('git-hooks.pre-commit', [ESLintPreCommitHook::class]);

    $this->makeTempFile(
        'fixable-js-file.js',
        file_get_contents($projectRoot.'/tests/Fixtures/fixable-js-file.js')
    );

    GitHooks::shouldReceive('isMergeInProgress')->andReturn(false);
    GitHooks::shouldReceive('getListOfChangedFiles')->andReturn('AM temp/fixable-js-file.js');

    $this->artisan('git-hooks:pre-commit')
        ->expectsOutputToContain('ESLint Failed')
        ->expectsOutputToContain('COMMIT FAILED')
        ->expectsConfirmation('Would you like to attempt to correct files automagically?', 'no')
        ->assertExitCode(1);
});

test('ESLint passes when staged JS file has no linting errors', function () use ($projectRoot, $sandbox) {
    $this->config->set('git-hooks.code_analyzers.eslint', [
        'path' => $sandbox->binaryPath(),
        'config' => $projectRoot.'/tests/Fixtures/.eslintrcFixture.js',
        'file_extensions' => '/\.(js|jsx|ts|tsx)$/',
        'run_in_docker' => false,
        'docker_container' => '',
        'additional_params' => '',
    ]);
    $this->config->set('git-hooks.pre-commit', [ESLintPreCommitHook::class]);

    $this->makeTempFile('ClassWithoutFixableIssues.php', file_get_contents($projectRoot.'/tests/Fixtures/ClassWithoutFixableIssues.php'));

    GitHooks::shouldReceive('isMergeInProgress')->andReturn(false);
    GitHooks::shouldReceive('getListOfChangedFiles')->andReturn('AM temp/ClassWithoutFixableIssues.php');

    $this->artisan('git-hooks:pre-commit')
        ->doesntExpectOutputToContain('ESLint Failed')
        ->assertSuccessful();
});
