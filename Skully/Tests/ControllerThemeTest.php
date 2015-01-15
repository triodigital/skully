<?php


namespace Skully\Tests;

require_once __DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'antecedent'.DIRECTORY_SEPARATOR.'patchwork'.DIRECTORY_SEPARATOR.'Patchwork.php';

use \org\bovigo\vfs\vfsStream;
use \Skully\Application;
use \Skully\Core\Config;
use \Skully\Core\Controller;
use Skully\Exceptions\PageNotFoundException;

require_once('realpath_custom.php');
require_once('App/include.php');
require_once(dirname(__FILE__).'/functions.php');

class ControllerThemeTest extends \PHPUnit_Framework_TestCase {
    protected $root;
    /**
     * @var Application
     */
    protected $app;

    protected function appStructure()
    {
        $structure = array(
            'App' => array(
                'smarty' => array(
                    'templates_c' => array()
                )
            ),
            'anotherpublic' => array(
                'file' => 'yes'
            ),
            'public' => array(
                'default' => array(
                    'App' => array(
                        'views' => array(
                            'home' => array(
                                'index.tpl' => 'This is app default home',
                                'error.tpl' => '{errorStuff}',
                                'undefinedIndexError.tpl' => '{$stuff["something"]}',
                                'noAction.tpl' => 'No action, but should be visible anyway',
                                '_noAction.tpl' => 'Should be invisible'
                            ),
                            'wrapper' => array(
                                'noController.tpl' => 'No controller, but should be visible anyway',
                                '_noController.tpl' => 'Should be invisible'
                            ),
                            'admin' => array(
                                'home' => array(
                                    'index.tpl' => 'This is app default admin home'
                                )
                            )
                        )
                    ),
                    'resources' => array(
                        'scss' => array(
                            'main.scss' => 'default main scss',
                            'page.scss' => 'default page scss'
                        )
                    )
                ),
                'test' => array(
                    'App' => array(
                        'views' => array(
                            'admin' => array(
                                'home' => array(
                                    'index.tpl' => 'This is app test admin home'
                                )
                            )
                        )
                    ),
                    'resources' => array(
                        'scss' => array(
                            'page.scss' => 'test page scss'
                        )
                    )
                )
            )
        );
        return $structure;
    }
    protected function getApp()
    {
        $structure = $this->appStructure();
        $this->root = vfsStream::setup('root', 777, $structure);
        $config = new Config();
        $config->setProtectedFromArray(array(
            'theme' => 'test',
            'basePath' => vfsStream::url('root'),
            'baseUrl' => 'http://localhost/skully/',
            'languages' => array('en' => array('value' => 'english', 'code' => 'en')),
            'urlRules' => array(
                '' => 'home/index',
                'admin' => 'admin/home/index'
            ),
            'namespace' => 'App',
            'caching' => 0
        ));
        setRealpath();
        return new \App\Application($config);
    }

    public function testSmartyTemplateDir()
    {
        $app = $this->getApp();
        $r = $app->getTemplateEngine()->getTemplateDir();
        $this->assertEquals(replaceSeparators($app->config('basePath').'public/test/App/views/'), replaceSeparators($r['main']));
        $this->assertEquals(replaceSeparators($app->config('basePath').'public/default/App/views/'), replaceSeparators($r['default']));
        unsetRealpath();
    }

    public function testAdditionalTemplateDir()
    {
        $app = $this->getApp();
        $app->getTheme()->setDir(replaceSeparators('vfs://root/anotherpublic'), 'plugin');
        $this->assertEquals('yes', file_get_contents($app->getTheme()->getPath('file')));
    }

    public function testSmartyPluginsDir()
    {
        $app = $this->getApp();
        $r = $app->getTemplateEngine()->getPluginsDir();
        $this->assertEquals(replaceSeparators($app->getRealpath(dirname(__FILE__).'/../').'/Library/Smarty/libs/plugins/'), $r[count($r)-1]);
        $this->assertEquals(replaceSeparators($app->getRealpath(dirname(__FILE__).'/../').'/App/smarty/plugins/'), $r[count($r)-2]);
        unsetRealpath();
    }

    public function testDirSetupCorrect()
    {
        $this->getApp();
        $this->assertTrue(file_exists(replaceSeparators('vfs://root')));

        // This cannot be asserted as it is True on Linux but False on Windows
        // $this->assertFalse(file_exists(replaceSeparators('vfs://root/')));
        unsetRealpath();
    }

    public function testLoadDefaultTheme()
    {
        $app = $this->getApp();
        $this->assertTrue(file_exists('vfs://root/public/test/App/'));
        $this->assertEquals(replaceSeparators('vfs://root/public/test/App/'), $app->getTheme()->getAppPath(''));
        $this->assertEquals(replaceSeparators('vfs://root/public/test/'), $app->getTheme()->getPath(''));
        $this->assertEquals(replaceSeparators('vfs://root/public/default/App/views/home/index.tpl'), $app->getTheme()->getAppPath(replaceSeparators('views/home/index.tpl')));
        $this->assertEquals(replaceSeparators('vfs://root/public/test/App/views/admin/home/index.tpl'), $app->getTheme()->getAppPath(replaceSeparators('views/admin/home/index.tpl')));
        /**@var Controller $controller **/
        $controller = new \App\Controllers\HomeController($app, 'index');
        $this->assertEquals('This is app default home', $controller->fetch('/home/index'));

        ob_start();
        $controller->render();
        $output = ob_get_clean();
        //$this->assertEquals('This is app default home', $output);
        $controller = new \App\Controllers\Admin\HomeController($app, 'index');
        $this->assertEquals('This is app test admin home', $controller->fetch());

        unsetRealpath();
    }

    public function xtestAppRunControllerFromRawUrl()
    {
        $app = $this->getApp();
        ob_start();
        $app->runControllerFromRawUrl('');
        $output = ob_get_clean();
        $this->assertEquals('This is app default home', $output);
        unsetRealpath();
    }

    /**
     * @expectedException \Skully\Exceptions\InvalidTemplateException
     * @expectedExceptionCode 99
     */
    public function xtestSmartyInvalidTemplateError()
    {
        $app = $this->getApp();
        $app->runControllerFromRawUrl('home/error');
        unsetRealpath();
    }

//    todo: For some reason when CommandTest and ImageTest enabled, this won't pass.
//    /**
//     * @expectedException \Skully\Exceptions\InvalidTemplateException
//     * @expectedExceptionCode 1
//     */
//    public function xtestSmartyInvalidTemplateUndefinedIndex()
//    {
//        $app = $this->getApp();
//        ob_start();
//        $app->runControllerFromRawUrl('home/undefinedIndexError');
//        $output = ob_get_clean();
//        $this->assertEquals('', $output);
//        unsetRealpath();
//    }

    public function xtestNoActionVisible()
    {
        $app = $this->getApp();
        ob_start();
        $app->runControllerFromRawUrl('home/noAction');
        $output = ob_get_clean();
        $this->assertEquals('No action, but should be visible anyway', $output);
    }

    /**
     * @expectedException \Skully\Exceptions\PageNotFoundException
     */
    public function xtestNoActionInvisible()
    {
        $app = $this->getApp();
        ob_start();
        $app->runControllerFromRawUrl('home/_noAction');
        ob_clean();
    }

    /**
     * @expectedException \Skully\Exceptions\PageNotFoundException
     */
    public function xtestNoActionNotFound()
    {
        $app = $this->getApp();
        ob_start();
        $app->runControllerFromRawUrl('home/notFound');
        ob_clean();
    }

    public function xtestNoActionInvisibleUrl()
    {
        $app = $this->getApp();
        try {
            $app->runControllerFromRawUrl('home/_notFound?something=1');
        }
        catch (PageNotFoundException $e) {
            $this->assertEquals('home/_notFound', $e->getRoute());
            $this->assertEquals('http://localhost/skully/home/_notFound?something=1', $e->getUrl());
        }
    }

    /**
     * This is why you must add '_' to any views you do not want to directly accessible via
     * URL, even for wrapper views.
     */
    public function xtestNoControllerVisible()
    {
        $app = $this->getApp();
        ob_start();
        $app->runControllerFromRawUrl('wrapper/noController');
        $output = ob_get_clean();
        $this->assertEquals('No controller, but should be visible anyway', $output);
    }

    /**
     * @expectedException \Skully\Exceptions\PageNotFoundException
     */
    public function xtestNoControllerInvisible()
    {
        $app = $this->getApp();
        ob_start();
        $app->runControllerFromRawUrl('wrapper/_noController');
        ob_clean();
    }

    public function xtestNoControllerInvisibleUrl()
    {
        $app = $this->getApp();
        try {
            $app->runControllerFromRawUrl('wrapper/_noController?something=1');
        }
        catch (PageNotFoundException $e) {
            $this->assertEquals('wrapper/_noController', $e->getRoute());
            $this->assertEquals('http://localhost/skully/wrapper/_noController?something=1', $e->getUrl());
        }
    }
}
