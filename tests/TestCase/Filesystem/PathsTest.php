<?php
declare(strict_types=1);

namespace BEdita\I18n\Test\Filesystem;

use BEdita\I18n\Filesystem\Paths;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Test class for Paths
 */
class PathsTest extends TestCase
{
    /**
     * Data provider for `testSetup` test case.
     *
     * @return array
     */
    public static function setupProvider(): array
    {
        $baseDir = getcwd();

        return [
            'plugin' => [
                ['plugin' => 'Dummy'],
                [
                    'templatePaths' => [
                        $baseDir . '/tests/test_app/plugins/Dummy/src/',
                        $baseDir . '/tests/test_app/plugins/Dummy/config/',
                        $baseDir . '/tests/test_app/plugins/Dummy/templates/',
                    ],
                    'localePath' => $baseDir . '/tests/test_app/plugins/Dummy/resources/locales/',
                    'defaultDomain' => 'Dummy',
                ],
            ],
            'plugins' => [
                ['plugins' => true],
                [
                    'templatePaths' => [
                        $baseDir . '/tests/test_app/TestApp/Template/',
                        $baseDir . '/tests/test_app/plugins/Dummy/Template/',
                        $baseDir . '/tests/test_app/TestApp/',
                        $baseDir . '/tests/test_app/config',
                        $baseDir . '/tests/test_app/plugins/Dummy/templates/',
                        $baseDir . '/tests/test_app/plugins/Dummy/src/',
                        $baseDir . '/tests/test_app/plugins/Dummy/config',
                    ],
                    'localePath' => $baseDir . '/tests/test_app/TestApp/Locale/',
                    'defaultDomain' => 'messages',
                ],
            ],
            'app' => [
                [],
                [
                    'templatePaths' => [
                        $baseDir . '/src',
                        $baseDir . '/config',
                        $baseDir . '/tests/test_app/TestApp/Template/',
                    ],
                    'localePath' => $baseDir . '/tests/test_app/TestApp/Locale/',
                    'defaultDomain' => 'messages',
                ],
            ],
        ];
    }

    /**
     * Test `setup` method
     *
     * @param array $options Options.
     * @param array $expected Expected values.
     * @return void
     */
    #[DataProvider('setupProvider')]
    public function testSetup(array $options, array $expected): void
    {
        $templatePaths = [];
        $localePath = __DIR__ . '/../../test_app/TestApp/Locale';
        $defaultDomain = 'messages';
        Paths::setup($templatePaths, $localePath, $defaultDomain, $options);
        static::assertSame($expected['templatePaths'], $templatePaths);
        static::assertSame($expected['localePath'], $localePath);
        static::assertSame($expected['defaultDomain'], $defaultDomain);
    }
}
