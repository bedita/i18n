<?php
declare(strict_types=1);

/**
 * BEdita, API-first content management framework
 * Copyright 2022 Atlas Srl, Chialab Srl
 *
 * This file is part of BEdita: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * See LICENSE.LGPL or <http://gnu.org/licenses/lgpl-3.0.html> for more details.
 */

namespace BEdita\I18n\Command;

use BEdita\I18n\Filesystem\File;
use BEdita\I18n\Filesystem\Gettext;
use BEdita\I18n\Filesystem\Paths;
use BEdita\I18n\Filesystem\Ttag;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Cake\Utility\Hash;

/**
 * Gettext command.
 */
class GettextCommand extends Command
{
    /**
     * @var int
     */
    public const CODE_CHANGES = 2;

    /**
     * The Po results
     *
     * @var array
     */
    protected $poResult = [];

    /**
     * The template paths
     *
     * @var array
     */
    protected $templatePaths = [];

    /**
     * The locale path
     *
     * @var string
     */
    protected $localePath = '';

    /**
     * The name of default domain if not specified. Used for pot and po file names.
     *
     * @var string
     */
    protected $defaultDomain = 'default';

    /**
     * The locales to generate.
     *
     * @var array
     */
    protected $locales = [];

    /**
     * @inheritDoc
     */
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return parent::buildOptionParser($parser)
            ->setDescription([
                'Create or update i18n po/pot files',
                '',
                '`bin/cake gettext`: update files for current app',
                '`bin/cake gettext -app <app path>`: update files for the app',
                '`bin/cake gettext -plugin <plugin name>`: update files for the plugin',
            ])
            ->addOption('app', [
                'help' => 'The app path, for i18n update.',
                'short' => 'a',
                'required' => false,
            ])
            ->addOption('plugin', [
                'help' => 'The plugin name, for i18n update.',
                'short' => 'p',
                'required' => false,
            ])
            ->addOption('plugins', [
                'help' => 'All plugins',
                'required' => false,
                'boolean' => true,
            ])
            ->addOption('ci', [
                'help' => 'Run in CI mode. Exit with error if PO files are changed.',
                'required' => false,
                'boolean' => true,
            ])
            ->addOption('locales', [
                'help' => 'Comma separated list of locales to generate. Leave empty to use configuration `I18n.locales`',
                'short' => 'l',
                'default' => implode(',', array_keys((array)Configure::read('I18n.locales'))),
            ]);
    }

    /**
     * Get po result.
     *
     * @return array
     */
    public function getPoResult(): array
    {
        return $this->poResult;
    }

    /**
     * Get templatePaths.
     *
     * @return array
     */
    public function getTemplatePaths(): array
    {
        return $this->templatePaths;
    }

    /**
     * Get localePath
     *
     * @return string
     */
    public function getLocalePath(): string
    {
        return $this->localePath;
    }

    /**
     * Update gettext po files.
     *
     * @param \Cake\Console\Arguments $args The command arguments.
     * @param \Cake\Console\ConsoleIo $io The console io
     * @return null|void|int The exit code or null for success
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        $resCmd = [];
        exec('which msgmerge 2>&1', $resCmd);
        if (empty($resCmd[0])) {
            $io->abort('ERROR: msgmerge not available. Please install gettext utilities.');
        }

        $io->out('Updating .pot and .po files...');
        $this->locales = array_filter(explode(',', $args->getOption('locales')));
        Paths::setup($this->templatePaths, $this->localePath, $this->defaultDomain, $args->getOptions());
        foreach ($this->templatePaths as $path) {
            $io->out(sprintf('Search in: %s', $path));
            File::parseDir($path, $this->defaultDomain, $this->poResult);
        }

        $io->out('Creating master .pot file');
        $result = Gettext::writeMasterPot($this->localePath, $this->poResult);
        foreach ($result['info'] as $info) {
            $io->out($info);
        }
        $hasChanges = Hash::get($result, 'updated') === true;

        $io->out('Extracting ttag translations from javascript files');
        $result = Ttag::extract($this->localePath, $args->getOption('plugin'));
        foreach ($result['info'] as $info) {
            $io->out($info);
        }
        $io->out(sprintf('Ttag extracted: %s', $result['extracted']));

        $io->hr();
        $io->out('Merging master .pot with current .po files');
        $io->hr();

        $io->out('Writing po files');
        $result = Gettext::writePoFiles($this->locales, $this->localePath, $this->poResult);
        foreach ($result['info'] as $info) {
            $io->out($info);
        }

        $io->out('Done');

        if ($args->getOption('ci') && $hasChanges) {
            return GettextCommand::CODE_CHANGES;
        }

        return GettextCommand::CODE_SUCCESS;
    }
}
