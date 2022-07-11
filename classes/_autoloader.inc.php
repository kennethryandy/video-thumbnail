<?php
if (!defined('ABSPATH')) 
    exit;

    
//
// Global Autoloader to pre-load classes on demand.
//
spl_autoload_register(function($class)
{
    static $classes = null;
    if ($classes === null)
    {
        $rootPath = dirname(__FILE__) . '/';
        
        $classes = array(

			'App_Admin'  => $rootPath . 'class_app_admin.php',
			'App_Core'   => $rootPath . 'class_app_core.php',
        );
    }
    
    if (isset($classes[$class])) {
        require $classes[$class];
    }
});