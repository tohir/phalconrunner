<?php

/**
 * Abstract Class for Template
 */
abstract class Template
{
    
}

/**
 * Template Interface
 */
interface TemplateInterface
{
    public function __construct($writableFolder);
    
    public function persistTemplateVar($name, $value);
    
    public function loadTemplate($template, array $vars=array(), $cacheId='');
    
    public function templateExists($template);
    
    public function setTemplateDir($template);
}

