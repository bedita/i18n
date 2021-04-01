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

namespace BEdita\I18n\Shell;

use Cake\Console\Shell;
use Cake\Filesystem\File;
use Cake\Filesystem\Folder;

/**
 * Gettext shell
 */
class GettextShell extends Shell
{
    /**
     * Get the option parser for this shell.
     *
     * @return \Cake\Console\ConsoleOptionParser
     */
    public function getOptionParser()
    {
        $parser = parent::getOptionParser();
        $parser->addSubcommand('update', [
            'help' => 'Update po and pot files',
            'parser' => [
                'description' => [
                    'Create or update i18n files',
                    '`cake gettext update -app <app path>` will update po/pot file for the app',
                    '`cake gettext update -plugin <plugin path>` will update po/pot file for the plugin',
                ],
                'options' => [
                    'app' => [
                        'help' => 'The app path, for i18n update.',
                        'short' => 'a',
                        'required' => false,
                    ],
                    'plugin' => [
                        'help' => 'The plugin path, for i18n update.',
                        'short' => 'p',
                        'required' => false,
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
     * PO file name
     *
     * @var string
     */
    protected $poName = 'default.po';

    /**
     * Update gettext po files
     *
     * @return void
     */
    public function update(): void
    {
        $resCmd = [];
        exec('which msgmerge 2>&1', $resCmd);
        if (empty($resCmd[0])) {
            $this->out('ERROR: msgmerge not available. Please install gettext utilities.');

            return;
        }

        $this->out('Updating .pot and .po files...');

        $this->setupPaths();
        foreach ($this->templatePaths as $path) {
            $this->out(sprintf('Search in: %s', $path));
            $this->parseDir($path);
        }

        $this->out('Creating master .pot file');
        $this->writeMasterPot();
        $this->ttagExtract();

        $this->hr();
        $this->out('Merging master .pot with current .po files');
        $this->hr();

        $this->writePoFiles();

        $this->out('Done');
    }

    /**
     * Setup template paths and locale path
     *
     * @return void
     */
    private function setupPaths(): void
    {
        $basePath = getcwd();
        if (isset($this->params['app'])) {
            $f = new Folder($this->params['app']);
            $basePath = $f->path;
        } elseif (isset($this->params['plugin'])) {
            $startPath = ($this->params['startPath']) ? $this->params['startPath'] : getcwd();
            $f = new Folder(sprintf('%s/plugins/%s', $startPath, $this->params['plugin']));
            $basePath = $f->path;
            $this->poName = $this->params['plugin'] . ".po";
        }

        $this->templatePaths = [
            $basePath . '/src',
            $basePath . '/config',
        ];
        $this->localePath = $basePath . '/src/Locale';
    }

    /**
     * Write `master.pot` file
     *
     * @return void
     */
    private function writeMasterPot(): void
    {
        $potFilename = sprintf('%s/master.pot', $this->localePath);
        $this->out(sprintf('Writing new .pot file: %s', $potFilename));
        $pot = new File($potFilename, true);
        $pot->write($this->header('pot'));
        sort($this->poResult);
        foreach ($this->poResult as $res) {
            if (!empty($res)) {
                $pot->write(sprintf('%smsgid "%s"%smsgstr ""%s', "\n", $res, "\n", "\n"));
            }
        }
        $pot->close();
    }

    /**
     * Write `.po` files
     *
     * @return void
     */
    private function writePoFiles(): void
    {
        $header = $this->header('po');
        $potFilename = sprintf('%s/master.pot', $this->localePath);
        $folder = new Folder($this->localePath);
        $ls = $folder->read();
        foreach ($ls[0] as $loc) {
            if ($loc[0] != '.') { // only "regular" dirs...
                $this->out(sprintf('Language: %s', $loc));
                $poFile = sprintf('%s/%s/%s', $this->localePath, $loc, $this->poName);
                if (!file_exists($poFile)) {
                    $newPoFile = new File($poFile, true);
                    $newPoFile->write($header);
                    $newPoFile->close();
                }
                $this->out(sprintf('Merging %s', $poFile));
                $mergeCmd = sprintf('msgmerge --backup=off -N -U %s %s', $poFile, $potFilename);
                exec($mergeCmd);
                $this->analyzePoFile($poFile);
                $this->hr();
            }
        }
    }

    /**
     * Header lines for po/pot file
     *
     * @param string $type The file type (can be 'po', 'pot')
     * @return string
     *
     * @codeCoverageIgnore
     */
    private function header(string $type = 'po'): string
    {
        $result = sprintf('msgid ""%smsgstr ""%s', "\n", "\n");
        $contents = [
            'po' => [
                'Project-Id-Version' => 'BEdita 4',
                'POT-Creation-Date' => date("Y-m-d H:i:s"),
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
                'POT-Creation-Date' => date("Y-m-d H:i:s"),
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
     * @return void
     */
    private function analyzePoFile($filename): void
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
            $percent = number_format(($translated * 100.) / $numItems, 1);
        }
        $this->out(sprintf('Translated %s of items - %s %s', $translated, $numItems, $percent));
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
        $content = file_get_contents($file);
        if (empty($content)) {
            return;
        }

        if ($extension === 'twig' || $extension === 'php') {
            $this->parseContent($content);
        }
    }

    /**
     * Parse file content and put i18n data in poResult array
     *
     * @param string $content The file content
     * @return void
     */
    private function parseContent($content): void
    {
        $p = preg_quote("(");
        $q1 = preg_quote("'");
        $q2 = preg_quote('"');

        // temporarily replace "\'" with "|||||", fixString will replace "|||||" with "\'"
        // this fixes wrongly matched data in the following regexp
        $content = str_replace("\'", '|||||', $content);

        // looks for __("text to translate",true)
        // or __('text to translate',true), result in matches[1] or in matches[2]
        $rgxp = "/" . "__\s*{$p}\s*{$q2}" . "([^{$q2}]*)" . "{$q2}" . "|" . "__\s*{$p}\s*{$q1}" . "([^{$q1}]*)" . "{$q1}" . "/";
        $matches = [];
        preg_match_all($rgxp, $content, $matches);

        $limit = count($matches[0]);
        for ($i = 0; $i < $limit; $i++) {
            $item = $this->fixString($matches[1][$i]);
            if (empty($item)) {
                $item = $this->fixString($matches[2][$i]);
            }
            if (!in_array($item, $this->poResult)) {
                $this->poResult[] = $item;
            }
        }
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
     * @return void
     *
     * @codeCoverageIgnore
     */
    private function ttagExtract(): void
    {
        // check ttag command exists
        $ttag = 'node_modules/ttag-cli/bin/ttag';
        if (!file_exists($ttag)) {
            $this->out(sprintf('Skip javascript parsing - %s command not found', $ttag));

            return;
        }
        // check template folder exists
        $appDir = 'src/Template';
        if (!file_exists($appDir)) {
            $this->out(sprintf('Skip javascript parsing - %s folder not found', $appDir));

            return;
        }

        // do extract translation strings from js files using ttag
        $this->out('Extracting translation string from javascript files using ttag');
        $masterJs = sprintf('%s/master-js.pot', $this->localePath);
        exec(sprintf('%s extract --o %s --l en %s', $ttag, $masterJs, $appDir));

        // merge master-js.pot and master.pot
        $master = sprintf('%s/master.pot', $this->localePath);
        exec(sprintf('msgcat --use-first %s %s -o %s', $master, $masterJs, $master));

        // remove master-js.pot
        unlink($masterJs);
    }
}
