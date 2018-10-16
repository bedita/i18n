<?php
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
namespace BEdita\I18n\View\Helper;

use Cake\Core\Configure;
use Cake\I18n\I18n;
use Cake\Routing\Router;
use Cake\Utility\Hash;
use Cake\View\Helper;

/**
 * Helper to handle i18n things in view.
 */
class I18nHelper extends Helper
{
    /**
     * Translation data per object and lang (internal cache).
     * If `null` no cache has been created, if empty array no translations
     * have been found.
     *
     * Structure:
     *
     *   translation[<object ID>][<lang>][<field>] = <value>.
     *
     *
     * @var array|null
     */
    protected $translation = null;

    /**
     * Proxy to `\Cake\I18n\I18n::getLocale()`.
     * Return the currently configure locale as stored in the `intl.default_locale` PHP setting.
     *
     * @return string The name of the default locale.
     *
     * @codeCoverageIgnore
     */
    public function getLocale() : string
    {
        return I18n::getLocale();
    }

    /**
     * Return an array of available languages.
     *
     * @return array
     *
     * @codeCoverageIgnore
     */
    public function getLanguages() : array
    {
        return (array)Configure::read('I18n.languages');
    }

    /**
     * Return the current lang usually set by `\BEdita\I18n\Middleware\I18nMiddleware`
     *
     * @return string|null
     *
     * @codeCoverageIgnore
     */
    public function getLang() : ?string
    {
        return Configure::read('I18n.lang');
    }

    /**
     * Return the language name as configured.
     *
     * @param string $lang The abbreviated lang
     * @return string|null
     */
    public function getLangName($lang = null) : ?string
    {
        if (empty($lang)) {
            $lang = Configure::read('I18n.default');
        }

        return Hash::get($this->getLanguages(), $lang);
    }

    /**
     * Return the current URL replacing current lang with new lang passed.
     *
     * @param string $newLang The new lang you want in URL.
     * @return string
     */
    public function changeUrlLang($newLang) : string
    {
        $uri = Router::getRequest(true)->getUri();
        $url = $uri->getPath();
        $prefix = sprintf('/%s', $this->getLang());
        if (stripos($url, $prefix . '/') === 0 || $url === $prefix) {
            $url = sprintf('/%s', $newLang) . substr($url, strlen($prefix));
        }
        if ($uri->getQuery()) {
            $url .= '?' . $uri->getQuery();
        }

        return $url;
    }

    /**
     * Translate object field
     * Return translation (by response object and included data, field and language)
     *
     * @param array $object The object to translate
     * @param string $attribute The attribute name
     * @param string|null $lang The lang (2 chars string)
     * @param bool $defaultNull Pass true when you want null as default, on missing translation
     * @param array $included The included translations data
     * @return string|null
     */
    public function field(array $object, string $attribute, ?string $lang = null, bool $defaultNull = false, array &$included = []) : ?string
    {
        $defaultValue = null;
        if (!$defaultNull) {
            $defaultValue = Hash::get($object, sprintf('attributes.%s', $attribute), Hash::get($object, sprintf('%s', $attribute)));
        }
        if (empty($included) && !empty($this->getView()->viewVars['included'])) {
            $included = $this->getView()->viewVars['included'];
        }
        if (empty($lang)) {
            $lang = Configure::read('I18n.lang', '');
        }
        $returnValue = $this->getTranslatedField($object, $attribute, $lang, $included);
        if ($returnValue === null) {
            return $defaultValue;
        }

        return $returnValue;
    }

    /**
     * Verify that object has translation for the specified attribute and lang
     *
     * @param array $object The object to translate
     * @param string $attribute The attribute name
     * @param string|null $lang The lang (2 chars string
     * @param array $included The included translations data)
     * @return string|null
     */
    public function exists(array $object, string $attribute, ?string $lang = null, array &$included = []) : bool
    {
        if (empty($included) && !empty($this->getView()->viewVars['included'])) {
            $included = $this->getView()->viewVars['included'];
        }
        if (empty($lang)) {
            $lang = Configure::read('I18n.lang', '');
        }
        $val = $this->getTranslatedField($object, $attribute, $lang, $included);

        return ($val !== null);
    }

    /**
     * Reset internal translation cache.
     * To use when `included` array has changed.
     *
     * @return void
     */
    public function reset() : void
    {
        $this->translation = null;
    }

    /**
     * Return translated field per response object and included, attribute and lang. Null on missing translation.
     * First time that it's called per response object and included, it fills $this->translation data.
     * I.e.:
     *
     *     $this->translation[100]['en'] = ['title' => 'Example', 'description' => 'This is an example']
     *     $this->translation[100]['it'] = ['title' => 'Esempio', 'description' => 'Questo è un esempio']
     *     $this->translation[100]['sp'] = ['title' => 'Ejemplo', 'description' => 'Este es un ejemplo']
     *
     * @param array $object The object to translate
     * @param string $attribute The attribute name
     * @param string $lang The lang (2 chars string)
     * @param array $included The included translations data
     * @return string|null The translation of attribute field per object response and lang
     */
    private function getTranslatedField(array $object, string $attribute, string $lang, array &$included) : ?string
    {
        if (empty($object['id'])) {
            return null;
        }

        $id = $object['id'];

        if ($this->translation === null) {
            $translations = Hash::combine($included, '{n}.id', '{n}.attributes', '{n}.type');
            $this->translation = Hash::combine(
                $translations,
                'translations.{n}.lang',
                'translations.{n}.translated_fields',
                'translations.{n}.object_id'
            );
        }

        $path = sprintf('%s.%s.%s', $id, $lang, $attribute);

        return Hash::get($this->translation, $path);
    }
}
