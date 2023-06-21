<?php
declare(strict_types=1);

namespace BEdita\I18n\Test\Filesystem;

use BEdita\I18n\Filesystem\File;
use PHPUnit\Framework\TestCase;

/**
 * {@see \BEdita\I18n\Filesystem\File} Test Case
 *
 * @coversDefaultClass \BEdita\I18n\Filesystem\File
 */
class FileTest extends TestCase
{
    /**
     * Test `parseDir` method with invalid directory
     *
     * @return void
     * @covers ::parseDir()
     */
    public function testParseDirInvalid(): void
    {
        $dir = __DIR__ . '/invalid';
        $defaultDomain = 'messages';
        $translations = [];
        $actual = File::parseDir($dir, $defaultDomain, $translations);
        static::assertFalse($actual);
    }

    /**
     * Test `parseDir` method with valid directory.
     * Files to find: php|ctp|thtml|inc|tpl|twig
     *
     * @return void
     * @covers ::parseDir()
     * @covers ::parseFile()
     */
    public function testParseDir(): void
    {
        $dir = sprintf('%s/tests/test_dir', getcwd());
        $defaultDomain = 'messages';
        $translations = [];
        $expected = [
            'messages' => [
                'Sample twig' => [0 => ''],
                'Singular' => [0 => ''],
                'Context translation' => [0 => '', 1 => 'TestContext'],
                'Context translation singular' => [0 => '', 1 => 'TestContext'],
                'Sample ctp' => [0 => ''],
                'Sample php' => [0 => ''],
            ],
            'Test' => [
                'Plugin translation' => [0 => ''],
                'Plugin translation singular' => [0 => ''],
                'Context plugin translation' => [0 => '', 1 => 'TestContext'],
                'Context plugin translation singular' => [0 => '', 1 => 'TestContext'],
            ],
        ];
        $actual = File::parseDir($dir, $defaultDomain, $translations);
        static::assertFalse($actual); // there is an empty file in dir test_dir
        static::assertNotEmpty($translations);
        static::assertEquals($expected, $translations);
    }

    /**
     * Test `parseFile` method with invalid file
     *
     * @return void
     * @covers ::parseFile()
     */
    public function testParseFileNotExists(): void
    {
        $file = __DIR__ . 'invalid.twig';
        $defaultDomain = 'messages';
        $translations = [];
        $actual = File::parseFile($file, $defaultDomain, $translations);
        static::assertFalse($actual);
    }

    /**
     * Test `parseFile` method with empty file
     *
     * @return void
     * @covers ::parseFile()
     */
    public function testParseFileEmpty(): void
    {
        $file = __DIR__ . '/../../test_dir/empty.twig';
        $defaultDomain = 'messages';
        $translations = [];
        $actual = File::parseFile($file, $defaultDomain, $translations);
        static::assertFalse($actual);
    }

    /**
     * Test `parseFile` method with valid file
     *
     * @return void
     * @covers ::parseFile()
     */
    public function testParseFile(): void
    {
        $file = __DIR__ . '/../../test_dir/sample.twig';
        $defaultDomain = 'messages';
        $translations = [];
        $expected = [
            'messages' => [
                'Sample twig' => [0 => ''],
                'Singular' => [0 => ''],
                'Context translation' => [0 => '', 1 => 'TestContext'],
                'Context translation singular' => [0 => '', 1 => 'TestContext'],
            ],
            'Test' => [
                'Plugin translation' => [0 => ''],
                'Plugin translation singular' => [0 => ''],
                'Context plugin translation' => [0 => '', 1 => 'TestContext'],
                'Context plugin translation singular' => [0 => '', 1 => 'TestContext'],
            ],
        ];
        $actual = File::parseFile($file, $defaultDomain, $translations);
        static::assertTrue($actual);
        static::assertNotEmpty($translations);
        static::assertSame($expected, $translations);
    }

    /**
     * Test `unquoteString` method
     *
     * @return void
     * @covers ::unquoteString()
     */
    public function testUnquoteString(): void
    {
        $string = '"Sample string"';
        $expected = 'Sample string';
        $actual = File::unquoteString($string);
        static::assertSame($expected, $actual);
    }

    /**
     * Test `fixString` method
     *
     * @return void
     * @covers ::fixString()
     */
    public function testFixString(): void
    {
        $string = 'Sample string ' . '"' . "\n" . '|||||';
        $expected = 'Sample string ' . '\"' . '\n' . "'";
        $actual = File::fixString($string);
        static::assertSame($expected, $actual);
    }
}
