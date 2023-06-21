<?php
declare(strict_types=1);

namespace BEdita\I18n\Test\TestCase\Command;

use BEdita\I18n\Command\GettextCommand;
use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Core\Configure;
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
     */
    public function testExecute(): void
    {
        // set localePath using reflection class
        $localePath = sprintf('%s/tests/test_app/TestApp/Locale', getcwd());
        if (!file_exists($localePath)) {
            mkdir($localePath, 0777, true);
        }
        $potFilename = sprintf('%s/default.pot', $localePath);
        if (!file_exists($potFilename)) {
            file_put_contents($potFilename, '');
        }
        Configure::write('App.paths.locales', [$localePath]);

        // call the method
        $appPath = sprintf('%s/tests/test_app/TestApp', getcwd());
        $this->exec('gettext --app ' . $appPath);
        static::assertExitSuccess();

        // check po files are not empty
        foreach (['en_US', 'it_IT'] as $locale) {
            $content = file_get_contents(sprintf('%s/%s/default.po', $localePath, $locale));
            static::assertNotEmpty($content);
        }
    }

    /**
     * Test execute method with locales passed as option..
     *
     * @return void
     * @covers ::execute()
     */
    public function testExecuteWithLocales(): void
    {
        $localePath = APP . 'Locale';
        Configure::write('App.paths.locales', [$localePath]);
        if (!file_exists($localePath)) {
            mkdir($localePath, 0777, true);
        }
        $potFilename = sprintf('%s/default.pot', $localePath);
        if (!file_exists($potFilename)) {
            file_put_contents($potFilename, '');
        }

        $this->exec('gettext -l en,it --app ' . APP);
        $this->assertExitSuccess();

        foreach (['en', 'it'] as $locale) {
            $content = file_get_contents(sprintf('%s/%s/default.po', $localePath, $locale));
            static::assertNotEmpty($content);
        }
    }

    /**
     * Test command without any locales.
     *
     * @return void
     */
    public function testExecuteWithoutLocales(): void
    {
        $localePath = APP . 'Locale';
        Configure::write('App.paths.locales', [$localePath]);
        Configure::write('I18n', [
            'locales' => [],
        ]);
        if (!file_exists($localePath)) {
            mkdir($localePath, 0777, true);
        }
        $potFilename = sprintf('%s/default.pot', $localePath);
        if (!file_exists($potFilename)) {
            file_put_contents($potFilename, '');
        }

        $this->exec('gettext --app ' . APP);
        static::assertExitSuccess();
        $this->assertOutputContains('No locales set, .po files generation skipped');
    }

    /**
     * Test execute method with ci flag
     *
     * @return void
     * @covers ::execute()
     */
    public function testUpdateWithCi(): void
    {
        // set localePath using reflection class
        $localePath = sprintf('%s/tests/test_app/TestApp/Locale', getcwd());
        if (!file_exists($localePath)) {
            mkdir($localePath, 0777, true);
        }
        Configure::write('App.paths.locales', [$localePath]);
        $potFilename = sprintf('%s/default.pot', $localePath);
        if (!file_exists($potFilename)) {
            file_put_contents($potFilename, '');
        }

        // call the method
        $appPath = sprintf('%s/tests/test_app/TestApp', getcwd());
        $this->exec('gettext --app ' . $appPath . ' --ci');
        static::assertExitCode(GettextCommand::CODE_CHANGES);

        // check po files are not empty
        foreach (['en_US', 'it_IT'] as $locale) {
            $content = file_get_contents(sprintf('%s/%s/default.po', $localePath, $locale));
            static::assertNotEmpty($content);
        }

        // call method again
        $this->exec('gettext --app ' . $appPath);
        static::assertExitSuccess();
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
            sprintf('%s/tests/test_app/TestApp/Locale/it/default.po', getcwd()),
            sprintf('%s/tests/test_app/TestApp/Locale/en/default.po', getcwd()),
        ];
        foreach ($files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }
}
