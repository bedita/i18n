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

use Cake\Core\App;
use Cake\Core\Plugin;
use Cake\Utility\Hash;
use Cake\View\View;
use Throwable;

/**
 * Ttag utility class.
 *
 * Extract ttag strings from javascript files.
 * Requires ttag-cli to be installed.
 */
class Ttag
{
    /**
     * Extract ttag strings from javascript files.
     * Returns an array with two keys:
     *
     * - extracted: true if extraction was successful, false otherwise
     * - info: an array of strings with info messages
     *
     * @param array $locales The locales
     * @param string $localePath The locale path
     * @param string|null $plugin The plugin name, if any
     * @return array
     */
    public static function extract(array $locales, string $localePath, ?string $plugin = null): array
    {
        $skip = false;
        $info = [];

        // check ttag command exists
        $ttag = 'node_modules/ttag-cli/bin/ttag';
        if (!file_exists($ttag)) {
            $info[] = sprintf('Skip javascript parsing - %s command not found', $ttag);
            $skip = true;
        }

        // check template folder exists
        $appDir = !empty($plugin) ? Plugin::templatePath($plugin) : Hash::get(App::path(View::NAME_TEMPLATE), 0);
        if (!file_exists($appDir)) {
            $info[] = sprintf('Skip javascript parsing - %s folder not found', $appDir);
            $skip = true;
        }

        // do extract translation strings from js files using ttag
        $info[] = 'Extracting translation string from javascript files using ttag' . ($skip ? ' (skipped)' : '');
        $extracted = $skip ? false : self::doExtract($ttag, (string)$appDir, $localePath, $locales, $plugin);

        return compact('extracted', 'info');
    }

    /**
     * Perform ttag extract.
     * Returns true if extraction was successful, false otherwise.
     *
     * @param string $ttag Ttag command
     * @param string $appDir Path to the app directory
     * @param string $localePath Path to the locale directory
     * @param array $locales The locales
     * @param string|null $plugin The plugin name, if any
     * @return bool
     * @codeCoverageIgnore
     */
    public static function doExtract(
        string $ttag,
        string $appDir,
        string $localePath,
        array $locales,
        ?string $plugin = null
    ): bool {
        $result = true;
        try {
            // Path to the resources directory defined in cakephp app config/paths.php
            // Do not add RESOURCES path when it's a plugin
            $useResources = empty($plugin) && defined('RESOURCES') && file_exists(RESOURCES);
            $appDir = $useResources ? sprintf('%s %s', $appDir, RESOURCES) : $appDir;// @phpstan-ignore-line

            $defaultJs = sprintf('%s/default-js.pot', $localePath);
            foreach ($locales as $locale) {
                $lang = substr($locale, 0, 2);
                exec(sprintf('%s extract --extractLocation never --o %s --l %s %s', $ttag, $defaultJs, $lang, $appDir));
            }

            // merge default-js.pot and <plugin>.pot|default.pot
            $potFile = !empty($plugin) && is_string($plugin) ? sprintf('%s.pot', $plugin) : 'default.pot';
            $default = sprintf('%s/%s', $localePath, $potFile);
            exec(sprintf('msgcat --use-first %s %s -o %s', $default, $defaultJs, $default));

            // remove default-js.pot
            unlink($defaultJs);
        } catch (Throwable $e) {
            $result = false;
        }

        return $result;
    }
}
