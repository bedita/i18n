<?php
declare(strict_types=1);

namespace BEdita\I18n\Test\TestCase\Command;

use BEdita\I18n\Command\GettextCommand;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Core\Configure;
use Cake\TestSuite\ConsoleIntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * {@see \BEdita\I18n\Command\GettextCommand} Test Case
 *
 * @coversDefaultClass \BEdita\I18n\Command\GettextCommand
 */
class GettextCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    /**
     * The command used in test
     *
     * @var \BEdita\I18n\Command\GettextCommand
     */
    protected $command = null;

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        Configure::write('I18n', [
            'locales' => [
                'en_US' => 'en',
                'it_IT' => 'it',
            ],
        ]);
        parent::setUp();
        $this->useCommandRunner();
        $this->command = new GettextCommand();
        $this->cleanFiles();
    }

    /**
     * @inheritDoc
     */
    public function tearDown(): void
    {
        $this->cleanFiles();
        parent::tearDown();
    }

    /**
     * Test buildOptionParser method
     *
     * @return void
     * @covers ::buildOptionParser()
     */
    public function testBuildOptionParser(): void
    {
        $this->exec('gettext --help');
        $this->assertOutputContains('Create or update i18n po/pot files');
        $this->assertOutputContains('bin/cake gettext');
    }

    /**
     * Test execute method
     *
     * @return void
     * @covers ::execute()
     * @covers ::getPoResult()
     * @covers ::getTemplatePaths()
     * @covers ::getLocalePath()
     */
    public function testExecute(): void
    {
        // set localePath using reflection class
        $localePath = sprintf('%s/tests/test_app/TestApp/Locale', getcwd());
        Configure::write('App.paths.locales', [$localePath]);

        // call the method
        $appPath = sprintf('%s/tests/test_app/TestApp', getcwd());
        $this->exec('gettext --app ' . $appPath);

        // check po files are not empty
        foreach (['en_US', 'it_IT'] as $locale) {
            $content = file_get_contents(sprintf('%s/%s/default.po', $localePath, $locale));
            static::assertNotEmpty($content);
        }
    }

    /**
     * Provider for 'setupPaths'
     *
     * @return array
     */
    public function setupPathsProvider(): array
    {
        $base = getcwd();

        return [
            'app' => [
                'tests/test_app/TestApp', // app path
                null, // start path
                null, // plugin path
                [
                    sprintf('%s/tests/test_app/TestApp/src', $base),
                    sprintf('%s/tests/test_app/TestApp/config', $base),
                    sprintf('%s/tests/test_app/TestApp/Template', $base),
                ], // template paths
                sprintf('%s/tests/test_app/TestApp/Locale', $base), // locale path
            ],
            'plugin' => [
                null, // app path
                sprintf('%s/tests/test_app/TestApp', $base), // start path
                'Dummy', // plugin name
                [
                    sprintf('%s/tests/test_app/plugins/Dummy/src', $base),
                    sprintf('%s/tests/test_app/plugins/Dummy/config', $base),
                    sprintf('%s/tests/test_app/plugins/Dummy/templates', $base),
                ], // template paths
                sprintf('%s/tests/test_app/plugins/Dummy/Locale', $base), // locale path
            ],
        ];
    }

    /**
     * Test `setupPaths`
     *
     * @param string|null $appPath The app file path
     * @param string|null $startPath The start path
     * @param string|null $pluginName The plugin name
     * @param array $expectedTemplatePaths The expected template paths
     * @param string $expectedLocalePath The expected locale path
     * @return void
     * @dataProvider setupPathsProvider
     * @covers ::setupPaths()
     */
    public function testSetupPaths($appPath, $startPath, $pluginName, array $expectedTemplatePaths, string $expectedLocalePath): void
    {
        $expectedPoName = 'default.po';
        $options = [];
        if (!empty($appPath)) {
            $options['app'] = sprintf('%s/%s', getcwd(), $appPath);
        }
        if (!empty($startPath)) {
            $options['startPath'] = $startPath;
        }
        if (!empty($pluginName)) {
            $options['plugin'] = $pluginName;
            $expectedPoName = sprintf('%s.po', $pluginName);
        }
        $args = new Arguments([], $options, []);
        $method = self::getMethod('setupPaths');
        $method->invokeArgs($this->command, [$args]);
        $i = 0;
        $actualPaths = $this->command->getTemplatePaths();
        foreach ($actualPaths as &$actual) {
            if (strlen($actual) !== strlen($expectedTemplatePaths[$i++])) {
                $actual = substr($actual, 0, -1);
            }
        }
        static::assertEquals($expectedTemplatePaths, $actualPaths);
        $actual = $this->command->getLocalePath();
        if (strlen($actual) !== strlen($expectedLocalePath)) {
            $actual = substr($actual, 0, -1);
        }
        static::assertEquals($expectedLocalePath, $actual);
    }

    /**
     * Test writeMasterPot
     *
     * @covers ::writeMasterPot()
     * @return void
     */
    public function testWriteMasterPot(): void
    {
        // set localePath using reflection class
        $localePath = sprintf('%s/tests/test_app/TestApp/Locale', getcwd());
        $reflection = new \ReflectionProperty(get_class($this->command), 'localePath');
        $reflection->setAccessible(true);
        $reflection->setValue($this->command, $localePath);

        // set poResult using reflection class
        $poResult['default'] = [
            'This is a php sample',
            'A php content',
            'A php string with \"double quotes\"',
            "A php string with \'single quotes\'",
            'This is a twig sample',
            'A twig content',
            'A twig string with \"double quotes\"',
            "A twig string with \'single quotes\'",
        ];
        $reflection = new \ReflectionProperty(get_class($this->command), 'poResult');
        $reflection->setAccessible(true);
        $reflection->setValue($this->command, $poResult);

        // call writeMasterPot using reflection class
        $io = new ConsoleIo();
        $method = self::getMethod('writeMasterPot');
        $method->invokeArgs($this->command, [$io]);

        // file default.pot have been override, check again content (it should be unchanged), except for POT-Creation-Date
        $content = file_get_contents(sprintf('%s/default.pot', $localePath));
        static::assertNotEmpty($content);
    }

    /**
     * Test 'writePoFiles'
     *
     * @return void
     * @covers ::header()
     * @covers ::writePoFiles()
     * @covers ::analyzePoFile()
     */
    public function testWritePoFiles(): void
    {
        // set localePath using reflection class
        $localePath = sprintf('%s/tests/test_app/TestApp/Locale', getcwd());
        $reflection = new \ReflectionProperty(get_class($this->command), 'localePath');
        $reflection->setAccessible(true);
        $reflection->setValue($this->command, $localePath);

        // set poResult using reflection class
        $poResult['default'] = [
            'This is a php sample',
            'A php content',
        ];
        $reflection = new \ReflectionProperty(get_class($this->command), 'poResult');
        $reflection->setAccessible(true);
        $reflection->setValue($this->command, $poResult);

        // invoke writePoFiles
        $io = new ConsoleIo();
        $method = self::getMethod('writePoFiles');
        $method->invokeArgs($this->command, [$io]);

        // check po files are not empty
        foreach (['en_US', 'it_IT'] as $locale) {
            $content = file_get_contents(sprintf('%s/%s/default.po', $localePath, $locale));
            static::assertNotEmpty($content);
        }
    }

    /**
     * Provider for 'fixString'
     *
     * @return array
     */
    public function fixStringProvider()
    {
        return [
            'quotes' => [
                'something "quoted"', // input
                'something \"quoted\"', // expected
            ],
            'escaped quotes |||||' => [
                'something |||||quoted|||||', // input
                "something 'quoted'", // expected
            ],
            'new lines' => [
                sprintf('something%swith%snew%slines', "\n", "\n", "\n"), // input
                sprintf('something%swith%snew%slines', '\n', '\n', '\n'), // expected
            ],
        ];
    }

    /**
     * Test fixString
     *
     * @param string $input The input string
     * @param string $expected The expected output string
     * @return void
     * @dataProvider fixStringProvider
     * @covers ::fixString()
     */
    public function testFixString($input, $expected): void
    {
        $method = self::getMethod('fixString');
        $args = [$input];
        $result = $method->invokeArgs($this->command, $args);
        static::assertEquals($expected, $result);
    }

    /**
     * Provider for 'testParseFile'
     *
     * @return array
     */
    public function parseFileProvider(): array
    {
        return [
            'no twig, no php extension file' => [
                sprintf('%s/tests/files/gettext/contents/sample.js', getcwd()),
                'js',
                [],
            ],
            'empty php file' => [
                sprintf('%s/tests/files/gettext/contents/empty.php', getcwd()),
                'php',
                [],
            ],
            'sample php' => [
                sprintf('%s/tests/files/gettext/contents/sample.php', getcwd()),
                'php',
                [
                    'default' => [
                        'This is a php sample',
                        'A php content',
                        'A php string with \"double quotes\"',
                        'A php string with \'single quotes\'',
                        '1 test __',
                        '2 test __',
                        '3 test __',
                        '4 test __',
                        '1 test __n',
                        '1 test __x',
                        '1 test __xn',
                        '1 test __dx',
                        '1 test __dxn',
                    ],
                    'DomainSampleD' => [
                        '1 test __d',
                    ],
                    'DomainSampleDN' => [
                        '1 test __dn',
                    ],
                ],
            ],
            'sample twig' => [
                sprintf('%s/tests/files/gettext/contents/sample.twig', getcwd()),
                'twig',
                [
                    'default' => [
                        'This is a twig sample',
                        'A twig content',
                        'A twig string with \"double quotes\"',
                        'A twig string with \'single quotes\'',
                    ],
                    'DomainSampleD' => [
                        'A twig string in a domain',
                    ],
                ],
            ],
        ];
    }

    /**
     * Test 'parseFile'
     *
     * @param string $file The file to parse
     * @param string $extension The file extension
     * @param array $expected The po result array
     * @return void
     * @dataProvider parseFileProvider
     * @covers ::parseFile()
     */
    public function testParseFile(string $file, string $extension, array $expected): void
    {
        $method = self::getMethod('parseFile');
        $method->invokeArgs($this->command, [$file, $extension]);
        $actual = $this->command->getPoResult();
        $this->recursiveSort($expected);
        $this->recursiveSort($actual);
        static::assertEquals($expected, $actual);
    }

    /**
     * Provider for 'testParseDir'
     *
     * @return array
     */
    public function parseDirProvider(): array
    {
        return [
            'contents dir' => [
                sprintf('%s/tests/files/gettext/contents', getcwd()), // dir
                [
                    'default' => [
                        'This is a twig sample',
                        'A twig content',
                        'A twig string with \"double quotes\"',
                        "A twig string with 'single quotes'",
                        'This is a php sample',
                        'A php content',
                        'A php string with \"double quotes\"',
                        'A php string with \'single quotes\'',
                        '1 test __',
                        '2 test __',
                        '3 test __',
                        '4 test __',
                        '1 test __n',
                        '1 test __x',
                        '1 test __xn',
                        '1 test __dx',
                        '1 test __dxn',
                    ],
                    'DomainSampleD' => [
                        'A twig string in a domain',
                        '1 test __d',
                    ],
                    'DomainSampleDN' => [
                        '1 test __dn',
                    ],
                ], // result
            ],
        ];
    }

    /**
     * Test 'parseDir,', 'parseFile' and 'parseContent' functions
     *
     * @param string $dir The directory containing files to parse
     * @param array $expected The po result array
     * @return void
     * @dataProvider parseDirProvider
     * @covers ::parseDir()
     * @covers ::parseFile()
     * @covers ::parseContent()
     * @covers ::parseContentSecondArg()
     * @covers ::parseContentThirdArg()
     * @covers ::strposX()
     * @covers ::fixString()
     */
    public function testParseDir(string $dir, array $expected): void
    {
        $method = self::getMethod('parseDir');
        $method->invokeArgs($this->command, [$dir]);
        $actual = $this->command->getPoResult();
        $this->recursiveSort($expected);
        $this->recursiveSort($actual);
        static::assertEquals($expected, $actual);
    }

    /**
     * Recursive ksort/sort arrays used in tests
     *
     * @param array $array Array to sort
     * @return void
     */
    protected function recursiveSort(array &$array): void
    {
        foreach ($array as &$value) {
            if (is_array($value)) {
                $this->recursiveSort($value);
            }
        }
        if (array_values($array) === $array) {
            sort($array);

            return;
        }

        ksort($array);
    }

    /**
     * Get GettextShell method by name, making it accessible
     *
     * @param string $name The method name
     * @return \ReflectionMethod
     */
    protected static function getMethod($name): \ReflectionMethod
    {
        $class = new \ReflectionClass(GettextCommand::class);
        $method = $class->getMethod($name);
        $method->setAccessible(true);

        return $method;
    }

    /**
     * Clean files for test.
     *
     * @return void
     */
    private function cleanFiles(): void
    {
        $files = [
            sprintf('%s/tests/test_app/TestApp/Locale/default.pot', getcwd()),
            sprintf('%s/tests/test_app/TestApp/Locale/en_US/default.po', getcwd()),
            sprintf('%s/tests/test_app/TestApp/Locale/it_IT/default.po', getcwd()),
        ];
        foreach ($files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }
}
