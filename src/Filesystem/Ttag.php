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

class Ttag
{
    /**
     * Extract ttag strings from javascript files
     *
     * @param string $localePath The locale path
     * @param string|null $plugin The plugin name, if any
     * @return array
     */
    public static function extract(string $localePath, ?string $plugin = null): array
    {
        $extracted = false;
        $info = [];

        // check ttag command exists
        $ttag = 'node_modules/ttag-cli/bin/ttag';
        if (!file_exists($ttag)) {
            $info[] = sprintf('Skip javascript parsing - %s command not found', $ttag);

            return compact('extracted', 'info');
        }
        // check template folder exists
        $appDir = !empty($plugin) ? Plugin::templatePath($plugin) : Hash::get(App::path(View::NAME_TEMPLATE), 0);
        if (!file_exists($appDir)) {
            $info[] = sprintf('Skip javascript parsing - %s folder not found', $appDir);

            return compact('extracted', 'info');
        }
        // Path to the resources directory defined in cakephp app config/paths.php
        // Do not add RESOURCES path when it's a plugin
        if (empty($plugin) && defined('RESOURCES') && file_exists(RESOURCES)) {
            $appDir = sprintf('%s %s', $appDir, RESOURCES);
        }

        // do extract translation strings from js files using ttag
        $info[] = 'Extracting translation string from javascript files using ttag';
        $defaultJs = sprintf('%s/default-js.pot', $localePath);
        exec(sprintf('%s extract --extractLocation never --o %s --l en %s', $ttag, $defaultJs, $appDir));

        // merge default-js.pot and <plugin>.pot|default.pot
        $potFile = !empty($plugin) && is_string($plugin) ? sprintf('%s.pot', $plugin) : 'default.pot';
        $default = sprintf('%s/%s', $localePath, $potFile);
        exec(sprintf('msgcat --use-first %s %s -o %s', $default, $defaultJs, $default));

        // remove default-js.pot
        unlink($defaultJs);

        $extracted = true;

        return compact('extracted', 'info');
    }
}
