<?php
declare(strict_types=1);

namespace BEdita\I18n\Test\Filesystem;

use BEdita\I18n\Filesystem\Gettext;
use PHPUnit\Framework\TestCase;

/**
 * {@see \BEdita\I18n\Filesystem\Gettext} Test Case
 *
 * @coversDefaultClass \BEdita\I18n\Filesystem\Gettext
 */
class GettextTest extends TestCase
{
    /**
     * Test `analyzePoFile` method
     *
     * @return void
     * @covers ::analyzePoFile()
     */
    public function testAnalyzePoFile(): void
    {
        $filename = __DIR__ . '/../../test_dir/messages.po';
        $actual = Gettext::analyzePoFile($filename);
        foreach (['numItems', 'numNotTranslated', 'translated', 'percent'] as $key) {
            static::assertArrayHasKey($key, $actual);
        }
    }

    /**
     * Test `header` method
     *
     * @return void
     * @covers ::header()
     */
    public function testHeader(): void
    {
        $actual = Gettext::header();
        static::assertTrue(strpos($actual, 'msgid ""') === 0);
        static::assertTrue(strpos($actual, 'msgstr ""') > 0);
        static::assertTrue(strpos($actual, 'Project-Id-Version: BEdita 4') > 0);
        static::assertTrue(strpos($actual, 'Language-Team: BEdita I18N & I10N Team') > 0);
        static::assertTrue(strpos($actual, 'MIME-Version: 1.0') > 0);
        static::assertTrue(strpos($actual, 'Content-Transfer-Encoding: 8bit') > 0);
        static::assertTrue(strpos($actual, 'Plural-Forms: nplurals=2; plural=(n != 1);') > 0);
        static::assertTrue(strpos($actual, 'Content-Type: text/plain; charset=utf-8') > 0);
    }
}
