<?php

declare(strict_types=1);

use Igorsgm\GitHooks\Console\Commands\Hooks\PHPUnitPreCommitHook;
use Igorsgm\GitHooks\Facades\GitHooks;

$projectRoot = dirname(__DIR__, 3);
$phpunitBin = $projectRoot.'/vendor/bin/phpunit';

beforeEach(function () {
    $this->gitInit();
    $this->initializeTempDirectory(base_path('temp'));
});

test('PHPUnit hook skips gracefully when no test files found for staged file', function () use ($phpunitBin) {
    $this->config->set('git-hooks.code_analyzers.phpunit', [
        'path' => $phpunitBin,
        'run_in_docker' => false,
        'docker_container' => '',
        'additional_params' => '',
    ]);
    $this->config->set('git-hooks.pre-commit', [PHPUnitPreCommitHook::class]);

    $this->makeTempFile('NoTestFile.php', '<?php class NoTestFile {}');

    GitHooks::shouldReceive('isMergeInProgress')->andReturn(false);
    GitHooks::shouldReceive('getListOfChangedFiles')->andReturn('AM temp/NoTestFile.php');

    $this->artisan('git-hooks:pre-commit')->assertSuccessful();
})->skip(!file_exists($phpunitBin), 'PHPUnit binary not found');
