<?php

namespace PhalconRunner;

use PhalconRunner\FactoryLoader as FactoryLoader;
use PhalconRunner\AppConfig as AppConfig;

abstract class PhalconRunner
{
    
    /**
     * @var object Phalcon MVC Micro Object
     */
    private $phalcon;
    
    /**
     * @var array List of Routes, with Callback, Request Method, Conditionals
     */
    private $routes;
    
    /**
     * @var object Template Object
     */
    protected $template;
    
    /**
     * @var string Layout Template Filename
     */
    private $layoutTemplate = NULL;
    
    /**
     * @var string Page Template Filename
     */
    private $pageTemplate = NULL;
    
    /**
     * Constructor
     *
     * @param string $configFile Path to .ini config file
     * @param string $writableFolder Path to folder with write permissions
     */
    public function __construct($configFile, $writableFolder)
    {
        AppConfig::load($configFile);
        
        if (!is_writable($writableFolder)) {
            throw new Exception('Folder is not writable');
        }
        
        date_default_timezone_set(AppConfig::get('datetime', 'timezone', 'GMT'));
        
        $this->phalcon = $this->createPhalcon();
        
        $this->template = FactoryLoader::load('Template', FALSE, $writableFolder);
        
        $this->init();
    }
    
    /**
     * This function needs to be overridden to create the routes
     */
    abstract protected function init();
    
    /**
     * Method to create the Phalcon MVC Micro Object
     * @return object Phalcon MVC Micro Object
     */
    private function createPhalcon()
    {
        $di = new \Phalcon\DI\FactoryDefault();
        
        if (AppConfig::get('pp', 'usePhalconDatabaseObject') == 'on') {
            //Set up the database service
            $di->set('db', function(){
                return new \Phalcon\Db\Adapter\Pdo\Mysql(AppConfig::getSection('database'));
            });
        }
        
        return new \Phalcon\Mvc\Micro($di);
    }
    
    public function run()
    {
        $this->phalcon->handle();
    }
    
    /**
     * Method to register Application Routes
     * Uses GET by default
     *
     * Note: Unlike Slim, Phalcon appends conditionals to the route
     * 
     * @param array $route List of Routes containing: route, accessChecks, function, method
     * 
     * @example
     * $this->registerRoutes(array(
     *     array('/', NULL, 'home'),
     *     array('/openclipart/{term}' , 'loginRequired', 'openclipart'),
     *     array('/openclipart/{term}/{page:[0-9]+}', 'loginRequired:1|anotherCheck', 'openclipart', 'get')
     * ));
     *
     */
    protected function registerRoutes(array $routes)
    {
        // Check whether routes have been setup already
        if ($this->routes !== null) {
            throw new Exception('Routes have already been registered');
        }
        
        $this->routes = $routes;
        
        $app = $this; // Needed because '$this' is disallowed in closures
        
        // Setup 404 Page Handler
        $this->phalcon->notFound(function () use ($app) {
            $app->setStatusCode(404, "Not Found");
            return $app->show404Page();
        });
        
        foreach ($routes as $route) {
            list($routePattern, $accessChecks, $methodName) = $route;
            
            // Routes defaults to get if not specified
            $methodList = isset($route[3]) ? $route[3] : 'get';
            $methods = explode('|', $methodList);
            
            // Create call back
            $callback = function() use ($app, $accessChecks, $methodName) {
                $app->dispatch($methodName, $accessChecks, func_get_args());
            };
            
            // Attach to Phalcon
            foreach ($methods as $method) {
                $this->phalcon->$method($routePattern, $callback);
            }
        }
    }
    
    /**
     * Method to run all the accessChecks for Routes
     *
     * Multiple accessChecks are separated by pipes '|', parameters are separated by colons ':'
     * Example loginRequired:1|adminAccess
     *
     * For boolean parameters, use '1' instead of 'true'.
     *
     * Lastly the accesscheck methods in the class should be prepended by accesscheck_
     */
    private function runAccessChecks($accessChecks)
    {
        $accessChecks = explode('|', $accessChecks);
        
        foreach ($accessChecks as $accessCheck)
        {
            $params = explode(':', $accessCheck);
            $method = $params[0]; unset($params[0]);
            
            if (!empty($method)) {
                call_user_func_array(array($this,'accesscheck_'.$method), $params);
            }
        }
        
        return;
    }
    
    /**
     * Method to dispatch request to method with request type
     * @oaram string $method
     */
    public function dispatch($methodName, $accessChecks, $methodArgs)
    {
        $object =& $this;
        
        $this->runAccessChecks($accessChecks);
        
        $response = call_user_func_array(
            array($object, $methodName.'_'.strtolower($this->phalcon->request->getMethod())),
            $methodArgs
        );
        
        if (!empty($this->layoutTemplate)) {
            $response = $this->template->loadTemplate($this->layoutTemplate, array('content'=>$response));
        }
        
        if (!empty($this->pageTemplate)) {
            $response = $this->template->loadTemplate($this->pageTemplate, array('content'=>$response));
        }
        
        echo $response;
    }
    
    /**
     * 404 Page Hander - Should be overridden to display better/custom message
     */
    protected function show404Page()
    {
        echo '<h1>Page Not Found</h1>';
    }
    
    /**
     * Set HTTP Status Code
     *
     * @param int    $statusCode   200, 500, 403, etc
     * @param string $message
     */
    protected function setStatusCode($statusCode, $message)
    {
        $this->phalcon->response->setStatusCode($statusCode, $message)->sendHeaders();
    }
    
    /**
     * Method to Redirect to Another Route
     *
     * @param string $redirect Route to Redirect To
     * @param int $status HTTP Status Code
     */
    protected function redirect($redirect)
    {
        // Remove first slash because Phalcon doubles it
        $redirect = ($redirect[0] == '/') ? substr($redirect, 1) : $redirect;
        
        $this->phalcon->response->redirect($redirect)->sendHeaders();
    }
    
    /**
     * Method to set the page template
     * @param string $template Path to the page template
     */
    protected function setPageTemplate($template)
    {
        $this->pageTemplate = $template;
    }
    
    /**
     * Method to set the page template
     * @param string $template Path to the page template
     */
    protected function setLayoutTemplate($template)
    {
        $this->layoutTemplate = $template;
    }
    
    /**
     * Method that can be used to set Ajax Response
     * Effectively, just turns off the page and layout template
     */
    protected function setIsAjaxResponse()
    {
        $this->setLayoutTemplate(NULL);
        $this->setPageTemplate(NULL);
    }
    
    /**
     * Method to get a GET Value
     * @param string $name Name of the item
     * @param mixed $default Default value to be used if not set
     */
    protected function getValue($name, $default='')
    {
        if (isset($_GET[$name])) {
            return $_GET[$name];
        } else {
            return $default;
        }
    }
    
    /**
     * Method to get a POST Value
     * @param string $name Name of the item
     * @param mixed $default Default value to be used if not set
     */
    protected function postValue($name, $default='')
    {
        if (isset($_POST[$name])) {
            return $_POST[$name];
        } else {
            return $default;
        }
    }
    
    /**
     * Method to get a $_SESSION Value
     * @param string $name Name of the item
     * @param mixed $default Default value to be used if not set
     */
    protected function sessionValue($name, $default='')
    {
        if (isset($_SESSION[$name])) {
            return $_SESSION[$name];
        } else {
            return $default;
        }
    }
    
    /**
     * Method to set a $_SESSION Value
     * @param string $name Name of the Session Var
     * @param mixed $value Session Var Value
     */
    protected function setSessionValue($name, $value)
    {
        if (!isset($_SESSION)) { session_start(); }
        
        $_SESSION[$name] = $value;
    }
    
    /**
     * Method to unset a $_SESSION Value
     * @param string $name Name of the Session Var
     */
    protected function unsetSessionValue($name)
    {
        if (!isset($_SESSION)) { session_start(); }
        
        unset($_SESSION[$name]);
    }
}
