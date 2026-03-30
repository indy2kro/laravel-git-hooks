<?php

declare(strict_types=1);

use Igorsgm\GitHooks\Console\Commands\Hooks\PsalmPreCommitHook;
use Igorsgm\GitHooks\Facades\GitHooks;
use Igorsgm\GitHooks\Tests\Acceptance\ToolSandbox;

$projectRoot = dirname(__DIR__, 3);
$sandbox = ToolSandbox::php('psalm', 'vimeo/psalm', 'psalm');

beforeEach(function () use ($sandbox) {
    try {
        $sandbox->install();
    } catch (Throwable $e) {
        $this->markTestSkipped('Psalm sandbox setup failed: '.$e->getMessage());
    }

    $this->gitInit();
    $this->initializeTempDirectory(base_path('temp'));
});

test('Psalm fails when staged PHP file has type errors', function () use ($projectRoot, $sandbox) {
    $this->config->set('git-hooks.code_analyzers.psalm', [
        'path' => $sandbox->binaryPath(),
        'config' => '',
        'file_extensions' => '/\.php$/',
        'run_in_docker' => false,
        'docker_container' => '',
        'additional_params' => '--no-cache',
    ]);
    $this->config->set('git-hooks.pre-commit', [PsalmPreCommitHook::class]);

    $this->makeTempFile(
        'ClassWithFixableIssues.php',
        file_get_contents($projectRoot.'/tests/Fixtures/ClassWithFixableIssues.php')
    );

    GitHooks::shouldReceive('isMergeInProgress')->andReturn(false);
    GitHooks::shouldReceive('getListOfChangedFiles')->andReturn('AM temp/ClassWithFixableIssues.php');

    $this->artisan('git-hooks:pre-commit')
        ->expectsOutputToContain('Psalm Failed')
        ->expectsOutputToContain('COMMIT FAILED')
        ->assertExitCode(1);
});

test('Psalm passes when staged PHP file has no type errors', function () use ($projectRoot, $sandbox) {
    $this->config->set('git-hooks.code_analyzers.psalm', [
        'path' => $sandbox->binaryPath(),
        'config' => '',
        'file_extensions' => '/\.php$/',
        'run_in_docker' => false,
        'docker_container' => '',
        'additional_params' => '--no-cache',
    ]);
    $this->config->set('git-hooks.pre-commit', [PsalmPreCommitHook::class]);

    $this->makeTempFile('sample.js', file_get_contents($projectRoot.'/tests/Fixtures/sample.js'));

    GitHooks::shouldReceive('isMergeInProgress')->andReturn(false);
    GitHooks::shouldReceive('getListOfChangedFiles')->andReturn('AM temp/sample.js');

    // No PHP files staged → psalm skips and hook passes
    $this->artisan('git-hooks:pre-commit')
        ->doesntExpectOutputToContain('Psalm Failed')
        ->assertSuccessful();
});

test('Psalm skips non-PHP files staged alongside PHP file with errors', function () use ($projectRoot, $sandbox) {
    $this->config->set('git-hooks.code_analyzers.psalm', [
        'path' => $sandbox->binaryPath(),
        'config' => '',
        'file_extensions' => '/\.php$/',
        'run_in_docker' => false,
        'docker_container' => '',
        'additional_params' => '--no-cache',
    ]);
    $this->config->set('git-hooks.pre-commit', [PsalmPreCommitHook::class]);

    $this->makeTempFile(
        'ClassWithFixableIssues.php',
        file_get_contents($projectRoot.'/tests/Fixtures/ClassWithFixableIssues.php')
    );
    $this->makeTempFile(
        'sample.js',
        file_get_contents($projectRoot.'/tests/Fixtures/sample.js')
    );

    GitHooks::shouldReceive('isMergeInProgress')->andReturn(false);
    GitHooks::shouldReceive('getListOfChangedFiles')
        ->andReturn("AM temp/ClassWithFixableIssues.php\nAM temp/sample.js");

    $this->artisan('git-hooks:pre-commit')
        ->expectsOutputToContain('Psalm Failed')
        ->doesntExpectOutputToContain('sample.js')
        ->expectsOutputToContain('COMMIT FAILED')
        ->assertExitCode(1);
});
