<?php
declare(strict_types=1);

namespace BEdita\I18n\Test\Filesystem;

use BEdita\I18n\Filesystem\Ttag;
use PHPUnit\Framework\TestCase;

/**
 * Test class for Ttag
 */
class TtagTest extends TestCase
{
    /**
     * Test `extract` method.
     *
     * @return void
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
}
