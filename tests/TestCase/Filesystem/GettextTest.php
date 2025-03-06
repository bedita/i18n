<?php
declare(strict_types=1);

namespace BEdita\I18n\Test\Filesystem;

use BEdita\I18n\Filesystem\Gettext;
use PHPUnit\Framework\TestCase;

/**
 * Test class for Gettext
 */
class GettextTest extends TestCase
{
    public const LOCALE_PATH = APP . 'Locale';

    /**
     * Test `analyzePoFile` method
     *
     * @return void
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

    /**
     * Test `writeMasterPot` method.
     *
     * @return void
     */
    public function testWriteMasterPot(): void
    {
        $translations = [
            'messages' => [
                [
                    'Something',
                    'Translalable',
                ],
            ],
        ];
        $actual = Gettext::writeMasterPot(self::LOCALE_PATH, $translations);
        foreach (['info', 'updated'] as $key) {
            static::assertArrayHasKey($key, $actual);
        }
        static::assertTrue($actual['updated']);
        if (file_exists(self::LOCALE_PATH . DS . 'messages.pot')) {
            unlink(self::LOCALE_PATH . DS . 'messages.pot');
        }
    }

    /**
     * Test `writePoFiles` method on empty locales.
     *
     * @return void
     */
    public function testWritePoFilesEmptyLocales(): void
    {
        $locales = [];
        $localePath = self::LOCALE_PATH;
        $translations = [];
        $actual = Gettext::writePoFiles($locales, $localePath, $translations);
        static::assertArrayHasKey('info', $actual);
    }

    /**
     * Test `writePoFiles` method on empty pot folder.
     *
     * @return void
     */
    public function testWritePoFilesEmptyPotFolder(): void
    {
        $locales = ['es_ES'];
        $localePath = self::LOCALE_PATH;
        $translations = [
            'messages' => [
                [
                    'Something',
                    'Translalable',
                ],
            ],
        ];
        $actual = Gettext::writePoFiles($locales, $localePath, $translations);
        static::assertArrayHasKey('info', $actual);
        foreach ($locales as $locale) {
            $localeFile = $localePath . DS . $locale . DS . 'messages.po';
            static::assertTrue(file_exists($localeFile));
            unlink($localeFile);
        }
        if (file_exists($localePath . DS . 'messages.pot')) {
            unlink($localePath . DS . 'messages.pot');
        }
        if (file_exists($localePath . DS . $locales[0])) {
            rmdir($localePath . DS . $locales[0]);
        }
    }

    /**
     * Test `writePoFiles` method.
     *
     * @return void
     */
    public function testWritePoFiles(): void
    {
        $locales = ['en_US', 'it_IT'];
        $localePath = self::LOCALE_PATH;
        $translations = [
            'messages' => [
                [
                    'Something',
                    'Translalable',
                ],
            ],
        ];
        $actual = Gettext::writePoFiles($locales, $localePath, $translations);
        static::assertArrayHasKey('info', $actual);
        foreach ($locales as $locale) {
            $localeFile = $localePath . DS . $locale . DS . 'messages.po';
            static::assertTrue(file_exists($localeFile));
            unlink($localeFile);
        }
        if (file_exists($localePath . DS . 'messages.pot')) {
            unlink($localePath . DS . 'messages.pot');
        }
    }
}
