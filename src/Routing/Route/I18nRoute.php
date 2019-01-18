<?php
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
use Cake\Core\Configure;
use Cake\Routing\Route\Route;
use Psr\Http\Message\ServerRequestInterface;

/**
 * I18n Route class.
 *
 * It helps to build and match I18n routing rules.
 */
class I18nRoute extends Route
{
    use I18nTrait;

    /**
     * {@inheritDoc}
     */
    public function __construct($template, $defaults = [], array $options = [])
    {
        parent::__construct($this->buildTemplate($template), $defaults, $options);

        if (empty($options['lang'])) {
            $this->setPatterns(['lang' => implode('|', array_keys($this->getLanguages()))]);
        }
    }

    /**
     * Build the right route template adding :lang if needed.
     *
     * If :lang is not found add it at the beginning, for example /simple/path becomes /:lang/simple/path
     *
     * @param string $template The initial template.
     * @return string
     */
    protected function buildTemplate(string $template) : string
    {
        if ($template === '/') {
            return '/:lang';
        }

        if (preg_match('/\/:lang(\/.*|$)/', $template)) {
            return $template;
        }

        return $template = '/:lang' . $template;
    }

    /**
     * {@inheritDoc}
     */
    public function match(array $url, array $context = [])
    {
        if (!array_key_exists('lang', $url)) {
            $url['lang'] = $this->getLang();
        }

        return parent::match($url, $context);
    }
}
