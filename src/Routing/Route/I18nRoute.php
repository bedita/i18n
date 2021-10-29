<?php
declare(strict_types=1);

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
namespace BEdita\I18n\Routing\Route;

use BEdita\I18n\Core\I18nTrait;
use Cake\Routing\Route\Route;

/**
 * I18n Route class.
 *
 * It helps to build and match I18n routing rules.
 */
class I18nRoute extends Route
{
    use I18nTrait;

    /**
     * Language string used in route template.
     *
     * @var string
     */
    public const LANG_STRING = 'lang';

    /**
     * The placeholder to use in route template.
     *
     * @var string
     */
    protected $placeholder = null;

    /**
     * @inheritDoc
     */
    public function __construct($template, $defaults = [], array $options = [])
    {
        parent::__construct($this->buildTemplate($template), $defaults, $options);

        if (empty($options['lang'])) {
            $this->setPatterns(['lang' => implode('|', array_keys($this->getLanguages()))]);
        }
    }

    /**
     * Build the right route template adding {lang} or :lang if needed.
     *
     * If {lang} or :lang is not found add it at the beginning, for example /simple/path becomes /{lang}/simple/path
     *
     * @param string $template The initial template.
     * @return string
     */
    protected function buildTemplate(string $template): string
    {
        if ($template === '/') {
            return '/{lang}';
        }

        $this->setPlaceholder($template);

        $path = sprintf('/\/%s(\/.*|$)/', $this->getSearchPattern());
        if (preg_match($path, $template)) {
            return $template;
        }

        return sprintf('/%s%s', $this->placeholder, $template);
    }

    /**
     * Set the right placeholder style.
     * If it's present some placeholder in old colon style it uses `:lang`
     * else it uses the braces style `{lang}`.
     *
     * @param string $template The template to analyze
     * @return void
     */
    protected function setPlaceholder(string $template): void
    {
        $placeholder = '{%s}';
        if (preg_match('/:([a-z0-9-_]+(?<![-_]))/i', $template)) {
            $placeholder = ':%s';
        }

        $this->placeholder = sprintf($placeholder, static::LANG_STRING);
    }

    /**
     * Get search pattern used to know if lang pattern is already present in template.
     *
     * @return string
     */
    protected function getSearchPattern(): string
    {
        if (strpos($this->placeholder, ':' . static::LANG_STRING) === 0) {
            return $this->placeholder;
        }

        return sprintf('\{%s\}', static::LANG_STRING);
    }

    /**
     * @inheritDoc
     */
    public function match(array $url, array $context = []): ?string
    {
        if (!array_key_exists('lang', $url)) {
            $url['lang'] = $this->getLang();
        }

        return parent::match($url, $context) ?: null;
    }
}
