<?php

declare(strict_types=1);

use Igorsgm\GitHooks\Console\Commands\Hooks\VitestPreCommitHook;
use Igorsgm\GitHooks\Facades\GitHooks;
use Igorsgm\GitHooks\Tests\Acceptance\ToolSandbox;

$projectRoot = dirname(__DIR__, 3);
$sandbox = ToolSandbox::js('vitest', 'vitest', 'vitest');

beforeEach(function () use ($sandbox) {
    try {
        $sandbox->install();
    } catch (Throwable $e) {
        $this->markTestSkipped('Vitest sandbox setup failed: '.$e->getMessage());
    }

    $this->gitInit();
    $this->initializeTempDirectory(base_path('temp'));
});

test('Vitest hook skips gracefully when no test files found for staged JS file', function () use ($projectRoot, $sandbox) {
    $this->config->set('git-hooks.code_analyzers.vitest', [
        'path' => $sandbox->binaryPath(),
        'run_in_docker' => false,
        'docker_container' => '',
        'additional_params' => '',
    ]);
    $this->config->set('git-hooks.pre-commit', [VitestPreCommitHook::class]);

    $this->makeTempFile('sample.js', file_get_contents($projectRoot.'/tests/Fixtures/sample.js'));

    GitHooks::shouldReceive('isMergeInProgress')->andReturn(false);
    GitHooks::shouldReceive('getListOfChangedFiles')->andReturn('AM temp/sample.js');

    // No *.test.js counterpart exists → hook finds no test files and calls $next
    $this->artisan('git-hooks:pre-commit')->assertSuccessful();
});
