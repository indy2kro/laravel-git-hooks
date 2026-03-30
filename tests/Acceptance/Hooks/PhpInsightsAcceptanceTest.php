<?php

declare(strict_types=1);

use Igorsgm\GitHooks\Console\Commands\Hooks\PhpInsightsPreCommitHook;
use Igorsgm\GitHooks\Facades\GitHooks;
use Igorsgm\GitHooks\Tests\Acceptance\ToolSandbox;

$projectRoot = dirname(__DIR__, 3);
$sandbox = ToolSandbox::php('phpinsights', 'nunomaduro/phpinsights', 'phpinsights');

beforeEach(function () use ($sandbox) {
    try {
        $sandbox->install();
    } catch (Throwable $e) {
        $this->markTestSkipped('PHP Insights sandbox setup failed: '.$e->getMessage());
    }

    $this->gitInit();
    $this->initializeTempDirectory(base_path('temp'));
});

test('PHP Insights fails when staged PHP file has quality issues', function () use ($projectRoot, $sandbox) {
    $this->config->set('git-hooks.code_analyzers.phpinsights', [
        'path' => $sandbox->binaryPath(),
        'config' => $projectRoot.'/tests/Fixtures/phpinsightsFixture.php',
        'file_extensions' => '/\.php$/',
        'run_in_docker' => false,
        'docker_container' => '',
        'additional_params' => '',
    ]);
    $this->config->set('git-hooks.pre-commit', [PhpInsightsPreCommitHook::class]);

    $this->makeTempFile(
        'ClassWithFixableIssues.php',
        file_get_contents($projectRoot.'/tests/Fixtures/ClassWithFixableIssues.php')
    );

    GitHooks::shouldReceive('isMergeInProgress')->andReturn(false);
    GitHooks::shouldReceive('getListOfChangedFiles')->andReturn('AM temp/ClassWithFixableIssues.php');

    $this->artisan('git-hooks:pre-commit')
        ->expectsOutputToContain('PhpInsights Failed')
        ->expectsOutputToContain('COMMIT FAILED')
        ->expectsConfirmation('Would you like to attempt to correct files automagically?', 'no')
        ->assertExitCode(1);
});
