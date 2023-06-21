<?php
declare(strict_types=1);

namespace BEdita\I18n\Test\Filesystem;

use BEdita\I18n\Filesystem\Ttag;
use PHPUnit\Framework\TestCase;

/**
 * {@see \BEdita\I18n\Filesystem\Ttag} Test Case
 *
 * @coversDefaultClass \BEdita\I18n\Filesystem\Ttag
 */
class TtagTest extends TestCase
{
    /**
     * Test `extract` method.
     *
     * @return void
     * @covers ::extract()
     * @covers ::doExtract()
     */
    public function testExtract(): void
    {
        $testDir = sprintf('%s/tests/test_dir', getcwd());
        $locales = ['en_US'];
        $localePath = $testDir;
        $plugin = null;
        $actual = Ttag::extract($locales, $localePath, $plugin);
        static::assertIsArray($actual);
        static::assertArrayHasKey('extracted', $actual);
        static::assertArrayHasKey('info', $actual);

        $actual = Ttag::extract($locales, $localePath, 'Dummy');
        static::assertIsArray($actual);
        static::assertArrayHasKey('extracted', $actual);
        static::assertArrayHasKey('info', $actual);
    }

    /**
     * Test `doExtract` method.
     *
     * @return void
     * @covers ::doExtract()
     */
    public function testDoExtract(): void
    {
        $testAppDir = sprintf('%s/tests/test_app/TestApp', getcwd());
        $testDir = sprintf('%s/tests/test_dir', getcwd());
        $locales = ['en_US'];
        $localePath = $testDir;
        $appDir = $testAppDir;
        $ttag = 'node_modules/ttag-cli/bin/ttag';
        define('RESOURCES', $appDir);
        $actual = Ttag::doExtract($ttag, $appDir, $localePath, $locales);
        static::assertFalse($actual);
    }
}
