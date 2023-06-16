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

class Paths
{
    /**
     * Setup template and locale paths
     *
     * @param array $templatePaths Template paths
     * @param string $localePath Locale path
     * @param string $defaultDomain Default domain
     * @param array $options Options
     * @return void
     */
    public static function setup(
        array &$templatePaths,
        string &$localePath,
        string &$defaultDomain,
        array $options
    ): void {
        if ($options['plugins'] === true) {
            self::setupPlugins($templatePaths, $localePath);

            return;
        }
        if ($options['plugin'] !== null) {
            self::setupPlugin($templatePaths, $localePath, $defaultDomain, $options['plugin']);

            return;
        }
        $app = $options['app'];
        $basePath = $app ?? getcwd();
        $templatePaths = [$basePath . DS . 'src', $basePath . DS . 'config'];
        $templatePaths = array_merge($templatePaths, App::path(View::NAME_TEMPLATE));
        $templatePaths = array_filter($templatePaths, function ($path) {
            return strpos($path, 'plugins') === false;
        });
        $localePath = (string)Hash::get((array)App::path('locales'), 0);
    }

    /**
     * Setup template paths and locale path for a plugin
     *
     * @param array $templatePaths Template paths
     * @param string $localePath Locale path
     * @param string $defaultDomain Default domain
     * @param array $options Options
     * @return void
     */
    public static function setupPlugin(
        array &$templatePaths,
        string &$localePath,
        string &$defaultDomain,
        string $plugin
    ): void {
        $templatePaths = array_merge(
            [
                Plugin::classPath($plugin),
                Plugin::configPath($plugin),
            ],
            App::path(View::NAME_TEMPLATE, $plugin)
        );
        $defaultDomain = $plugin;
        $localePath = (string)Hash::get((array)App::path('locales', $plugin), '0');
    }

    /**
     * Setup template paths and locale path for all plugins
     *
     * @param array $templatePaths Template paths
     * @param string $localePath Locale path
     * @return void
     */
    public static function setupPlugins(array &$templatePaths, string &$localePath): void
    {
        $pluginsPaths = App::path('plugins');
        $plugins = array_reduce(
            $pluginsPaths,
            fn (array $acc, string $path) => array_merge(
                $acc,
                array_filter(
                    (array)scandir($path),
                    fn ($file) => is_string($file) && !in_array($file, ['.', '..']) && Plugin::getCollection()->has($file)
                )
            ),
            []
        );
        $templatePathsTmp = App::path('templates');
        $templatePathsTmp[] = APP;
        $templatePathsTmp[] = dirname(APP) . DS . 'config';
        $templatePathsTmp = array_reduce(
            $plugins,
            fn (array $acc, string $plugin) => array_merge(
                $acc,
                App::path('templates', $plugin),
                [
                    Plugin::classPath($plugin),
                    dirname(Plugin::classPath($plugin)) . DS . 'config',
                ],
            ),
            $templatePathsTmp
        );
        $templatePaths = $templatePathsTmp;
        $localesPaths = (array)App::path('locales');
        $localePath = (string)Hash::get($localesPaths, 0);
    }
}
