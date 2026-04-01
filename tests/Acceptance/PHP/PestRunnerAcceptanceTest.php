<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Igorsgm\GitHooks\Console\Commands\Hooks\PestPreCommitHook;
use Igorsgm\GitHooks\Facades\GitHooks;

$projectRoot = dirname(__DIR__, 3);
$pestBin = $projectRoot.'/vendor/bin/pest';

beforeEach(function () {
    $this->gitInit();
    $this->initializeTempDirectory(base_path('temp'));
    File::deleteDirectory(base_path('tests'));
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

test('Pest runner passes when found test file has passing tests', function () use ($projectRoot, $pestBin) {
    $this->config->set('git-hooks.code_analyzers.pest', [
        'path' => $pestBin,
        'run_in_docker' => false,
        'docker_container' => '',
        'additional_params' => '--no-coverage --no-configuration --bootstrap='.$projectRoot.'/vendor/autoload.php',
    ]);
    $this->config->set('git-hooks.pre-commit', [PestPreCommitHook::class]);

    $this->makeTempFile('SomeClass.php', '<?php class SomeClass {}');

    File::makeDirectory(base_path('tests'), 0755, true, true);
    file_put_contents(
        base_path('tests/SomeClassTest.php'),
        "<?php\ntest('SomeClass works', fn () => expect(1 + 1)->toBe(2));\n"
    );

    GitHooks::shouldReceive('isMergeInProgress')->andReturn(false);
    GitHooks::shouldReceive('getListOfChangedFiles')->andReturn('AM temp/SomeClass.php');

    $this->artisan('git-hooks:pre-commit')
        ->doesntExpectOutputToContain('Pest Failed')
        ->assertSuccessful();

    File::deleteDirectory(base_path('tests'));
})->skip(!file_exists($pestBin), 'Pest binary not found');

test('Pest runner fails when found test file has failing tests', function () use ($projectRoot, $pestBin) {
    $this->config->set('git-hooks.code_analyzers.pest', [
        'path' => $pestBin,
        'run_in_docker' => false,
        'docker_container' => '',
        'additional_params' => '--no-coverage --no-configuration --bootstrap='.$projectRoot.'/vendor/autoload.php',
    ]);
    $this->config->set('git-hooks.pre-commit', [PestPreCommitHook::class]);

    $this->makeTempFile('SomeClass.php', '<?php class SomeClass {}');

    File::makeDirectory(base_path('tests'), 0755, true, true);
    file_put_contents(
        base_path('tests/SomeClassTest.php'),
        "<?php\ntest('SomeClass is broken', fn () => expect(true)->toBeFalse());\n"
    );

    GitHooks::shouldReceive('isMergeInProgress')->andReturn(false);
    GitHooks::shouldReceive('getListOfChangedFiles')->andReturn('AM temp/SomeClass.php');

    $this->artisan('git-hooks:pre-commit')
        ->expectsOutputToContain('Pest Failed')
        ->expectsOutputToContain('COMMIT FAILED')
        ->assertExitCode(1);

    File::deleteDirectory(base_path('tests'));
})->skip(!file_exists($pestBin), 'Pest binary not found');

