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

use BEdita\I18n\Core\I18nTrait;
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
    use I18nTrait;

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
     * Return the current URL replacing current lang with new lang passed.
     *
     * @param string $newLang The new lang you want in URL.
     * @param string $switchUrl The switch lang URL defined for this app, if any.
     * @return string
     */
    public function changeUrlLang($newLang, $switchUrl = null) : string
    {
        $request = Router::getRequest(true);
        if (empty($request)) {
            return '';
        }
        $path = $request->getUri()->getPath();
        $query = $request->getUri()->getQuery();

        $newLangUrl = $this->newLangUrl($newLang, $path, $query);
        if ($newLangUrl !== null) {
            return $newLangUrl;
        }

        if (!empty($switchUrl)) {
            return sprintf('%s?new=%s', $switchUrl, $newLang);
        }

        if (!empty($query)) {
            $path .= sprintf('?%s', $query);
        }

        return $path;
    }

    /**
     * Try to create a new language URL from current path using lang prefix.
     *
     * @param string $newLang The new lang you want in URL.
     * @param string $path The current URL path.
     * @param string $query The current URL query.
     * @return string|null The new lang url or null if no lang prefix was found
     */
    protected function newLangUrl($newLang, $path, $query) : ?string
    {
        $prefix = sprintf('/%s', $this->getLang());
        if (stripos($path, $prefix . '/') === 0 || $path === $prefix) {
            $url = sprintf('/%s', $newLang) . substr($path, strlen($prefix));
            if ($query) {
                $url .= '?' . $query;
            }

            return $url;
        }

        return null;
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
    public function field(array $object, string $attribute, ?string $lang = null, bool $defaultNull = false, array $included = []) : ?string
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
     * @return bool
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
