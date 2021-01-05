<?php

namespace WWCCSVImporter;

if(defined('WP_DEBUG') && WP_DEBUG){
    require_once __DIR__.'/tests.php';
}

if(!defined( 'WP_CLI')){
    require_once __DIR__.'/frontend-hooks.php';
    return;
}

if(!defined('WWCCSV_USE_OWN_AUTOLOADER')){
    define('WWCCSV_USE_OWN_AUTOLOADER', true);
}

if(WWCCSV_USE_OWN_AUTOLOADER){
    if(!defined('WWCCSV_AUTOLOADER_BASEDIR')){
        define('WWCCSV_AUTOLOADER_BASEDIR', dirname(__DIR__));
    }
    require_once trailingslashit(WWCCSV_AUTOLOADER_BASEDIR).'vendor/autoload.php';
}

\WP_CLI::add_command( 'wwc-prod-csv-import', new ImportProducts() );

\WP_CLI::add_command( 'wwc-prod-csv-check', new LogsChecker() );
