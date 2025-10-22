<?php
declare(strict_types=1);

/**
 * BEdita, API-first content management framework
 * Copyright 2023 Atlas Srl, Chialab Srl
 *
 * This file is part of BEdita: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * See LICENSE.LGPL or <http://gnu.org/licenses/lgpl-3.0.html> for more details.
 */

namespace BEdita\I18n\Filesystem;

use Cake\I18n\FrozenTime;

/**
 * Gettext utilities.
 *
 * This class contains methods to analyze and create po/pot files.
 * It is used by the shell tasks.
 */
class Gettext
{
    /**
     * Analyze po file and translate it.
     * Returns an array with the following keys:
     *
     * - numItems: number of items
     * - numNotTranslated: number of not translated items
     * - translated: number of translated items
     * - percent: percentage of translated items
     *
     * @param string $filename The po file name
     * @return array
     */
    public static function analyzePoFile($filename): array
    {
        $lines = file($filename);
        $numItems = $numNotTranslated = 0;
        foreach ($lines as $k => $l) {
            if (strpos($l, 'msgid "') === 0) {
                $numItems++;
            }
            if (strpos($l, 'msgstr ""') === 0 && (!isset($lines[$k + 1]) || strpos($lines[$k + 1], '"') !== 0)) {
                $numNotTranslated++;
            }
        }
        $translated = $numItems - $numNotTranslated;
        $percent = $numItems === 0 ? 0 : number_format($translated * 100. / $numItems, 1);

        return compact('numItems', 'numNotTranslated', 'translated', 'percent');
    }

    /**
     * Header lines for po/pot file.
     * Returns the header string.
     *
     * @param string $type The file type (can be 'po', 'pot')
     * @return string
     */
    public static function header(string $type = 'po'): string
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
     * Write `master.pot` file with all translations.
     * Returns an array with the following keys:
     *
     * - info: array of info messages
     * - updated: boolean, true if file has been updated
     *
     * @param string $localePath Locale path
     * @param array $translations Translations
     * @return array
     */
    public static function writeMasterPot(string $localePath, array $translations): array
    {
        $info = [];
        $updated = false;

        foreach ($translations as $domain => $poResult) {
            $potFilename = sprintf('%s/%s.pot', $localePath, $domain);
            $info[] = sprintf('Writing new .pot file: %s', $potFilename);
            $contents = file_exists($potFilename) ? file_get_contents($potFilename) : '';

            // remove headers from pot file
            $contents = preg_replace('/^msgid ""\nmsgstr ""/', '', $contents);
            $contents = trim(preg_replace('/^"([^"]*?)"$/m', '', $contents));

            $lines = [];
            ksort($poResult);
            foreach ($poResult as $res => $contexts) {
                sort($contexts);
                foreach ($contexts as $ctx) {
                    $msgctxt = sprintf('msgctxt "%s"%smsgid "%s"%smsgstr ""', $ctx, "\n", $res, "\n");
                    $msgidstr = sprintf('msgid "%s"%smsgstr ""', $res, "\n");
                    $lines[] = !empty($ctx) ? $msgctxt : $msgidstr;
                }
            }

            $result = implode("\n\n", $lines);
            if ($contents !== $result) {
                file_put_contents($potFilename, sprintf("%s\n%s\n", self::header('pot'), $result));
                $updated = true;
            }
        }

        return compact('info', 'updated');
    }

    /**
     * Write `.po` files for each locale.
     * Returns an array with the following keys:
     *
     * - info: array of info messages
     *
     * @param array $locales Locales
     * @param string $localePath Locale path
     * @param array $translations Translations
     * @return array
     */
    public static function writePoFiles(array $locales, string $localePath, array &$translations): array
    {
        $info = [];
        if (empty($locales)) {
            $info[] = 'No locales set, .po files generation skipped';

            return compact('info');
        }

        $header = self::header('po');
        foreach ($locales as $loc) {
            $potDir = $localePath . DS . $loc;
            if (!file_exists($potDir)) {
                mkdir($potDir);
            }
            $info[] = sprintf('Language: %s', $loc);
            foreach (array_keys($translations) as $domain) {
                $potFilename = sprintf('%s/%s.pot', $localePath, $domain);
                $poFile = sprintf('%s/%s.po', $potDir, $domain);
                if (!file_exists($poFile)) {
                    $newPoFile = new \SplFileInfo($poFile);
                    $newPoFile->openFile('w')->fwrite($header);
                }
                $info[] = sprintf('Merging %s', $poFile);
                $mergeCmd = sprintf('msgmerge --backup=off -N -U %s %s', $poFile, $potFilename);
                exec($mergeCmd);
                $analysis = self::analyzePoFile($poFile);
                $info[] = sprintf(
                    'Translated %d of %d items - %s %%',
                    $analysis['translated'],
                    $analysis['numItems'],
                    $analysis['percent']
                );
                $info[] = '---------------------';
            }
        }

        return compact('info');
    }
}
