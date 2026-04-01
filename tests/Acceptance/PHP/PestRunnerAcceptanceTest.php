<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
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

/**
 * Diagnostic test - always passes.
 * Runs Pest directly (bypassing the hook) with several flag combinations and
 * writes the results to STDERR so they appear in CI logs even when later
 * tests fail.  Helps identify whether the issue is with Pest itself, the
 * --no-configuration flag, the bootstrap path, or the test-file syntax.
 */
test('Pest runner environment diagnostic', function () use ($projectRoot, $pestBin) {
    @mkdir(base_path('tests'), 0755, true);

    // Class-based test file (same as PHPUnitRunner uses)
    $classFile = base_path('tests/DiagClassTest.php');
    file_put_contents($classFile, "<?php\nuse PHPUnit\\Framework\\TestCase;\nclass DiagClassTest extends TestCase {\n    public function testTrue(): void { \$this->assertTrue(true); }\n}\n");

    // Functional test file (Pest-style)
    $funcFile = base_path('tests/DiagFuncTest.php');
    file_put_contents($funcFile, "<?php\ntest('it works', fn () => expect(1 + 1)->toBe(2));\n");

    $run = function (string $label, string $cmd): string {
        $out = [];
        $exit = -1;
        exec($cmd.' 2>&1', $out, $exit);

        return sprintf('[exit=%d] %s => %s', $exit, $label, implode(' | ', array_slice($out, 0, 8)));
    };

    $basePath = base_path();
    $bp = escapeshellarg($basePath);
    $pb = escapeshellarg($pestBin);
    $pr = $projectRoot;

    $lines = [
        '',
        str_repeat('=', 72),
        'PEST DIAGNOSTIC',
        str_repeat('=', 72),
        'PHP:             '.PHP_VERSION,
        'OS:              '.PHP_OS,
        'base_path():     '.$basePath,
        'project_root:    '.$projectRoot,
        'pest_bin:        '.$pestBin,
        'pest_exists:     '.(file_exists($pestBin) ? 'YES' : 'NO'),
        'pest_executable: '.(is_executable($pestBin) ? 'YES' : 'NO'),
        'class_file:      '.(file_exists($classFile) ? 'YES' : 'NO'),
        'func_file:       '.(file_exists($funcFile) ? 'YES' : 'NO'),
        '',
        $run('class+no-cfg+bootstrap', "cd $bp && $pb --no-coverage --no-configuration --bootstrap=$pr/vendor/autoload.php tests/DiagClassTest.php"),
        $run('class+no-cfg+no-bootstrap', "cd $bp && $pb --no-coverage --no-configuration tests/DiagClassTest.php"),
        $run('class+cfg+bootstrap', "cd $bp && $pb --no-coverage --bootstrap=$pr/vendor/autoload.php tests/DiagClassTest.php"),
        $run('func+no-cfg+bootstrap', "cd $bp && $pb --no-coverage --no-configuration --bootstrap=$pr/vendor/autoload.php tests/DiagFuncTest.php"),
        $run('func+cfg+bootstrap', "cd $bp && $pb --no-coverage --bootstrap=$pr/vendor/autoload.php tests/DiagFuncTest.php"),
        str_repeat('=', 72),
        '',
    ];

    fwrite(STDERR, implode(PHP_EOL, $lines));

    @unlink($classFile);
    @unlink($funcFile);

    expect(true)->toBeTrue();
})->skip(!file_exists($pestBin), 'Pest binary not found');

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
    $this->config->set('git-hooks.debug_commands', true);
    $this->config->set('git-hooks.output_errors', true);

    $this->makeTempFile('SomeClass.php', '<?php class SomeClass {}');

    File::makeDirectory(base_path('tests'), 0755, true, true);
    file_put_contents(base_path('tests/SomeClassTest.php'), <<<'PHP'
<?php
use PHPUnit\Framework\TestCase;
class SomeClassTest extends TestCase {
    public function testSomeClassWorks(): void { $this->assertTrue(true); }
}
PHP);

    GitHooks::shouldReceive('isMergeInProgress')->andReturn(false);
    GitHooks::shouldReceive('getListOfChangedFiles')->andReturn('AM temp/SomeClass.php');

    // Use Artisan::call() so we can capture and expose the hook output on failure
    $exitCode = Artisan::call('git-hooks:pre-commit');
    $hookOutput = Artisan::output();

    fwrite(STDERR, PHP_EOL.'=== HOOK OUTPUT (exit='.$exitCode.') ==='.PHP_EOL.$hookOutput.PHP_EOL.'==='.PHP_EOL);

    File::deleteDirectory(base_path('tests'));

    expect($exitCode)->toBe(0, 'Hook output was: '.$hookOutput);
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
    file_put_contents(base_path('tests/SomeClassTest.php'), <<<'PHP'
<?php
use PHPUnit\Framework\TestCase;
class SomeClassTest extends TestCase {
    public function testSomeClassIsBroken(): void { $this->assertTrue(false); }
}
PHP);

    GitHooks::shouldReceive('isMergeInProgress')->andReturn(false);
    GitHooks::shouldReceive('getListOfChangedFiles')->andReturn('AM temp/SomeClass.php');

    $this->artisan('git-hooks:pre-commit')
        ->expectsOutputToContain('Pest Failed')
        ->expectsOutputToContain('COMMIT FAILED')
        ->assertExitCode(1);

    File::deleteDirectory(base_path('tests'));
})->skip(!file_exists($pestBin), 'Pest binary not found');

