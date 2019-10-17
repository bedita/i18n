<?php
/**
 * BEdita, API-first content management framework
 * Copyright 2019 ChannelWeb Srl, Chialab Srl
 *
 * This file is part of BEdita: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * See LICENSE.LGPL or <http://gnu.org/licenses/lgpl-3.0.html> for more details.
 */

namespace BEdita\I18n\Test\Shell;

use BEdita\I18n\Shell\GettextShell;
use Cake\Console\Shell;
use Cake\TestSuite\ConsoleIntegrationTestCase;
use Cake\Utility\Hash;

/**
 * {@see \BEdita\I18n\Shell\GettextShell} Test Case
 *
 * @coversDefaultClass \BEdita\I18n\Shell\GettextShell
 */
class GettextShellTest extends ConsoleIntegrationTestCase
{
    /**
     * The shell for test
     *
     * @var GettextShell
     */
    protected $shell = null;

    /**
     * {@inheritDoc}
     */
    public function setUp()
    {
        $this->shell = new GettextShell();
        parent::setUp();
    }

    /**
     * Test update and private methods called inside update
     *
     * @covers ::update()
     */
    // public function testUpdate()
    // {
    // }

    /**
     * Provider for 'setupPaths'
     *
     * @return array
     */
    public function setupPathsProvider()
    {
        $base = getcwd();

        return [
            'app' => [
                'tests/files/gettext/app', // app path
                null, // start path
                null, // plugin path
                [
                    sprintf('%s/tests/files/gettext/app/src', $base),
                    sprintf('%s/tests/files/gettext/app/config', $base),
                ], // template paths
                sprintf('%s/tests/files/gettext/app/src/Locale', $base), // locale path
            ],
            'plugin' => [
                null, // app path
                sprintf('%s/tests/files/gettext', $base), // start path
                'dummy', // plugin name
                [
                    sprintf('%s/tests/files/gettext/plugins/dummy/src', $base),
                    sprintf('%s/tests/files/gettext/plugins/dummy/config', $base),
                ], // template paths
                sprintf('%s/tests/files/gettext/plugins/dummy/src/Locale', $base), // locale path
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
     *
     * @dataProvider setupPathsProvider
     * @covers ::setupPaths()
     */
    public function testSetupPaths(
        $appPath,
        $startPath,
        $pluginName,
        array $expectedTemplatePaths,
        string $expectedLocalePath)
    {
        $expectedPoName = 'default.po';
        if (!empty($appPath)) {
            $this->shell->params['app'] = sprintf('%s/%s', getcwd(), $appPath);
        }
        if (!empty($startPath)) {
            $this->shell->params['startPath'] = $startPath;
        }
        if (!empty($pluginName)) {
            $this->shell->params['plugin'] = $pluginName;
            $expectedPoName = sprintf('%s.po', $pluginName);
        }
        $method = self::getMethod('setupPaths');
        $method->invokeArgs($this->shell, []);
        static::assertEquals($expectedTemplatePaths, $this->shell->templatePaths);
        static::assertEquals($expectedLocalePath, $this->shell->localePath);
        static::assertEquals($expectedPoName, $this->shell->poName);
    }

    /**
     * Test writeMasterPot
     *
     * @covers ::writeMasterPot()
     * @return void
     */
    public function testWriteMasterPot()
    {
        // set localePath using reflection class
        $localePath = sprintf('%s/tests/files/gettext/app/src/Locale', getcwd());
        $class = new \ReflectionClass('BEdita\I18n\Shell\GettextShell');
        $property = $class->getProperty('localePath');
        $property->setAccessible(true);
        $property->setValue($class, $localePath);

        // set poResult using reflection class
        $property = $class->getProperty('poResult');
        $property->setAccessible(true);
        $property->setValue($class, [
            'This is a twig sample',
            'A twig content',
            'A twig string with \"double quotes\"',
            "A twig string with \'single quotes\'",
            'This is a php sample',
            'A php content',
            'A php string with \"double quotes\"',
            "A php string with \'single quotes\'",
        ]);

        // call writeMasterPot using reflection class
        $method = $class->getMethod('writeMasterPot');
        $method->setAccessible(true);
        $method->invokeArgs($this->shell, []);

        // check data
        $expected = '';
        foreach ($this->shell->poResult as $str) {
            $expected.= sprintf('%smsgid "%s"%smsgstr ""%s', "\n", $str, "\n", "\n");
        }
        $content = file_get_contents(sprintf('%s/master.pot', $localePath));
        static::assertEquals($expected, $content);
    }

    /**
     * Test writePoFiles
     *
     * @covers ::writePoFiles()
     * @return void
     */
    // public function testWritePoFiles()
    // {
    // }

    /**
     * Test analyzePoFile
     *
     * @covers ::analyzePoFile()
     * @return void
     */
    // public function testAnalyzePoFile()
    // {
    // }

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
                "something \'quoted\'", // expected
            ],
            'new lines' => [
                sprintf('something%swith%snew%slines', "\n", "\n", "\n", "\n"), // input
                sprintf('something%swith%snew%slines', '\n', '\n', '\n', '\n'), // expected
            ],
        ];
    }

    /**
     * Test fixString
     *
     * @param string $input The input string
     * @param string $expected The expected output string
     * @return void
     *
     * @dataProvider fixStringProvider
     * @covers ::fixString()
     */
    public function testFixString($input, $expected)
    {
        $method = self::getMethod('fixString');
        $args = [ $input ];
        $result = $method->invokeArgs($this->shell, $args);
        static::assertEquals($expected, $result);
    }

    /**
     * Provider for 'testParseDir'
     *
     * @return array
     */
    public function parseDirProvider()
    {
        return [
            'contents dir' => [
                sprintf('%s/tests/files/gettext/contents', getcwd()), // dir
                [
                    'This is a twig sample',
                    'A twig content',
                    'A twig string with \"double quotes\"',
                    "A twig string with \'single quotes\'",
                    'This is a php sample',
                    'A php content',
                    'A php string with \"double quotes\"',
                    "A php string with \'single quotes\'",
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
     *
     * @dataProvider parseDirProvider
     * @covers ::parseDir()
     * @covers ::parseFile()
     * @covers ::parseContent()
     * @covers ::fixString()
     */
    public function testParseDir(string $dir, array $expected)
    {
        $method = self::getMethod('parseDir');
        $method->invokeArgs($this->shell, [ $dir ]);
        static::assertEquals($expected, $this->shell->poResult);
    }

    /**
     * Test ttagExtract
     *
     * @covers ::ttagExtract()
     * @return void
     */
    // public function testTtagExtract()
    // {
    // }

    /**
     * Get GettextShell method by name, making it accessible
     *
     * @param string $name The method name
     * @return \ReflectionMethod
     */
    protected static function getMethod($name)
    {
        $class = new \ReflectionClass('BEdita\I18n\Shell\GettextShell');
        $method = $class->getMethod($name);
        $method->setAccessible(true);

        return $method;
    }
}