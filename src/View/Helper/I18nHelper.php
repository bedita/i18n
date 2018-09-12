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
     * Translation data per object and lang.
     * Structure:
     *
     *   translation[<object ID>][<lang>][<field>] = <value>.
     *
     * @var array
     */
    protected $translation = [];

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
        $url = Router::getRequest(true)->getUri()->getPath();
        $prefix = sprintf('/%s', $this->getLang());
        if (stripos($url, $prefix . '/') === 0 || $url === $prefix) {
            $url = sprintf('/%s', $newLang) . substr($url, strlen($prefix));
        }

        return $url;
    }

    /**
     * Translate object field
     * Return translation (by response, field and language)
     *
     * @param array $response The response for the object to translate
     * @param string $attribute The attribute name
     * @param string $lang The lang (2 chars string)
     * @param bool $defaultNull Pass true when you want null as default, on missing translation
     * @return string|null
     */
    public function field(array $response, string $attribute, string $lang, bool $defaultNull = false) : ?string
    {
        $defaultValue = null;
        if (!$defaultNull) {
            $defaultValue = Hash::get($response, sprintf('data.attributes.%s', $attribute));
        }
        $returnValue = $this->getTranslatedField($response, $attribute, $lang);
        if ($returnValue === null) {
            return $defaultValue;
        }

        return $returnValue;
    }

    /**
     * Verify that object has translation for the specified attribute and lang
     *
     * @param array $response The response for the object to translate
     * @param string $attribute The attribute name
     * @param string $lang The lang (2 chars string)
     * @return string|null
     */
    public function exists(array $response, string $attribute, string $lang) : bool
    {
        $val = $this->getTranslatedField($response, $attribute, $lang);

        return ($val !== null);
    }

    /**
     * Return translated field per response, attribute and lang. Null on missing translation.
     * First time that it's called per object/response, it fills $this->translation data.
     * I.e.:
     *
     *     $this->translation[100]['en'] = ['title' => 'Example', 'description' => 'This is an example']
     *     $this->translation[100]['it'] = ['title' => 'Esempio', 'description' => 'Questo è un esempio']
     *     $this->translation[100]['sp'] = ['title' => 'Ejemplo', 'description' => 'Este es un ejemplo']
     *
     * @param array $response The response for the object to translate
     * @param string $attribute The attribute name
     * @param string $lang The lang (2 chars string)
     * @return string|null The translation of attribute field per object response and lang
     */
    private function getTranslatedField(array $response, string $attribute, string $lang) : ?string
    {
        if (empty($response)) {
            return null;
        }
        $id = Hash::get($response, 'data.id');
        $path = sprintf('%s.%s.%s', $id, $lang, $attribute);
        if (!Hash::check($this->translation, $path)) {
            if (Hash::check($response, 'included')) {
                foreach ($response['included'] as $included) {
                    if ($included['type'] === 'translations') {
                        $lang = Hash::get($included, 'attributes.lang');
                        $this->translation[$id][$lang] = Hash::get($included, 'attributes.translated_fields');
                        if (!Hash::check($this->translation, sprintf('%s.%s.%s', $id, $lang, $attribute))) { // if field not in translated_fields, set to null
                            $this->translation[$id][$lang][$attribute] = null;
                        }
                    }
                }
            }
            $mainLang = Hash::get($response, 'data.attributes.lang');
            $this->translation[$id][$mainLang] = Hash::get($response, 'data.attributes');
        }

        return Hash::get($this->translation, $path);
    }
}
