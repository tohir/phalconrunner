<?php

namespace PhalconRunner;

/**
 * Load Factory Classes
 *
 * These are classes that load different 'systems' based on config settings
 * Requires abstract classes and interfaces to be setup
 * Classes and expected to extend $objectType and implement $objectType.'Interface'
 * 
 */
class FactoryLoader
{
    public static function load($objectType, $asSingelton=FALSE, $params='')
    {
        $factoryClass = AppConfig::get('factory_settings', 'template');
        
        if (!class_exists($factoryClass)) {
            throw new \Exception("Could not find class {$factoryClass} for Factory Option: {$objectType}");
        }
        
        $classExtends = class_parents($factoryClass);
        $classInterfaces = class_implements($factoryClass);
        
        if (class_exists($factoryClass) && in_array($objectType, $classExtends) && in_array($objectType.'Interface', $classInterfaces)) {
            if ($asSingelton) {
                return $factoryClass::singleton($params);
            } else {
                return new $factoryClass($params);
            }
        } else{
            throw new \Exception('Invalid Factory Option for: '.$objectType);
        }
        
        
    }
}