<?php

namespace WWCCSVImporter;

use WP_CLI\ExitException;

class LogsChecker extends \WP_CLI_Command
{
    /**
     * @var string Absolute path to the file to process
     */
    private $file = '';
    /**
     * @var string
     */
    private $outFilePath = '';
    /**
     * @var bool
     */
    private $showNotFound;
    /**
     * @var bool
     */
    private $showUpdated;

    /**
     * Parse import log files
     *
     * ## OPTIONS
     *
     * <file>
     * : The path to the log file to check. Can be absolute or relative to wp-content.
     *
     * [--not-found]
     * : Display not found products only
     *
     * [--updated]
     * : Display updated products only
     *
     * [--out]
     * : The filename where redirect the output (will be created inside WP_CONTEND_DIR)
     *
     * ## EXAMPLES
     *
     *     wp wwc-prod-csv-check wwcsvimporter-logs-2019-10-02_10-03.log
     *
     *     wp wwc-prod-csv-import wwcsvimporter-logs-2019-10-02_10-03.log --skipped
     *
     *     wp wwc-prod-csv-import wwcsvimporter-logs-2019-10-02_10-03.log --skipped --out=file
     *
     * @when after_wp_load
     *
     * @param array $args
     * @param array $assoc_args
     *
     * @throws \WP_CLI\ExitException
     */
    public function __invoke($args, $assoc_args)
    {
        $this->showNotFound = isset($assoc_args['not-found']) && $assoc_args['not-found'];
        $this->showUpdated = isset($assoc_args['updated']) && $assoc_args['updated'];
        if(!$this->showNotFound && !$this->showUpdated){
            $this->showUpdated = true;
            $this->showNotFound = true;
        }

        if(isset($assoc_args['out'])){
            $fullPath = WP_CONTENT_DIR.'/'.$assoc_args['out'];
            $this->setOutFilePath($fullPath);
        }

        $this->handleFile($args[0]);
        $this->check();
    }

    private function handleFile($inputFilePath)
    {
        if(preg_match('|^/|',$inputFilePath)){
            //If starts with /, assume it is an absolute path
            $filePath = $inputFilePath;
        }else{
            //This is a relative path
            $filePath = WP_CONTENT_DIR.'/'.$inputFilePath;
        }
        $this->setFile($filePath);
    }

    private function check()
    {
        $filePath = $this->getFile();
        if(!\is_file($filePath)){
            $this->error($filePath.' is not a file');
        }

        $updatedProducts = [];
        $notFoundProducts = [];

        $h = @fopen($filePath,'r');
        if($h){
            while( ($line = fgets($h)) !== false ){
                if(preg_match('/with SKU ([a-zA-Z0-9]+) not found/',$line,$matches)){
                    $notFoundProducts[] = $matches[1];
                }elseif(preg_match('/Product with SKU: ([a-zA-Z0-9]+) and ID: #([0-9]+) updated successfully/',$line,$matches)){
                    $updatedProducts[] = $matches[1];
                }
            }
            if (!feof($h)) {
                $this->error("Error: unexpected fgets() fail");
            }
        }

        if($this->mustOutputToFile()){
            $outputFilePath = $this->getOutFilePath();
            $data = '';
            if($this->mustShowUpdated()){
                $data .= 'Updated product list'.PHP_EOL;
                $data .= implode(PHP_EOL,$updatedProducts);
            }
            $data .= PHP_EOL;
            if($this->mustShowNotFound()){
                $data .= 'Not found products list'.PHP_EOL;
                $data .= implode(PHP_EOL,$notFoundProducts);
            }
            $r = file_put_contents($outputFilePath,$data);
            if(!$r){
                $this->error('Unable to write to file: '.$outputFilePath);
            }
            $this->success('File written: '.$outputFilePath);
        }else{
            if($this->mustShowUpdated()){
                $this->info('Updated products list:');
                $this->info(implode(';',$updatedProducts));
            }
            if($this->mustShowNotFound()){
                $this->info('Not found products list:');
                $this->info(implode(';',$notFoundProducts));
            }
            if($this->mustShowUpdated()){
                $this->info('Updated products: '.count($updatedProducts));
            }
            if($this->mustShowNotFound()){
                $this->info('Not found products: '.count($notFoundProducts));
            }
            $this->success('Operation completed');
        }
    }

    /**
     * @return string
     */
    public function getFile()
    {
        return $this->file;
    }

    /**
     * @param string $file
     */
    public function setFile(string $file)
    {
        $this->file = $file;
    }

    /**
     * @return string
     */
    public function getOutFilePath()
    {
        return $this->outFilePath;
    }

    /**
     * @param string $outFilePath
     */
    public function setOutFilePath(string $outFilePath)
    {
        $this->outFilePath = $outFilePath;
    }

    /**
     * @return bool
     */
    public function mustOutputToFile()
    {
        return $this->getOutFilePath() !== '';
    }

    /**
     * @return bool
     */
    public function mustShowNotFound()
    {
        return $this->showNotFound;
    }

    /**
     * @param bool $showNotFound
     */
    public function setShowNotFound(bool $showNotFound): void
    {
        $this->showNotFound = $showNotFound;
    }

    /**
     * @return bool
     */
    public function mustShowUpdated()
    {
        return $this->showUpdated;
    }

    /**
     * @param bool $showUpdated
     */
    public function setShowUpdated(bool $showUpdated): void
    {
        $this->showUpdated = $showUpdated;
    }

    /*
     * OUTPUT
     */

    /**
     * @param string $message
     */
    public function info(string $message)
    {
        \WP_CLI::log($message);
    }

    /**
     * @param string $message
     */
    public function log(string $message)
    {
        \WP_CLI::log($message);
    }

    /**
     * @param string $message
     */
    public function success(string $message)
    {
        \WP_CLI::success($message);
    }

    /**
     * @param string $message
     */
    public function error(string $message){
        try{
            \WP_CLI::error($message);
        }catch(\WP_CLI\ExitException $e){
            \WP_CLI::log($message);
            die();
        }
    }

    public function newLine(){
        \WP_CLI::log('');
    }
}