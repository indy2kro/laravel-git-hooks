<?php

declare(strict_types=1);

use Igorsgm\GitHooks\Console\Commands\Hooks\CodeceptionPreCommitHook;
use Igorsgm\GitHooks\Facades\GitHooks;
use Igorsgm\GitHooks\Tests\Acceptance\ToolSandbox;

$projectRoot = dirname(__DIR__, 3);
$sandbox = ToolSandbox::php('codeception', 'codeception/codeception', 'codecept');

beforeEach(function () use ($sandbox) {
    try {
        $sandbox->install();
    } catch (Throwable $e) {
        $this->markTestSkipped('Codeception sandbox setup failed: '.$e->getMessage());
    }

    $this->gitInit();
    $this->initializeTempDirectory(base_path('temp'));
});

test('Codeception hook skips gracefully when no test files found for staged file', function () use ($sandbox) {
    $this->config->set('git-hooks.code_analyzers.codeception', [
        'path' => $sandbox->binaryPath(),
        'run_in_docker' => false,
        'docker_container' => '',
        'additional_params' => '',
    ]);
    $this->config->set('git-hooks.pre-commit', [CodeceptionPreCommitHook::class]);

    $this->makeTempFile('NoTestFile.php', '<?php class NoTestFile {}');

    GitHooks::shouldReceive('isMergeInProgress')->andReturn(false);
    GitHooks::shouldReceive('getListOfChangedFiles')->andReturn('AM temp/NoTestFile.php');

    // No matching *Cest.php / *Cept.php found → hook skips gracefully
    $this->artisan('git-hooks:pre-commit')->assertSuccessful();
});
