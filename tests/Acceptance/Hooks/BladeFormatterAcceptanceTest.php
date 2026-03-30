<?php

declare(strict_types=1);

use Igorsgm\GitHooks\Console\Commands\Hooks\BladeFormatterPreCommitHook;
use Igorsgm\GitHooks\Facades\GitHooks;
use Igorsgm\GitHooks\Tests\Acceptance\ToolSandbox;

$projectRoot = dirname(__DIR__, 3);
$sandbox = ToolSandbox::js('blade-formatter', 'blade-formatter', 'blade-formatter');

beforeEach(function () use ($sandbox) {
    try {
        $sandbox->install();
    } catch (Throwable $e) {
        $this->markTestSkipped('Blade Formatter sandbox setup failed: '.$e->getMessage());
    }

    $this->gitInit();
    $this->initializeTempDirectory(base_path('temp'));
});

test('Blade Formatter fails when staged blade file is not formatted', function () use ($projectRoot, $sandbox) {
    $this->config->set('git-hooks.code_analyzers.blade_formatter', [
        'path' => $sandbox->binaryPath(),
        'config' => '',
        'file_extensions' => '/\.blade\.php$/',
        'run_in_docker' => false,
        'docker_container' => '',
    ]);
    $this->config->set('git-hooks.pre-commit', [BladeFormatterPreCommitHook::class]);

    $this->makeTempFile(
        'fixable-blade-file.blade.php',
        file_get_contents($projectRoot.'/tests/Fixtures/fixable-blade-file.blade.php')
    );

    GitHooks::shouldReceive('isMergeInProgress')->andReturn(false);
    GitHooks::shouldReceive('getListOfChangedFiles')->andReturn('AM temp/fixable-blade-file.blade.php');

    $this->artisan('git-hooks:pre-commit')
        ->expectsOutputToContain('Blade Formatter Failed')
        ->expectsOutputToContain('COMMIT FAILED')
        ->expectsConfirmation('Would you like to attempt to correct files automagically?', 'no')
        ->assertExitCode(1);
});
