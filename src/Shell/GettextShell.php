<?php
declare(strict_types=1);

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

namespace BEdita\I18n\Shell;

use Cake\Console\ConsoleOptionParser;
use Cake\Console\Shell;
use Cake\Core\App;
use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\Filesystem\File;
use Cake\Filesystem\Folder;
use Cake\I18n\FrozenTime;

/**
 * Gettext shell
 */
class GettextShell extends Shell
{
    /**
     * @var int
     */
    public const CODE_CHANGES = 2;

    /**
     * Get the option parser for this shell.
     *
     * @return \Cake\Console\ConsoleOptionParser
     * @codeCoverageIgnore
     */
    public function getOptionParser(): ConsoleOptionParser
    {
        $parser = parent::getOptionParser();
        $parser->addSubcommand('update', [
            'help' => 'Update po and pot files',
            'parser' => [
                'description' => [
                    'Create or update i18n po/pot files',
                    '',
                    '`cake gettext update`: update files for current app',
                    '`cake gettext update -app <app path>`: update files for the app',
                    '`cake gettext update -plugin <plugin name>`: update files for the plugin',
                ],
                'options' => [
                    'app' => [
                        'help' => 'The app path, for i18n update.',
                        'short' => 'a',
                        'required' => false,
                    ],
                    'includePlugins' => [
                        'help' => 'Discover translations in plugin folders.',
                        'required' => false,
                        'boolean' => true,
                    ],
                    'plugin' => [
                        'help' => 'The plugin name, for i18n update.',
                        'short' => 'p',
                        'required' => false,
                    ],
                    'ci' => [
                        'help' => 'Run in CI mode. Exit with error if PO files are changed.',
                        'required' => false,
                        'boolean' => true,
                    ],
                ],
            ],
        ]);

        return $parser;
    }

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
     * Get po result
     */
    public function getPoResult(): array
    {
        return $this->poResult;
    }

    /**
     * Get templatePaths
     */
    public function getTemplatePaths(): array
    {
        return $this->templatePaths;
    }

    /**
     * Get localePath
     */
    public function getLocalePath(): string
    {
        return $this->localePath;
    }

    /**
     * Update gettext po files
     *
     * @return int
     */
    public function update(): int
    {
        $resCmd = [];
        exec('which msgmerge 2>&1', $resCmd);
        if (empty($resCmd[0])) {
            $this->abort('msgmerge not available. Please install gettext utilities.');
        }

        $this->setupPaths();

        $this->hr();
        $this->out('Paths:');
        $this->hr();
        foreach ($this->templatePaths as $path) {
            $this->out('   ' . $path);
            $this->parseDir($path);
        }

        $this->hr();
        $this->out('Creating .pot file');
        $this->hr();
        $hasChanges = $this->writePotFiles();
        $this->ttagExtract();

        $this->hr();
        $this->out('Merging master .pot with current .po files');
        $this->hr();
        $this->writePoFiles();

        if ($this->param('ci') && $hasChanges) {
            return GettextShell::CODE_CHANGES;
        }

        return GettextShell::CODE_SUCCESS;
    }

    /**
     * Setup template paths and locale path
     *
     * @return void
     */
    protected function setupPaths(): void
    {
        if ($this->param('plugin')) {
            $plugin = (string)$this->param('plugin');
            $this->templatePaths = [
                Plugin::classPath($plugin),
                Plugin::configPath($plugin),
                ...App::path('Template', $plugin),
            ];

            $localesPaths = (array)Configure::read('App.paths.locales');
            foreach ($localesPaths as $path) {
                if (strpos($path, sprintf('%s%s%s', DS, $plugin, DS)) > 0) {
                    $this->localePath = $path;
                    break;
                }
            }

            return;
        }

        $localesPath = App::path('Locale');
        $templatePaths = [
            APP,
            dirname(APP) . DS . 'config',
            ...App::path('Template'),
        ];

        if ($this->param('includePlugins')) {
            $plugins = $this->getPlugins();
            foreach ($plugins as $plugin) {
                $templatePaths = array_merge($templatePaths, [
                    Plugin::classPath($plugin),
                    Plugin::configPath($plugin),
                    ...App::path('Template', $plugin),
                ]);
            }
        }

        $this->templatePaths = $templatePaths;
        $this->localePath = $localesPath[0];
    }

    /**
     * Get a list of loaded plugins at the given path.
     *
     * @param string $path The path to check.
     * @return string[] A list of loaded plugins.
     */
    protected function getLoadedPlugins(string $path): array
    {
        $dirs = scandir($path);

        return array_filter($dirs, fn ($file) => Plugin::getCollection()->has($file));
    }

    /**
     * Get the list of plugins loaded by the application.
     *
     * @return string[] A list of loaded plugins.
     */
    protected function getPlugins(): array
    {
        /**
         * @var array $loadedPlugins
         */
        $loadedPlugins = array_map(
            fn ($path) => $this->getLoadedPlugins($path),
            App::path('Plugin')
        );

        return array_merge(...$loadedPlugins);
    }

    /**
     * Write `.pot` files
     *
     * @return bool True if file was updated, false otherwise
     */
    protected function writePotFiles(): bool
    {
        $updated = false;

        foreach ($this->poResult as $domain => $poResult) {
            $potFilename = sprintf('%s/%s.pot', $this->localePath, $domain);
            $this->out('    ' . $potFilename);
            $pot = new File($potFilename, true);

            $contents = $pot->read();

            // remove headers from pot file
            $contents = preg_replace('/^msgid ""\nmsgstr ""/', '', $contents);
            $contents = trim(preg_replace('/^"([^"]*?)"$/m', '', $contents));

            $lines = [];
            ksort($poResult);
            foreach ($poResult as $res => $contexts) {
                sort($contexts);
                foreach ($contexts as $ctx) {
                    if (!empty($ctx)) {
                        $lines[] = sprintf('msgctxt "%s"%smsgid "%s"%smsgstr ""', $ctx, "\n", $res, "\n");
                    } else {
                        $lines[] = sprintf('msgid "%s"%smsgstr ""', $res, "\n");
                    }
                }
            }

            $result = implode("\n\n", $lines);
            if ($contents !== $result) {
                $pot->write(sprintf("%s\n%s\n", $this->header('pot'), $result));
                $updated = true;
            }

            $pot->close();
        }

        return $updated;
    }

    /**
     * Write `.po` files
     *
     * @return void
     */
    protected function writePoFiles(): void
    {
        $header = $this->header('po');
        $locales = array_keys((array)Configure::read('I18n.locales', []));
        foreach ($locales as $loc) {
            $this->out(sprintf('Language: %s', $loc));

            $potDir = $this->localePath . DS . $loc;
            if (!file_exists($potDir)) {
                mkdir($potDir);
            }

            foreach (array_keys($this->poResult) as $domain) {
                $potFilename = sprintf('%s/%s.pot', $this->localePath, $domain);
                $poFile = sprintf('%s/%s.po', $potDir, $domain);
                if (!file_exists($poFile)) {
                    $newPoFile = new File($poFile, true);
                    $newPoFile->write($header);
                    $newPoFile->close();
                }

                $this->msgMerge($potFilename, $poFile);

                $info = $this->analyzePoFile($poFile);
                $percent = 0;
                if ($info['total'] > 0) {
                    $percent = number_format(($info['total'] - $info['missed']) * 100. / $info['total'], 1);
                }
                $this->out(sprintf('    %s (translated %d of %d items - %s%%)', $poFile, $info['total'] - $info['missed'], $info['total'], $percent));
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
    protected function header(string $type = 'po'): string
    {
        $result = sprintf('msgid ""%smsgstr ""%s', "\n", "\n");
        $contents = [
            'po' => [
                'Project-Id-Version' => 'BEdita 4',
                'POT-Creation-Date' => FrozenTime::now()->format('Y-m-d H:i:s'),
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
                'POT-Creation-Date' => FrozenTime::now()->format('Y-m-d H:i:s'),
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
     * Run the msgmerge command.
     *
     * @param string $input The input .pot file.
     * @param string $output The output .po file.
     * @return int Exit code of the process.
     */
    protected function msgMerge(string $input, string $output): int
    {
        $mergeCmd = sprintf('msgmerge --backup=off -N -U %s %s', $output, $input);
        $process = proc_open($mergeCmd, [
            ['pipe', 'r'],
            ['pipe', 'w'],
            ['pipe', 'w'],
        ], $pipes);

        return proc_close($process);
    }

    /**
     * Analyze po file and translate it
     *
     * @param string $filename The po file name
     * @return array
     */
    protected function analyzePoFile($filename): array
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

        return [
            'total' => $numItems,
            'missed' => $numNotTranslated,
        ];
    }

    /**
     * Remove leading and trailing quotes from string
     *
     * @param string $str The string
     * @return string The new string
     */
    protected function unquoteString($str): string
    {
        return substr($str, 1, -1);
    }

    /**
     * "fix" string - strip slashes, escape and convert new lines to \n
     *
     * @param string $str The string
     * @return string The new string
     */
    protected function fixString($str): string
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
    protected function parseFile($file, $extension)
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
            $capturePath = "'[^']*'";
            $doubleQuoteCapture = str_replace("'", $options['double_quote'], $capturePath);
            $quoteCapture = str_replace("'", $options['quote'], $capturePath);

            // phpcs:disable
            $rgxp = '/' . $fname . '\s*' . $options['open_parenthesis'] . str_repeat('((?:' . $doubleQuoteCapture . ')|(?:' . $quoteCapture . '))\s*[,)]\s*', $singularPosition + 1) . '/';
            // phpcs:enable

            $matches = [];
            preg_match_all($rgxp, $content, $matches);

            $limit = count($matches[0]);
            for ($i = 0; $i < $limit; $i++) {
                $domain = 'default';
                $ctx = '';
                $str = $this->unquoteString($matches[1][$i]);

                if (strpos($fname, '__d') === 0) {
                    $domain = $this->unquoteString($matches[1][$i]);

                    if (strpos($fname, '__dx') === 0) {
                        $ctx = $this->unquoteString($matches[2][$i]);
                        $str = $this->unquoteString($matches[3][$i]);
                    } else {
                        $str = $this->unquoteString($matches[2][$i]);
                    }
                } elseif (strpos($fname, '__x') === 0) {
                    $ctx = $this->unquoteString($matches[1][$i]);
                    $str = $this->unquoteString($matches[2][$i]);
                }

                $str = $this->fixString($str);
                if (empty($str)) {
                    continue;
                }

                if (!array_key_exists($domain, $this->poResult)) {
                    $this->poResult[$domain] = [];
                }

                if (!array_key_exists($str, $this->poResult[$domain])) {
                    $this->poResult[$domain][$str] = [''];
                }

                if (!in_array($ctx, $this->poResult[$domain][$str])) {
                    $this->poResult[$domain][$str][] = $ctx;
                }
            }
        }
    }

    /**
     * Parse a directory
     *
     * @param string $dir The directory
     * @return void
     */
    protected function parseDir($dir): void
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
     * @return void
     * @codeCoverageIgnore
     */
    protected function ttagExtract(): void
    {
        // check ttag command exists
        $ttag = 'node_modules/ttag-cli/bin/ttag';
        if (!file_exists($ttag)) {
            $this->out(sprintf('Skip javascript parsing - %s command not found', $ttag));

            return;
        }
        // check template folder exists
        $appDir = 'src/Template';
        if (!empty($this->params['plugin'])) {
            $startPath = !empty($this->params['startPath']) ? $this->params['startPath'] : getcwd();
            $appDir = sprintf('%s/plugins/%s/src/Template', $startPath, $this->params['plugin']);
        }
        if (!file_exists($appDir)) {
            $this->out(sprintf('Skip javascript parsing - %s folder not found', $appDir));

            return;
        }

        // do extract translation strings from js files using ttag
        $this->out('Extracting translation string from javascript files using ttag');
        $masterJs = sprintf('%s/master-js.pot', $this->localePath);
        exec(sprintf('%s extract --o %s --l en %s', $ttag, $masterJs, $appDir));

        // merge master-js.pot and default.pot
        $master = sprintf('%s/default.pot', $this->localePath);
        exec(sprintf('msgcat --use-first %s %s -o %s', $master, $masterJs, $master));

        // remove master-js.pot
        unlink($masterJs);
    }
}
