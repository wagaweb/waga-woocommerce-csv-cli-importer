<?php

namespace WWCCSVImporter;

use cli\progress\Bar;
use function Humbug\get_contents;
use League\Csv\Exception;
use League\Csv\Reader;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use WP_CLI\ExitException;
use function WP_CLI\Utils\make_progress_bar;
use WP_CLI;
use WWCCSVImporter\action\AbstractAction;
use WWCCSVImporter\action\ProductUpdateAction;

/*
 * @WAGADEV: Si vuole creare un pacchetto stand-alone di importazione via CSV.
 * Il CSV dovrebbe essere fornito già con gli header formattati correttamente OPPURE
 * dovrebbe essere possibile fornire da linea di comando un file di mapping tra gli header del csv e gli
 * header standard.
 */
class ImportProducts extends \WP_CLI_Command
{
    /**
     * @var bool
     */
    private $forceUpdate = false;
    /**
     * @var bool
     */
    private $dryRun = false;
    /**
     * @var bool
     */
    private $skipLog = false;
    /**
     * @var bool
     */
    private $verbose = false;
    /**
     * @var string Absolute path to the file to process
     */
    private $file = '';
    /**
     * @var string Absolute path to the log file to write
     */
    private $logfile = '';
    /**
     * @var string Absolute path to manifest file
     */
    private $manifestFile;
    /**
     * @var array Manifest file parsed content
     */
    private $manifest;
    /**
     * @var string The lock file name
     */
    private $lockFileName;
	/**
	 * @var boolean
	 */
    private $bypassLockFile;
    /**
     * @var Logger
     */
    private $logger;
    /**
     * @var ImportActionsQueue
     */
    private $currentQueue;

    /**
     * Import a well-formatted CSV file
     *
     * ## OPTIONS
     *
     * <file>
     * : The path to the file to import. Can be absolute, relative to wp-content or remote.
     *
     * [--force-update]
     * : Whether or not to force a product update at the end
     *
     * [--skip-log]
     * : Whether or not to skip file logging.
     *
     * [--dry-run]
     * : Whether or not to perform a dry run.
     *
     * [--manifest]
     * : Absolute path to the manifest file. If the CSV to import doesn't have the standard headers, it is possible to use a json file for mapping the custom headers.
     *
     * [--lockfile]
     * : A custom name for the lock file. Having a different lock file name, allows to perform concurrent operations.
     *
     * [--unlock]
     * : Bypass lock file check
     *
     * ## EXAMPLES
     *
     *     wp wwc-prod-csv-import test.csv
     *
     *     wp wwc-prod-csv-import test.csv --quiet
     *
     *     wp wwc-prod-csv-import http://www.foo.bar/baz.csv --quiet
     *
     *     wp wwc-prod-csv-import http://www.foo.bar/baz.csv --manifest=/full/path/to/manifest.json --quiet
     *
     * @when after_wp_load
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function __invoke($args, $assoc_args)
    {
        if(\WP_CLI::get_config('debug')){
            $this->setVerbose(true);
        }else{
            $this->setVerbose(!\WP_CLI::get_config('quiet'));
        }

        $this->setSkipLog(isset($assoc_args['skip-log']) && $assoc_args['skip-log']);

        if(isset($assoc_args['lockfile'])){
            $this->setLockFileName($assoc_args['lockfile']);
        }else{
            $lockFileName = 'wwc-prod-csv-import.lock';
            $this->setLockFileName($lockFileName);
        }

        try{
	        $this->setBypassLockFile(isset($assoc_args['unlock']) && $assoc_args['unlock']);

            if($this->isLocked() && !$this->mustBypassLockFile()){
                $this->error('An operation is already in progress. You could delete the lock file located at: '.$this->getLockFilePath().' or run the command with --unlock flag if this is an issue.',false);
            }
			if($this->isLocked()){
				$this->clearLockFile();
			}
            $this->createLockFile();

            if($this->mustLog()){
                $this->initLogFile();
                $this->logger = new Logger('import');
                $this->logger->pushHandler(new StreamHandler($this->getLogfile()), Logger::INFO);
            }

            if(!\is_dir($this->getTmpDir())){
                $r = wp_mkdir_p($this->getTmpDir());
                if(!$r){
                    $this->error('Unable to create temp directory: '.$this->getTmpDir());
                }else{
                    $this->info('Temp directory created successfully: '.$this->getTmpDir());
                }
            }

            $this->setForceUpdate(isset($assoc_args['force-update']) && $assoc_args['force-update']);
            $this->setDryRun(isset($assoc_args['dry-run']) && $assoc_args['dry-run']);

            //Startup
            $manifestFile = isset($assoc_args['manifest']) ? $assoc_args['manifest'] : null;
            if(isset($manifestFile)){
                if(!\is_file($manifestFile)){
                    $this->error('Provided manifest is not a file');
                }
                $ext = pathinfo($manifestFile,PATHINFO_EXTENSION);
                if($ext !== 'json'){
                    $this->error('Provided manifest is not a json');
                }
                $this->setManifestFile($manifestFile);
                $this->parseManifestFile();
            }



            if(isset($args[0])){
                $this->handleFile($args[0]);
                $this->import();
            }
        }catch (\Exception $e){
            $this->clearLockFile();
            $this->error($e->getMessage());
        }
    }

    /**
     * @param string $inputFilePath
     * @throws \Exception
     */
    private function handleFile(string $inputFilePath)
    {
        $fs = new Filesystem();

        if(preg_match('|^/|',$inputFilePath)){
            //If starts with /, assume it is an absolute path
            $filePath = $inputFilePath;
        }elseif(preg_match('|(https?)://|',$inputFilePath,$matches)){
            //This is a remote path
            $remoteUri = $inputFilePath;
            $remoteProtocol = $matches[1];
            //Lets proceed to file download
            $this->info('Downloading: '.$remoteUri);
            $contents = @get_contents($remoteUri);
            if(!$contents){
                $this->error('Unable to download the file from: '.$remoteUri);
            }
            $fileName = (new \DateTime())->format('Y-m-d_Hi').'_import.csv';
            $filePath = $this->getTmpDir().'/'.$fileName;
            if(\is_file($filePath)){
                unlink($filePath);
            }
            $fs->dumpFile($filePath,$contents);
            $this->info('File saved to: '.$filePath);
        }else{
            //This is a relative path
            $filePath = WP_CONTENT_DIR.'/'.$inputFilePath;
        }
        $this->info('Input file: '.$filePath);
        $this->setFile($filePath);
    }

    /**
     * Import the file
     */
    private function import()
    {
        $filePath = $this->getFile();
        if(!\is_file($filePath)){
            $this->error($filePath.' is not a file');
        }

        $this->info('Importing: '.$filePath);

        try{
            do_action('wwc-prod-csv-import\pre_import',$filePath,$this);
            $csv = Reader::createFromPath($filePath,'r');
            $csv->setDelimiter(';');
            $csv->setHeaderOffset(0);

            $records = $csv->getRecords();
            if($csv->count() <= 0){
                $this->error('No records found');
            }

            $this->currentQueue = new ImportActionsQueue();
            $this->currentQueue->setPretend($this->isDryRun());
            $this->currentQueue->setVerbose($this->isVerbose());

            if(!$this->verbose){
                $progress = make_progress_bar( 'Updating products', $csv->count() );
                if(!$progress instanceof Bar){
                    $progress = [
                      'total' => $csv->count(),
                      'current' => 0,
                    ];
                }
            }

            foreach ($records as $offset => $record){
                /*
                 * Alter the record before processing. Return FALSE to skip the record.
                 */
                $record = apply_filters('wwc-prod-csv-import\pre_import\alter_record',$record,$this);
                if($record === false || (!\is_array($record))){
                    $this->info('- Record at offset: '.$offset.' skipped');
                }else{
                    $this->handleRecord($record);
                }
                if(!$this->verbose){
                    if($progress instanceof Bar){
                        $this->writePercentageOfCompletionInLockFile('running',$progress->percent());
                        $progress->tick();
                    }
                    else{
                        $percentage = $progress['current'] / $progress['total'] * 100;
                        $this->writePercentageOfCompletionInLockFile('running', $percentage);
                        $progress['current']++;
                    }
                }
            }

            if(!$this->verbose){
                if($progress instanceof Bar){
                    $progress->finish();
                }
            }

            if(!$this->verbose && $this->currentQueue->countAfterActions() > 0){
                $progress = make_progress_bar( 'Finalizing imports', $this->currentQueue->countAfterActions() );
                if(!$progress instanceof Bar){
                    $progress = [
                        'total' => $this->currentQueue->countAfterActions(),
                        'current' => 0,
                    ];
                }
                $this->currentQueue->conclude(function(ImportAction $action) use($progress){
                    if($this->mustLog() && $action instanceof AbstractAction){
                        foreach ($action->getLogData() as $m){
                            $this->log($m);
                        }
                    }
                    if($progress instanceof Bar){
                        $this->writePercentageOfCompletionInLockFile('finalizing',$progress->percent());
                        $progress->tick();
                    }
                    else{
                        $percentage = $progress['current'] / $progress['total'] * 100;
                        $this->writePercentageOfCompletionInLockFile('finalizing', $percentage);
                        $progress['current']++;
                    }
                });
                if($progress instanceof Bar){
                    $progress->finish();
                }
            }else{
                $this->currentQueue->conclude();
            }

            $this->success('Operation completed');
            do_action('wwc-prod-csv-import\import_done');
            $this->clearLockFile();
        }catch (\Exception $e){
            $this->clearLockFile();
            $this->error($e->getMessage());
        }
    }

    /**
     * This functions handles a single "record". A record is an array representing the row of the CSV
     *
     * @param array $record
     * @return bool
     */
    private function handleRecord($record)
    {
        /*
         * @WAGADEV L'idea è che lo script crei per ogni record una "coda" di azioni da svolgere per aggiornare il prodotto
         * con tutti i campi e poi le esegua nella maniera più ottimale possibile (magari mergiando le query sul DB, raggruppando le azioni simili, ecc...)
         */
        global $wpdb;

        $currentSku = null;
        $currentPostId = null;

        /*
         * Here we get all record contained in the row
         */
        foreach ($record as $key => $value){
            /*
             * $key here is the column header (eg: PRODUCT_PRICE), $value is the cell value (eg: 35.00)
             */
            if($this->hasManifestFile()){
                $dataType = $this->getDataOfKeyFromManifest($key); //It is possible to specify a type for the data, see sample.
                $key = $this->convertToStandardKey($key);
            }
            if($key === 'SKU'){
                $currentSku = $value;
                //Check for product existence
                $q = $wpdb->prepare(
                    'SELECT ID FROM `'.$wpdb->posts.'` as p JOIN `'.$wpdb->postmeta.'` as pm ON (p.ID = pm.post_id) WHERE meta_key = "_sku" AND meta_value = %s',
                    $currentSku
                );
                $currentPostId = $wpdb->get_var($q);
                if($currentPostId === null){
                    break;
                }
                continue;
            }

            if(isset($dataType)){
                $this->currentQueue->addActionByKey($key,$value,$currentPostId,$dataType);
            }else{
                $this->currentQueue->addActionByKey($key,$value,$currentPostId);
            }

            if($this->mustForceUpdate() && !$this->isDryRun()){
                $this->currentQueue->addAfterAction(new ProductUpdateAction(null,$currentPostId,$this->isVerbose()));
                //In case of variation, update the parent product also
                if(get_post_type($currentPostId) === 'product_variation'){
                    $parentId = (int) wp_get_post_parent_id($currentPostId);
                    if($parentId > 0){
                        $this->currentQueue->addAfterAction(new ProductUpdateAction(null,$parentId,$this->isVerbose(),[],1000));
                    }
                }
            }
        }

        if(!$currentPostId){
            $this->info('Product with SKU '.$currentSku.' not found. Skipping.');
            return false;
        }

        $this->currentQueue->resolve(function(ImportAction $action){
            if($this->mustLog() && $action instanceof AbstractAction){
                foreach ($action->getLogData() as $m){
                    $this->log($m);
                }
            }
        });

        $this->info('Product with SKU: '.$currentSku.' and ID: #'.$currentPostId.' updated successfully.');

        do_action('wwc-prod-csv-import\product_updated',$currentPostId);

        return true;
    }

    /**
     * Init the log file for the session
     *
     * @throws \Exception
     */
    private function initLogFile(){
        if(!\is_dir($this->getLogsDir())){
            $r = wp_mkdir_p($this->getLogsDir());
            if(!$r){
                $this->error('Unable to create logs directory: '.$this->getLogsDir());
            }else{
                if($this->verbose) \WP_CLI::log('Logs directory created successfully: '.$this->getLogsDir());
            }
        }

        //Generating log file for the session
        $logFile = $this->getLogsDir().'/wwcsvimporter-logs-'.(new \DateTime())->format('Y-m-d_H-i').'.log';
        $this->setLogfile($logFile);
        try{
            $fs = new Filesystem();
            $fs->touch($this->getLogfile());
            if($this->verbose) \WP_CLI::log('Using log file: '.$logFile);
        }catch (\Exception $e){
            if($this->verbose) \WP_CLI::log('Unable to create the log file: '.$logFile);
            $this->skipLog = true;
        }
    }

    /*
     * SETTER AND GETTERS
     */

    /**
     * @param bool $forceUpdate
     */
    private function setForceUpdate(bool $forceUpdate): void
    {
        $this->forceUpdate = $forceUpdate;
    }

    /**
     * @return bool
     */
    private function mustForceUpdate(): bool
    {
        return $this->forceUpdate;
    }

    /**
     * @return bool
     */
    private function isDryRun(): bool
    {
        return $this->dryRun;
    }

    /**
     * @param bool $dryrun
     */
    private function setDryRun(bool $dryrun): void
    {
        $this->dryRun = $dryrun;
    }

    /**
     * @return bool
     */
    private function isVerbose(): bool
    {
        return $this->verbose;
    }

    /**
     * @param bool $verbose
     */
    private function setVerbose(bool $verbose): void
    {
        $this->verbose = $verbose;
    }

    /**
     * @return bool
     */
    private function mustSkipLog(): bool
    {
        return $this->skipLog;
    }

    /**
     * @return bool
     */
    private function mustLog(): bool
    {
        return !$this->skipLog;
    }

    /**
     * @param bool $skipLog
     */
    private function setSkipLog(bool $skipLog): void
    {
        $this->skipLog = $skipLog;
    }

    /**
     * @return string
     */
    private function getFile(): string
    {
        return $this->file;
    }

    /**
     * @param string $file
     */
    private function setFile(string $file): void
    {
        $this->file = $file;
    }

    /**
     * @return string
     */
    private function getLogfile(): string
    {
        return $this->logfile;
    }

    /**
     * @param string $logfile
     */
    private function setLogfile(string $logfile): void
    {
        $this->logfile = $logfile;
    }

    /**
     * @return string
     */
    private function getManifestFile(): string
    {
        return $this->manifestFile;
    }

    /**
     * @param string $manifestFile
     */
    private function setManifestFile(string $manifestFile): void
    {
        $this->manifestFile = $manifestFile;
    }

    /**
     * @return array
     */
    private function getManifest(): array
    {
        return $this->manifest;
    }

    /**
     * @param array $manifest
     */
    private function setManifest(array $manifest): void
    {
        $this->manifest = $manifest;
    }

    /**
     * @return bool
     */
    private function hasManifestFile(): bool
    {
        return isset($this->manifestFile);
    }

    /**
     * @return string
     */
    private function getTmpDir(): string
    {
        return WP_CONTENT_DIR.'/temp';
    }

    /**
     * @return string
     */
    private function getLogsDir(): string
    {
        return WP_CONTENT_DIR.'/cli-logs';
    }

    /*
     * LOCK FILE
     */

    /**
     * @param string $lockFileName
     */
    private function setLockFileName(string $lockFileName): void
    {
        $this->lockFileName = $lockFileName;
    }

    /**
     * Get the lock file name
     *
     * @return string|NULL
     */
    private function getLockFileName(): string
    {
        if(!isset($this->lockFileName))
        {
            return 'wwc-prod-csv-import.lock';
        }
        return $this->lockFileName;
    }

    /**
     * @param bool $force Force the creation of the file, even if the lock file already exists
     *
     *
     * @throws \Exception
     * @throws IOException
     *
     * @return bool
     */
    private function createLockFile($force = false): bool
    {
        $lockFilePath = $this->getLockFilePath();
        if(\is_file($lockFilePath) && !$force){
            throw new \Exception('Lock file already exists at '.$lockFilePath);
        }
        if(\is_file($lockFilePath) && $force)
        {
            $this->clearLockFile();
        }
        $fs = new Filesystem();
        $fs->touch($lockFilePath);
        return true;
    }

    /**
     * @throws IOException
     *
     * @return bool
     */
    private function clearLockFile(): bool
    {
        $lockFilePath = $this->getLockFilePath();
        if(\is_file($lockFilePath)){
            $fs = new Filesystem();
            $fs->remove($lockFilePath);
            return true;
        }
        return false;
    }

    /**
     * @return bool
     */
    private function isLocked(): bool
    {
        $lockFilePath = $this->getLockFilePath();
        return \is_file($lockFilePath);
    }

	/**
	 * @return bool
	 */
	public function mustBypassLockFile(): bool {
		return $this->bypassLockFile;
	}

	/**
	 * @param bool $bypassLockFile
	 */
	public function setBypassLockFile(bool $bypassLockFile): void {
		$this->bypassLockFile = $bypassLockFile;
	}

    /**
     * @param string $stage
     * @param float $percentage
     *
     * @throws IOException
     * @throws \Exception
     */
    private function writePercentageOfCompletionInLockFile(string $stage, float $percentage)
    {
        if(!\file_exists($this->getLockFilePath())){
            throw new \Exception('Unable to write percentage to lock file, no lock file at '.$this->getLockFilePath());
        }
        $r = file_put_contents($this->getLockFilePath(), $stage.': '.(string) $percentage);
        if(!$r){
            throw new IOException('Unable to write percentage to the lock file located at: '.$this->getLockFilePath());
        }
    }

    /**
     * @return string
     */
    private function getLockFilePath(): string{
        $lockFileName = $this->getLockFileName();
        $lockFilePath = WP_CONTENT_DIR.'/'.$lockFileName;
        return $lockFilePath;
    }

    /*
     * Manifest
     */

    /**
     * Parse the manifest file and store
     */
    private function parseManifestFile()
    {
        $content = file_get_contents($this->getManifestFile());
        if(\is_string($content)){
            $jsonContent = json_decode($content,ARRAY_A);
            if(json_last_error() === JSON_ERROR_NONE){
                $this->setManifest($jsonContent);
            }else{
                $this->error('Unable to parse the manifest file: '.json_last_error_msg());
            }
        }
    }

    /**
     * Get the type of the data stored in the $key from the manifest file
     *
     * @param string $key
     * @return string
     */
    public function getDataOfKeyFromManifest(string $key){
        if($this->hasManifestFile()){
            $manifest = $this->getManifest();
            if(isset($manifest['_types']) && isset($manifest['_types'][$key])){
                $type = $manifest['_types'][$key];
                if(\in_array($type,ImportActionsQueue::getValidDataTypes())){
                    return $type;
                }
            }
        }
        return ImportActionsQueue::DATA_TYPE_STRING;
    }

    /**
     * Convert any $key to a standard key recognized by the script. Ex: "Product_Price => meta:_regular_price")
     *
     * @param string $customKey
     * @return string|bool
     */
    private function convertToStandardKey(string $customKey)
    {
        if($this->isStandardKey($customKey)){
            return $customKey;
        }
        $manifest = $this->getManifest();
        if(isset($manifest[$customKey]) && \is_string($manifest[$customKey]) && $manifest[$customKey] !== ''){
            return $manifest[$customKey];
        }
        $this->error('Unable to get the standard key from: '.$customKey);
        return false;
    }

    /**
     * Check if the provided key is a standard key recognized by the script
     *
     * @param string $key
     * @return bool
     */
    private function isStandardKey(string $key){
        $standardKeys = ['SKU','qty'];
        if(\in_array($key,$standardKeys)){
            return true;
        }
        if(preg_match('|meta:([a-zA-Z-_]+)|',$key)){
            return true;
        }
        return false;
    }

    /*
     * OUTPUT
     */

    /**
     * @param string $message
     */
    public function info(string $message)
    {
        if($this->isVerbose()){
            \WP_CLI::log($message);
        }else{
            $this->log($message);
        }
    }

    /**
     * @param string $message
     */
    public function log(string $message, $level = Logger::INFO)
    {
        if($this->mustSkipLog()){
            return;
        }
        $this->logger->info($message);
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
	 * @param bool $clearLock
	 */
    public function error(string $message, $clearLock = true){
        if($clearLock){
        	$this->clearLockFile();
        }
        try{
            \WP_CLI::error($message);
        }catch(ExitException $e){
            \WP_CLI::log($message);
            die();
        }
    }
}
