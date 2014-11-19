<?php

//namespace PhalconRunner;

use \Smarty as Smarty;
use \PhalconRunner\AppConfig as AppConfig;

class SmartyTemplate extends Template implements TemplateInterface
{
    
    /**
     * @var object Smarty Object
     */
    private $sm;
    
    /**
     * @var array List of Variables that must be sent to every template request
     */
    private $persistedVars = array();
    
    /**
     * @var boolean Indicate whether template folder has been provided
     */
    private $templateFolderSet = FALSE;
    
    /**
     * Constructor
     * 
     */
    public function __construct($writableFolder)
    {
        // Create Smarty Object
        $this->sm = new Smarty();
        
        // Setup Folders in Writable Folder
        $this->sm->setCompileDir($writableFolder.AppConfig::get('smarty', 'compileDir'));
        $this->sm->setConfigDir($writableFolder.AppConfig::get('smarty', 'configDir'));
        $this->sm->setCacheDir($writableFolder.AppConfig::get('smarty', 'cacheDir'));
        
        // This prevents undefined index errors if the variable is not defined
        $this->sm->error_reporting = E_ALL & ~E_NOTICE;
    }
    
    public function setTemplateDir($folderPath)
    {
        $this->sm->setTemplateDir($folderPath);
        $this->templateFolderSet = TRUE;
    }
    
    /**
     * Method to add a value to the Persisted List
     * @param string $name Name of the Item
     * @param mixed $value Value of the Item (Can be string or object)
     */
    public function persistTemplateVar($name, $value)
    {
        $this->persistedVars[$name] = $value;
    }
    
    /**
     * Method to load a template
     * @param string $template Path to the Template
     * @param array $vars Optional list of variables to send to template
     * @param string $cacheId Optional Cache Id - Not Used yet
     * @return string
     */
    public function loadTemplate($template, array $vars=array(), $cacheId='')
    {
        if (!$this->templateFolderSet) {
            throw new Exception('Template directory has not been set. $this->template->setTemplateDir($path)');
        }
        
        if (!$this->sm->templateExists($template)) {
            throw new Exception("Template Not Found: {$template}");
        }
        
        $this->sm->clearAllAssign();
        
        if (!empty($this->persistedVars)) {
            foreach($this->persistedVars as $item=>$val)
            {
                $this->sm->assign($item, $val);
            }
        }
        
        if (!empty($vars)) {
            foreach($vars as $item=>$val)
            {
                $this->sm->assign($item, $val);
            }
        }
        
        if (isset($_GET['debug'])) {
            return '<div style="border: 5px dashed blue">'.$this->sm->fetch($template).'</div>';
        } else {
            return $this->sm->fetch($template);
        }
        
    }
    
    /**
     * Method to check whether template exists
     * @param string $template Path to the Template
     * @return boolean
     */
    public function templateExists($template)
    {
        return $this->sm->templateExists($template);
    }
    
    /**
     * @todo Expose more Smarty functionality
     */
    public function registerPlugin($type, $name, $callback)
    {
        $this->sm->registerPlugin($type, $name, $callback);
    }
    
}


