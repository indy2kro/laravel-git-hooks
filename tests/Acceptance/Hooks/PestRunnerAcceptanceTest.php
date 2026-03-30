<?php

declare(strict_types=1);

use Igorsgm\GitHooks\Console\Commands\Hooks\PestPreCommitHook;
use Igorsgm\GitHooks\Facades\GitHooks;

$projectRoot = dirname(__DIR__, 3);
$pestBin = $projectRoot.'/vendor/bin/pest';

beforeEach(function () {
    $this->gitInit();
    $this->initializeTempDirectory(base_path('temp'));
});

test('Pest hook skips gracefully when no test files found for staged file', function () use ($pestBin) {
    $this->config->set('git-hooks.code_analyzers.pest', [
        'path' => $pestBin,
        'run_in_docker' => false,
        'docker_container' => '',
        'additional_params' => '',
    ]);
    $this->config->set('git-hooks.pre-commit', [PestPreCommitHook::class]);

    $this->makeTempFile('NoTestFile.php', '<?php class NoTestFile {}');

    GitHooks::shouldReceive('isMergeInProgress')->andReturn(false);
    GitHooks::shouldReceive('getListOfChangedFiles')->andReturn('AM temp/NoTestFile.php');

    $this->artisan('git-hooks:pre-commit')->assertSuccessful();
})->skip(!file_exists($pestBin), 'Pest binary not found');
