<?php
declare(strict_types=1);

/**
 * BEdita, API-first content management framework
 * Copyright 2018 ChannelWeb Srl, Chialab Srl
 *
 * This file is part of BEdita: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * See LICENSE.LGPL or <http://gnu.org/licenses/lgpl-3.0.html> for more details.
 */
namespace BEdita\I18n\Core;

use Cake\Core\Configure;
use Cake\I18n\I18n;
use Cake\Utility\Hash;

/**
 * Trait that helps with I18n stuff as locale and language configuration.
 */
trait I18nTrait
{
    /**
     * Proxy to `\Cake\I18n\I18n::getLocale()`.
     * Return the currently configure locale as stored in the `intl.default_locale` PHP setting.
     *
     * @return string The name of the default locale.
     *
     * @codeCoverageIgnore
     */
    public function getLocale(): string
    {
        return I18n::getLocale();
    }

    /**
     * Return the configured supported locales
     *
     * @return array
     *
     * @codeCoverageIgnore
     */
    public function getLocales(): array
    {
        return (array)Configure::read('I18n.locales', []);
    }

    /**
     * Return an array of available languages.
     *
     * @return array
     *
     * @codeCoverageIgnore
     */
    public function getLanguages(): array
    {
        return (array)Configure::read('I18n.languages', []);
    }

    /**
     * Return the current lang usually set by `\BEdita\I18n\Middleware\I18nMiddleware`
     *
     * @return string|null
     *
     * @codeCoverageIgnore
     */
    public function getLang(): ?string
    {
        return Configure::read('I18n.lang');
    }

    /**
     * Return the language name as configured.
     *
     * @param string $lang The abbreviated lang
     * @return string|null
     */
    public function getLangName($lang = null): ?string
    {
        if (empty($lang)) {
            $lang = Configure::read('I18n.default');
        }

        return Hash::get($this->getLanguages(), $lang);
    }

    /**
     * Return the default lang as configured.
     *
     * @return string|null
     *
     * @codeCoverageIgnore
     */
    public function getDefaultLang(): ?string
    {
        return Configure::read('I18n.default');
    }
}
