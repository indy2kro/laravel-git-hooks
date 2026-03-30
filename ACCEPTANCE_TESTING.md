# Acceptance Testing

Acceptance tests validate each supported tool end-to-end by running real binaries against actual fixture files through the full Artisan pre-commit pipeline.

## Why Separate from Feature Tests?

Feature tests mock binaries and focus on hook pipeline logic. Acceptance tests run the actual tool binaries to ensure the integration works with real tool output. They are kept separate so optional tools do not block the standard test suite.

## How It Works: Isolated Sandboxes

Each optional tool is installed in its own isolated temporary directory via `ToolSandbox`. This means:

- **No global pollution** — tools never conflict with each other or the project's own `vendor/`.
- **Cached installs** — the sandbox is reused on subsequent runs (no re-download if the binary is already present).
- **Automatic skip** — if installation fails (no network, incompatible PHP, etc.), the test is skipped with a clear message instead of failing.
- **One tool per directory** — `PHP_CodeSniffer`, `Psalm`, `Deptrac`, etc. each live under their own subdirectory:

```
/tmp/laravel-git-hooks-acceptance/
├── phpcodesniffer/      ← vendor/bin/phpcs, phpcbf
├── psalm/               ← vendor/bin/psalm
├── deptrac/             ← vendor/bin/deptrac
├── phpinsights/         ← vendor/bin/phpinsights
├── composer-normalize/  ← vendor/bin/composer-normalize
├── codeception/         ← vendor/bin/codecept
├── eslint/              ← node_modules/.bin/eslint
├── prettier/            ← node_modules/.bin/prettier
├── blade-formatter/     ← node_modules/.bin/blade-formatter
└── vitest/              ← node_modules/.bin/vitest
```

## Running Acceptance Tests

```bash
# Run all acceptance tests (tools install automatically on first run)
composer test:acceptance

# Or directly:
vendor/bin/pest --configuration phpunit-acceptance.xml

# Clean up all sandbox directories when done
composer test:acceptance:cleanup
```

## Pre-installed Tools (always run)

These tools ship with the package's dev dependencies and their acceptance tests run without any setup:

| Tool | Binary | Acceptance Test |
|------|--------|-----------------|
| Laravel Pint | `vendor/bin/pint` | `PintAcceptanceTest.php` |
| Larastan (PHPStan) | `vendor/bin/phpstan` | `LarastanAcceptanceTest.php` |
| PHP CS Fixer | `vendor/bin/php-cs-fixer` | `PHPCSFixerAcceptanceTest.php` |
| Rector | `vendor/bin/rector` | `RectorAcceptanceTest.php` |
| Pest | `vendor/bin/pest` | `PestRunnerAcceptanceTest.php` |
| PHPUnit | `vendor/bin/phpunit` | `PHPUnitRunnerAcceptanceTest.php` |

## Optional PHP Tools (auto-installed via sandbox)

On first run each tool is installed into its own isolated Composer project under the OS temp directory. Subsequent runs reuse the cached install.

| Tool | Package | Acceptance Test |
|------|---------|-----------------|
| PHP_CodeSniffer | `squizlabs/php_codesniffer` | `PHPCodeSnifferAcceptanceTest.php` |
| PHP Insights | `nunomaduro/phpinsights` | `PhpInsightsAcceptanceTest.php` |
| Psalm | `vimeo/psalm` | `PsalmAcceptanceTest.php` |
| Deptrac | `qossmic/deptrac` | `DeptracAcceptanceTest.php` |
| Composer Normalize | `ergebnis/composer-normalize` | `ComposerNormalizeAcceptanceTest.php` |
| Codeception | `codeception/codeception` | `CodeceptionAcceptanceTest.php` |

## Optional JS Tools (auto-installed via sandbox)

Each JS tool is installed into its own isolated `npm` project.

| Tool | Package | Acceptance Test |
|------|---------|-----------------|
| ESLint | `eslint` | `ESLintAcceptanceTest.php` |
| Prettier | `prettier` | `PrettierAcceptanceTest.php` |
| Blade Formatter | `blade-formatter` | `BladeFormatterAcceptanceTest.php` |
| Vitest | `vitest` | `VitestAcceptanceTest.php` |

## What Each Test Validates

### Code Analyzers
- **Fails** test: staged fixture file with known issues triggers the hook to report failure.
- **Passes** test: staged clean fixture file allows the commit to proceed.

### Test Runners (Pest, PHPUnit, Codeception, Vitest)
- **Skip gracefully** test: staged source file with no matching test file causes the hook to pass through without running tests.

## Fixture Files Used

| Fixture | Purpose |
|---------|---------|
| `ClassWithFixableIssues.php` | Missing `declare(strict_types=1)`, `return null` for `string` type — fails Pint, Larastan, PHP CS Fixer, PHPCS, Psalm |
| `ClassWithoutFixableIssues.php` | Clean, PSR-12 compliant empty class — passes all PHP analyzers |
| `ClassWithRectorIssues.php` | Uses `empty()` on array — fails Rector's `SimplifyEmptyCheckOnEmptyArrayRector` rule |
| `fixable-js-file.js` | JS file with double-quoted strings — fails Prettier/ESLint if configured |
| `fixable-blade-file.blade.php` | Poorly indented Blade template — fails Blade Formatter |
| `sample.js` | Simple clean JS file |

## Adding a New Tool's Acceptance Test

1. Create `tests/Acceptance/Hooks/YourToolAcceptanceTest.php`
2. Choose the right sandbox factory:

```php
<?php

declare(strict_types=1);

use Igorsgm\GitHooks\Console\Commands\Hooks\YourToolPreCommitHook;
use Igorsgm\GitHooks\Facades\GitHooks;
use Igorsgm\GitHooks\Tests\Acceptance\ToolSandbox;

$projectRoot = dirname(__DIR__, 3);

// For PHP tools:
$sandbox = ToolSandbox::php('your-tool', 'vendor/package', 'binary-name');

// For JS tools:
// $sandbox = ToolSandbox::js('your-tool', 'npm-package', 'binary-name');

beforeEach(function () use ($sandbox) {
    try {
        $sandbox->install();
    } catch (Throwable $e) {
        $this->markTestSkipped('YourTool sandbox setup failed: '.$e->getMessage());
    }

    $this->gitInit();
    $this->initializeTempDirectory(base_path('temp'));
});

test('YourTool fails when staged file has issues', function () use ($projectRoot, $sandbox) {
    $this->config->set('git-hooks.code_analyzers.your_tool', [
        'path' => $sandbox->binaryPath(),
        'file_extensions' => '/\.php$/',
        'run_in_docker' => false,
        'docker_container' => '',
    ]);
    $this->config->set('git-hooks.pre-commit', [YourToolPreCommitHook::class]);

    $this->makeTempFile(
        'ClassWithFixableIssues.php',
        file_get_contents($projectRoot.'/tests/Fixtures/ClassWithFixableIssues.php')
    );

    GitHooks::shouldReceive('isMergeInProgress')->andReturn(false);
    GitHooks::shouldReceive('getListOfChangedFiles')->andReturn('AM temp/ClassWithFixableIssues.php');

    $this->artisan('git-hooks:pre-commit')
        ->expectsOutputToContain('YourTool Failed')
        ->expectsOutputToContain('COMMIT FAILED')
        ->assertExitCode(1);
});
```

3. The test is automatically included in the acceptance suite — no configuration needed.

## Expected Output

When running `composer test:acceptance` for the first time (sandboxes install on demand):

```
PASS  Tests\Acceptance\Hooks\PintAcceptanceTest
  ✓ Pint fails when staged PHP file has style issues
  ✓ Pint passes when staged PHP file has no style issues

...

PASS  Tests\Acceptance\Hooks\PHPCodeSnifferAcceptanceTest    ← installs squizlabs/php_codesniffer first run
  ✓ PHP_CodeSniffer fails when staged PHP file violates coding standard
  ✓ PHP_CodeSniffer passes when no PHP files are staged

- Psalm sandbox setup failed: ...   → skipped if no network
```

On subsequent runs all sandboxes are cached and tests run at full speed.