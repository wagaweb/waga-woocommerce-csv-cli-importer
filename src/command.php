<?php

namespace WWCCSVImporter;

/*
 * Ajax endpoints
 */

/**
 * Executes a command in background
 * @param $cmd
 */
function bgExec($cmd){
    $cmd = '/bin/bash -c "' . addslashes($cmd) . '"';
    $cmd = $cmd . " > /dev/null 2>/dev/null &";
    //var_dump($cmd);
    //die();

    exec($cmd);

    /*
    $args = [];

    $pid=pcntl_fork();
    if($pid==0)
    {
        posix_setsid();
        pcntl_exec($cmd,$args,$_ENV);
        // child becomes the standalone detached process
    }
    */

}

add_action('wp_ajax_nopriv_activate-wwc-prod-csv-import', function(){
    $operation = $_POST['operation'];
    if(isset($operation['lockfile'])){
        $lockFileName = $operation['lockfile'];
    }else{
        $lockFileName = 'wwc-prod-csv-import.lock';
    }
    if(\is_file(WP_CONTENT_DIR.'/'.$lockFileName)){
        wp_send_json_error([
            'message' => 'An operation is already in progress',
            'error' => 'operation-in-progress'
        ]);
    }
    $operation = $operation['file'];
    $operation .= ' --quiet';
    $operation = sprintf('cd %s && wp wwc-prod-csv-import %s',ABSPATH,$operation);
    bgExec($operation);
    wp_send_json_success([
        'operation' => $operation
    ]);
});

add_action('wp_ajax_check-wwc-prod-csv-import', function(){

});

if ( ! class_exists( 'WP_CLI' ) ) {
    return;
}

if(!defined('WWCCSV_USE_OWN_AUTOLOADER') || WWCCSV_USE_OWN_AUTOLOADER){
    $autoloadPath = defined('WWCCSV_BASEDIR') ? WWCCSV_BASEDIR : getcwd();
    require_once trailingslashit($autoloadPath).'vendor/autoload.php';
}

\WP_CLI::add_command( 'wwc-prod-csv-import', new ImportProducts() );