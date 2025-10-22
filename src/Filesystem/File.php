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

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

/**
 * File utilities.
 */
class File
{
    /**
     * Parse a directory, look for files and rips gettext strings (@see parseFile).
     * Files with extensions .php, .ctp, .thtml, .inc, .tpl, .twig are parsed.
     * Returns true if all files are parsed correctly, false otherwise.
     *
     * @param string $dir The directory
     * @param string $defaultDomain The default domain
     * @param array $translations The translations array
     * @return bool
     */
    public static function parseDir(string $dir, string $defaultDomain, array &$translations): bool
    {
        $result = true;
        if (!is_dir($dir)) {
            return false;
        }
        $files = new RegexIterator(
            new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $dir,
                    RecursiveDirectoryIterator::KEY_AS_PATHNAME | RecursiveDirectoryIterator::CURRENT_AS_PATHNAME,
                ),
            ),
            '/.*\.(php|ctp|thtml|inc|tpl|twig)/i',
        );
        foreach ($files as $file) {
            $parseResult = self::parseFile($file, $defaultDomain, $translations);
            $result = $result && $parseResult;
        }

        return $result;
    }

    /**
     * Parse file and rips gettext strings.
     * Returns true if file is parsed correctly, false otherwise.
     *
     * @param string $file The file name
     * @param string $defaultDomain The default domain
     * @param array $translations The translations array
     * @return bool
     */
    public static function parseFile(string $file, string $defaultDomain, array &$translations): bool
    {
        if (!file_exists($file)) {
            return false;
        }
        $content = file_get_contents($file);
        if (empty($content)) {
            return false;
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
                $domain = $defaultDomain;
                $ctx = '';
                $str = self::unquoteString($matches[1][$i]);

                if (strpos($fname, '__d') === 0) {
                    $domain = self::unquoteString($matches[1][$i]);

                    if (strpos($fname, '__dx') === 0) {
                        $ctx = self::unquoteString($matches[2][$i]);
                        $str = self::unquoteString($matches[3][$i]);
                    } else {
                        $str = self::unquoteString($matches[2][$i]);
                    }
                } elseif (strpos($fname, '__x') === 0) {
                    $ctx = self::unquoteString($matches[1][$i]);
                    $str = self::unquoteString($matches[2][$i]);
                }
                $str = self::fixString($str);

                if (!array_key_exists($domain, $translations)) {
                    $translations[$domain] = [];
                }

                if (!array_key_exists($str, $translations[$domain])) {
                    $translations[$domain][$str] = [''];
                }

                if (!in_array($ctx, $translations[$domain][$str])) {
                    $translations[$domain][$str][] = $ctx;
                }
            }
        }

        return true;
    }

    /**
     * Remove leading and trailing quotes from string.
     *
     * @param string $str The string
     * @return string The new string
     */
    public static function unquoteString(string $str): string
    {
        return substr($str, 1, -1);
    }

    /**
     * "fix" string - strip slashes, escape and convert new lines to \n.
     *
     * @param string $str The string
     * @return string The new string
     */
    public static function fixString(string $str): string
    {
        $str = stripslashes($str);
        $str = str_replace('"', '\"', $str);
        $str = str_replace("\n", '\n', $str);
        $str = str_replace('|||||', "'", $str); // special sequence used in parseContent to temporarily replace "\'"

        return $str;
    }
}
