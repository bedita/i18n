<?php
declare(strict_types=1);

/**
 * BEdita, API-first content management framework
 * Copyright 2022 Atlas Srl, Chialab Srl
 *
 * This file is part of BEdita: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * See LICENSE.LGPL or <http://gnu.org/licenses/lgpl-3.0.html> for more details.
 */

namespace BEdita\I18n\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\App;
use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\Filesystem\File;
use Cake\Filesystem\Folder;
use Cake\Utility\Hash;
use Cake\View\View;

/**
 * Gettext command.
 */
class GettextCommand extends Command
{
    /**
     * The Po results
     *
     * @var array
     */
    protected $poResult = [];

    /**
     * The template paths
     *
     * @var array
     */
    protected $templatePaths = [];

    /**
     * The locale path
     *
     * @var string
     */
    protected $localePath = null;

    /**
     * The name of default domain if not specified. Used for pot and po file names.
     *
     * @var string
     */
    protected $defaultDomain = 'default';

    /**
     * @inheritDoc
     */
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return parent::buildOptionParser($parser)
            ->setDescription([
                'Create or update i18n po/pot files',
                '',
                '`bin/cake gettext`: update files for current app',
                '`bin/cake gettext -app <app path>`: update files for the app',
                '`bin/cake gettext -plugin <plugin name>`: update files for the plugin',
            ])
            ->addOption('app', [
                'help' => 'The app path, for i18n update.',
                'short' => 'a',
                'required' => false,
            ])
            ->addOption('plugin', [
                'help' => 'The plugin name, for i18n update.',
                'short' => 'p',
                'required' => false,
            ]);
    }

    /**
     * Get po result.
     *
     * @return array
     */
    public function getPoResult(): array
    {
        return $this->poResult;
    }

    /**
     * Get templatePaths.
     *
     * @return array
     */
    public function getTemplatePaths(): array
    {
        return $this->templatePaths;
    }

    /**
     * Get localePath
     *
     * @return string
     */
    public function getLocalePath(): string
    {
        return $this->localePath;
    }

    /**
     * Update gettext po files.
     *
     * @param \Cake\Console\Arguments $args The command arguments.
     * @param \Cake\Console\ConsoleIo $io The console io
     * @return null|void|int The exit code or null for success
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        $resCmd = [];
        exec('which msgmerge 2>&1', $resCmd);
        if (empty($resCmd[0])) {
            $io->abort('ERROR: msgmerge not available. Please install gettext utilities.');
        }

        $io->out('Updating .pot and .po files...');

        $this->setupPaths($args);
        foreach ($this->templatePaths as $path) {
            $io->out(sprintf('Search in: %s', $path));
            $this->parseDir($path);
        }

        $io->out('Creating master .pot file');
        $this->writeMasterPot($io);
        $this->ttagExtract($args, $io);

        $io->hr();
        $io->out('Merging master .pot with current .po files');
        $io->hr();

        $this->writePoFiles($io);

        $io->out('Done');

        return null;
    }

    /**
     * Setup template paths and locale path
     *
     * @param \Cake\Console\Arguments $args The command arguments.
     * @return void
     */
    private function setupPaths(Arguments $args): void
    {
        $plugin = $args->getOption('plugin');
        $localesPaths = (array)App::path('locales');
        if ($plugin && is_string($plugin)) {
            $paths = [
                Plugin::classPath($plugin),
                Plugin::configPath($plugin),
            ];
            $this->templatePaths = array_merge($paths, App::path(View::NAME_TEMPLATE, $plugin));
            $this->defaultDomain = $plugin;
            foreach ($localesPaths as $path) {
                if (strpos($path, sprintf('%s%s%s', DS, $plugin, DS)) > 0) {
                    $this->localePath = $path;
                    break;
                }
            }

            return;
        }
        $app = $args->getOption('app');
        $basePath = $app ?? getcwd();
        $this->templatePaths = [$basePath . DS . 'src', $basePath . DS . 'config'];
        $this->templatePaths = array_merge($this->templatePaths, App::path(View::NAME_TEMPLATE));
        $this->templatePaths = array_filter($this->templatePaths, function ($path) {
            return strpos($path, 'plugins') === false;
        });
        $this->localePath = (string)Hash::get($localesPaths, 0);
    }

    /**
     * Write `master.pot` file
     *
     * @param \Cake\Console\ConsoleIo $io The console io
     * @return void
     */
    private function writeMasterPot(ConsoleIo $io): void
    {
        foreach ($this->poResult as $domain => $poResult) {
            $potFilename = sprintf('%s/%s.pot', $this->localePath, $domain);
            $io->out(sprintf('Writing new .pot file: %s', $potFilename));
            $pot = new File($potFilename, true);
            $pot->write($this->header('pot'));
            sort($poResult);
            foreach ($poResult as $res) {
                if (!empty($res)) {
                    $pot->write(sprintf('%smsgid "%s"%smsgstr ""%s', "\n", $res, "\n", "\n"));
                }
            }
            $pot->close();
        }
    }

    /**
     * Write `.po` files
     *
     * @param \Cake\Console\ConsoleIo $io The console io
     * @return void
     */
    private function writePoFiles(ConsoleIo $io): void
    {
        $header = $this->header('po');
        $locales = array_keys((array)Configure::read('I18n.locales', []));
        foreach ($locales as $loc) {
            $potDir = $this->localePath . DS . $loc;
            if (!file_exists($potDir)) {
                mkdir($potDir);
            }
            $io->out(sprintf('Language: %s', $loc));

            foreach (array_keys($this->poResult) as $domain) {
                $potFilename = sprintf('%s/%s.pot', $this->localePath, $domain);
                $poFile = sprintf('%s/%s.po', $potDir, $domain);
                if (!file_exists($poFile)) {
                    $newPoFile = new File($poFile, true);
                    $newPoFile->write($header);
                    $newPoFile->close();
                }
                $io->out(sprintf('Merging %s', $poFile));
                $mergeCmd = sprintf('msgmerge --backup=off -N -U %s %s', $poFile, $potFilename);
                exec($mergeCmd);
                $this->analyzePoFile($poFile, $io);
                $io->hr();
            }
        }
    }

    /**
     * Header lines for po/pot file
     *
     * @param string $type The file type (can be 'po', 'pot')
     * @return string
     * @codeCoverageIgnore
     */
    private function header(string $type = 'po'): string
    {
        $result = sprintf('msgid ""%smsgstr ""%s', "\n", "\n");
        $contents = [
            'po' => [
                'Project-Id-Version' => 'BEdita 4',
                'POT-Creation-Date' => date('Y-m-d H:i:s'),
                'PO-Revision-Date' => '',
                'Last-Translator' => '',
                'Language-Team' => 'BEdita I18N & I10N Team',
                'Language' => '',
                'MIME-Version' => '1.0',
                'Content-Transfer-Encoding' => '8bit',
                'Plural-Forms' => 'nplurals=2; plural=(n != 1);',
                'Content-Type' => 'text/plain; charset=utf-8',
            ],
            'pot' => [
                'Project-Id-Version' => 'BEdita 4',
                'POT-Creation-Date' => date('Y-m-d H:i:s'),
                'MIME-Version' => '1.0',
                'Content-Transfer-Encoding' => '8bit',
                'Language-Team' => 'BEdita I18N & I10N Team',
                'Plural-Forms' => 'nplurals=2; plural=(n != 1);',
                'Content-Type' => 'text/plain; charset=utf-8',
            ],
        ];
        foreach ($contents[$type] as $k => $v) {
            $result .= sprintf('"%s: %s \n"', $k, $v) . "\n";
        }

        return $result;
    }

    /**
     * Analyze po file and translate it
     *
     * @param string $filename The po file name
     * @param \Cake\Console\ConsoleIo $io The console io
     * @return void
     */
    private function analyzePoFile($filename, ConsoleIo $io): void
    {
        $lines = file($filename);
        $numItems = $numNotTranslated = 0;
        foreach ($lines as $k => $l) {
            if (strpos($l, 'msgid "') === 0) {
                $numItems++;
            }
            if (strpos($l, 'msgstr ""') === 0) {
                if (!isset($lines[$k + 1])) {
                    $numNotTranslated++;
                } elseif (strpos($lines[$k + 1], '"') !== 0) {
                    $numNotTranslated++;
                }
            }
        }
        $translated = $numItems - $numNotTranslated;
        $percent = 0;
        if ($numItems > 0) {
            $percent = number_format($translated * 100. / $numItems, 1);
        }
        $io->out(sprintf('Translated %d of %d items - %s %%', $translated, $numItems, $percent));
    }

    /**
     * "fix" string - strip slashes, escape and convert new lines to \n
     *
     * @param string $str The string
     * @return string The new string
     */
    private function fixString($str): string
    {
        $str = stripslashes($str);
        $str = str_replace('"', '\"', $str);
        $str = str_replace("\n", '\n', $str);
        $str = str_replace('|||||', "'", $str); // special sequence used in parseContent to temporarily replace "\'"

        return $str;
    }

    /**
     * Parse file and rips gettext strings
     *
     * @param string $file The file name
     * @param string $extension The file extension
     * @return void
     */
    private function parseFile($file, $extension)
    {
        if (!in_array($extension, ['php', 'twig'])) {
            return;
        }
        $content = file_get_contents($file);
        if (empty($content)) {
            return;
        }

        $functions = [
            '__' => 0, // __( string $singular , ... $args )
            '__n' => 0, // __n( string $singular , string $plural , integer $count , ... $args )
            '__d' => 1, // __d( string $domain , string $msg , ... $args )
            '__dn' => 1, // __dn( string $domain , string $singular , string $plural , integer $count , ... $args )
            '__x' => 1, // __x( string $context , string $singular , ... $args )
            '__xn' => 1, // __xn( string $context , string $singular , string $plural , integer $count , ... $args )
            '__dx' => 2, // __dx( string $domain , string $context , string $msg , ... $args )
            '__dxn' => 2, // __dxn( string $domain , string $context , string $singular , string $plural , integer $count , ... $args )
        ];

        // temporarily replace "\'" with "|||||", fixString will replace "|||||" with "\'"
        // this fixes wrongly matched data in the following regexp
        $content = str_replace("\'", '|||||', $content);

        $options = [
            'open_parenthesis' => preg_quote('('),
            'quote' => preg_quote("'"),
            'double_quote' => preg_quote('"'),
        ];

        foreach ($functions as $fname => $singularPosition) {
            if ($singularPosition === 0) {
                $this->parseContent($fname, $content, $options);
            } elseif ($singularPosition === 1) {
                $this->parseContentSecondArg($fname, $content, $options);
            } elseif ($singularPosition === 2) {
                $this->parseContentThirdArg($fname, $content, $options);
            }
        }
    }

    /**
     * Parse file content and put i18n data in poResult array
     *
     * @param string $start The starting string to search for, the name of the translation method
     * @param string $content The file content
     * @param array $options The options
     * @return void
     */
    private function parseContent($start, $content, $options): void
    {
        // phpcs:disable
        $rgxp = '/' .
            "${start}\s*{$options['open_parenthesis']}\s*{$options['double_quote']}" . "([^{$options['double_quote']}]*)" . "{$options['double_quote']}" .
            '|' .
            "${start}\s*{$options['open_parenthesis']}\s*{$options['quote']}" . "([^{$options['quote']}]*)" . "{$options['quote']}" .
            '/';
        // phpcs:enable
        $matches = [];
        preg_match_all($rgxp, $content, $matches);

        $domain = $this->defaultDomain;

        $limit = count($matches[0]);
        for ($i = 0; $i < $limit; $i++) {
            $item = $this->fixString($matches[1][$i]);
            if (empty($item)) {
                $item = $this->fixString($matches[2][$i]);
            }

            if (!array_key_exists($domain, $this->poResult)) {
                $this->poResult[$domain] = [];
            }

            if (!in_array($item, $this->poResult[$domain])) {
                $this->poResult[$domain][] = $item;
            }
        }
    }

    /**
     * Parse file content and put i18n data in poResult array
     *
     * @param string $start The starting string to search for, the name of the translation method
     * @param string $content The file content
     * @param array $options The options
     * @return void
     */
    private function parseContentSecondArg($start, $content, $options): void
    {
        $capturePath = "([^']*)',\s*'([^']*)";
        $doubleQuoteCapture = str_replace("'", $options['double_quote'], $capturePath);
        $quoteCapture = str_replace("'", $options['quote'], $capturePath);

        // phpcs:disable
        $rgxp =
            '/' . "${start}\s*{$options['open_parenthesis']}\s*{$options['double_quote']}" . $doubleQuoteCapture . "{$options['double_quote']}" .
            '|' . "${start}\s*{$options['open_parenthesis']}\s*{$options['quote']}" . $quoteCapture . "{$options['quote']}" .
            '/';
        // phpcs:enable
        $matches = [];
        preg_match_all($rgxp, $content, $matches);

        $limit = count($matches[0]);
        for ($i = 0; $i < $limit; $i++) {
            $domain = $matches[3][$i];
            $str = $matches[4][$i];

            // context not handled for now
            if (strpos($start, '__x') === 0) {
                $domain = $this->defaultDomain;
            }

            $item = $this->fixString($str);

            if (!array_key_exists($domain, $this->poResult)) {
                $this->poResult[$domain] = [];
            }

            if (!in_array($item, $this->poResult[$domain])) {
                $this->poResult[$domain][] = $item;
            }
        }
    }

    /**
     * Parse file content and put i18n data in poResult array
     *
     * @param string $start The starting string to search for, the name of the translation method
     * @param string $content The file content
     * @param array $options The options
     * @return void
     */
    private function parseContentThirdArg($start, $content, $options): void
    {
        // phpcs:disable
        $rgxp =
            '/' . "${start}\s*{$options['open_parenthesis']}\s*{$options['double_quote']}" . '([^{)}]*)' . "{$options['double_quote']}" .
            '|' . "${start}\s*{$options['open_parenthesis']}\s*{$options['quote']}" . '([^{)}]*)' . "{$options['quote']}" .
            '/';
        // phpcs:enable
        $matches = [];
        preg_match_all($rgxp, $content, $matches);

        $domain = $this->defaultDomain; // domain and context not handled yet

        $limit = count($matches[0]);
        for ($i = 0; $i < $limit; $i++) {
            $str = $matches[2][$i];
            $pos = $this->strposX($str, ',', 2);
            $str = trim(substr($str, $pos + 1));
            if (strpos($str, ',') > 0) {
                $str = substr($str, 1, strpos($str, ',') - 2);
            } else {
                $str = substr($str, 1);
            }
            $item = $this->fixString($str);

            if (!array_key_exists($domain, $this->poResult)) {
                $this->poResult[$domain] = [];
            }

            if (!in_array($item, $this->poResult[$domain])) {
                $this->poResult[$domain][] = $item;
            }
        }
    }

    /**
     * Calculate nth ($number) position of $needle in $haystack.
     *
     * @param string $haystack The haystack where to search
     * @param string $needle The needle to search
     * @param int $number The nth position to retrieve
     * @return int|false
     */
    private function strposX($haystack, $needle, $number = 0)
    {
        return strpos(
            $haystack,
            $needle,
            $number > 1 ?
            $this->strposX($haystack, $needle, $number - 1) + strlen($needle) : 0
        );
    }

    /**
     * Parse a directory
     *
     * @param string $dir The directory
     * @return void
     */
    private function parseDir($dir): void
    {
        $folder = new Folder($dir);
        $tree = $folder->tree($dir, false);
        foreach ($tree as $files) {
            foreach ($files as $file) {
                if (!is_dir($file)) {
                    $f = new File($file);
                    $info = $f->info();
                    if (isset($info['extension'])) {
                        $this->parseFile($file, $info['extension']);
                    }
                }
            }
        }
    }

    /**
     * Extract translations from javascript files using ttag, if available.
     *
     * @param \Cake\Console\Arguments $args The command arguments.
     * @param \Cake\Console\ConsoleIo $io The console io
     * @return void
     * @codeCoverageIgnore
     */
    private function ttagExtract(Arguments $args, ConsoleIo $io): void
    {
        // check ttag command exists
        $ttag = 'node_modules/ttag-cli/bin/ttag';
        if (!file_exists($ttag)) {
            $io->out(sprintf('Skip javascript parsing - %s command not found', $ttag));

            return;
        }
        // check template folder exists
        $plugin = $args->getOption('plugin');
        $appDir = (!empty($plugin) && is_string($plugin)) ? Plugin::templatePath($plugin) : App::path(View::NAME_TEMPLATE)[0];
        if (!file_exists($appDir)) {
            $io->out(sprintf('Skip javascript parsing - %s folder not found', $appDir));

            return;
        }

        // do extract translation strings from js files using ttag
        $io->out('Extracting translation string from javascript files using ttag');
        $defaultJs = sprintf('%s/default-js.pot', $this->localePath);
        exec(sprintf('%s extract --o %s --l en %s', $ttag, $defaultJs, $appDir));

        // merge default-js.pot and default.pot
        $default = sprintf('%s/default.pot', $this->localePath);
        exec(sprintf('msgcat --use-first %s %s -o %s', $default, $defaultJs, $default));

        // remove default-js.pot
        unlink($defaultJs);
    }
}
