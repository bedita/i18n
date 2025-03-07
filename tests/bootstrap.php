<?php
declare(strict_types=1);

// phpcs:ignoreFile

use BEdita\I18n\I18nPlugin;
use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\Datasource\ConnectionManager;
use Cake\Routing\Router;

/**
 * Test suite bootstrap for BEdita/I18n.
 *
 * This function is used to find the location of CakePHP whether CakePHP
 * has been installed as a dependency of the plugin, or the plugin is itself
 * installed as a dependency of an application.
 */
$findRoot = function ($root) {
    do {
        $lastRoot = $root;
        $root = dirname($root);
        if (is_dir($root . '/vendor/cakephp/cakephp')) {
            return $root;
        }
    } while ($root !== $lastRoot);
    throw new Exception('Cannot find the root of the application, unable to run tests');
};
$root = $findRoot(__FILE__);
unset($findRoot);
chdir($root);

require_once 'vendor/autoload.php';

define('ROOT', $root . DS . 'tests' . DS . 'test_app' . DS);
define('APP', ROOT . 'TestApp' . DS);
define('CONFIG', ROOT . 'config' . DS);
define('TMP', sys_get_temp_dir() . DS);
define('CACHE', TMP . 'cache' . DS);
define('LOGS', TMP . 'logs' . DS);

//used by Cake\Command\HelpCommand
define('CORE_PATH', $root . DS . 'vendor' . DS . 'cakephp' . DS . 'cakephp' . DS);

// Enable strict_variables Twig configuration
Configure::write('Bake.twigStrictVariables', true);

Configure::write('debug', true);
Configure::write('App', [
    'debug' => true,
    'namespace' => 'App',
    'encoding' => 'utf-8',
    'paths' => [
        'locales' => [
            APP . 'Locale' . DS,
            ROOT . 'plugins' . DS . 'Dummy' . DS . 'Locale' . DS,
        ],
        'plugins' => [ROOT . 'plugins' . DS],
        'templates' => [
            APP . 'Template' . DS,
            ROOT . 'plugins' . DS . 'Dummy' . DS . 'Template' . DS,
        ],
    ],
    'base' => '',
    'webroot' => '/',
]);

Cache::setConfig([
    '_cake_core_' => [
        'engine' => 'File',
        'prefix' => 'cake_core_',
        'serialize' => true,
    ],
    '_cake_model_' => [
        'engine' => 'File',
        'prefix' => 'cake_model_',
        'serialize' => true,
    ],
]);

if (!getenv('db_dsn')) {
    putenv('db_dsn=sqlite:///:memory:');
}
ConnectionManager::setConfig('test', ['url' => getenv('db_dsn')]);
Router::reload();
Router::fullBaseUrl('http://localhost');

Plugin::getCollection()->add(new I18nPlugin(['middleware' => true]));
