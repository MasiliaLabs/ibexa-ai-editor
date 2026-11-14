<?php

declare(strict_types=1);

namespace Masilia\IbexaAiEditor\Tests;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * The layout of the Ibexa packages: the bundle is the integration with Symfony, the lib is the implementation, the contracts are
 * the public API. Each holds what it should and knows only what it may.
 */
final class ArchitectureTest extends TestCase
{
    private const SRC = __DIR__ . '/../src';

    /** What a bundle may hold: the integration, nothing else. */
    private const BUNDLE_MAY_HOLD = ['Controller', 'DependencyInjection', 'Resources', 'MasiliaIbexaAiEditorBundle.php'];

    /** What the lib may not name: the bundle, and the parts of Symfony that wire or serve an application. */
    private const LIB_MAY_NOT_USE = [
        'Masilia\\Bundle\\',
        'Symfony\\Component\\HttpKernel\\',
        'Symfony\\Bundle\\',
        'Symfony\\Component\\Config\\',
        'Symfony\\Component\\DependencyInjection\\ContainerBuilder',
        'Symfony\\Component\\DependencyInjection\\Extension\\',
        'Symfony\\Component\\DependencyInjection\\Compiler\\',
        'Symfony\\Component\\DependencyInjection\\Loader\\',
        'Symfony\\Component\\HttpFoundation\\',
    ];

    /** @return list<string> */
    private function files(string $directory): array
    {
        $files = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::SRC . '/' . $directory, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }

    public function testTheBundleHoldsTheSymfonyIntegrationOnly(): void
    {
        $found = array_values(array_diff(scandir(self::SRC . '/bundle') ?: [], ['.', '..']));

        self::assertSame([], array_values(array_diff($found, self::BUNDLE_MAY_HOLD)), 'The bundle holds what is not integration: it belongs in src/lib.');
    }

    public function testTheLibKnowsNeitherTheBundleNorTheWiringOfSymfony(): void
    {
        $wrong = [];
        foreach ($this->files('lib') as $file) {
            $code = (string) preg_replace('#/\*.*?\*/|//[^\n]*#s', '', (string) file_get_contents($file));
            preg_match_all('/^use ([A-Za-z0-9_\\\\]+)/m', $code, $uses);
            foreach ($uses[1] as $name) {
                foreach (self::LIB_MAY_NOT_USE as $forbidden) {
                    if (str_starts_with($name, $forbidden)) {
                        $wrong[] = sprintf('%s uses %s', substr($file, strlen(self::SRC) + 1), $name);
                    }
                }
            }
        }

        self::assertSame([], $wrong, implode("\n", $wrong));
    }
}
