<?php

namespace PhalconRunner;

class AppConfig
{
    /**
     * @var array Config Values
     */
    private static $config;
    
    /**
     * Method to get a Config Value
     * @param string $section Config Key Section
     * @param string $name Name of Config Key
     * @return mixed Config Value
     */
    public static function get($section, $name, $default=NULL)
    {
        if (isset(static::$config->$section->$name)) {
            return static::$config->$section->$name;
        } else {
            return $default;
        }
    }
    
    /**
     * Method to get a Config Section as an array
     * @param string $section Config Key Section
     * @return array Config Value
     */
    public static function getSection($section)
    {
        if (isset(static::$config->$section)) {
            $sectionValues = static::$config->$section;
            return (array)$sectionValues;
        } else {
            throw new Exception('Config Section not found');
        }
    }
    
    /**
     * Method to load Config Values from a .ini file
     * @param string $configIniFile Path to .ini config file
     */
    public static function load($configIniFile)
    {
        if (!empty(static::$config)) {
            throw new Exception('Config has already been loaded');
        }
        
        $values = parse_ini_file($configIniFile, TRUE);
        
        if ($values == FALSE) {
            throw new Exception('Unable to parse config file');
        } else {
            static::$config = json_decode(json_encode($values), FALSE);
        }
    }
    
    
}
