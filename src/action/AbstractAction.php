<?php

namespace WWCCSVImporter\action;

use WWCCSVImporter\ImportAction;

/**
 * Class AbstractAction
 *
 * This class represent an action to perform to change a particular field of a product.
 *
 * @package WWCCSVImporter\action
 */
abstract class AbstractAction implements ImportAction
{
    /**
     * @var string
     */
    private $id;
    /**
     * @var string This is the value of the CSV column
     */
    private $data;
    /**
     * @var array Any data that the action can use to perform its work
     */
    private $specificData;
    /**
     * @var int
     */
    private $productId;
    /**
     * @var bool
     */
    private $verbose;
    /**
     * @var @array
     */
    private $logData = [];

    /**
     * AbstractAction constructor.
     * @param string|null $data
     * @param int $productId
     * @param bool $verbose
     * @param array $specificData
     */
    public function __construct($data, int $productId, bool $verbose, array $specificData = [])
    {
        $this->setData($data);
        $this->setProductId($productId);
        $this->setVerbose($verbose);
        if(\is_array($specificData) && count($specificData) > 0){
            $this->setSpecificData($specificData);
        }
        $this->setId($this->generateId());
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @param string $id
     */
    public function setId(string $id): void
    {
        $this->id = $id;
    }

    /**
     * Generate an unique id for the action
     *
     * @return string
     */
    private function generateId(): string
    {
        if($this->getData() === null){
            $data = 'noData';
        }else{
            $data = trim($this->getData());
        }

        $id = get_called_class().'_'.$this->getProductId().'_'.$data;
        $hash = md5($id);

        return $hash;
    }

    /**
     * @return string|null
     */
    public function getData(): ?string
    {
        return $this->data;
    }

    /**
     * @param string|null $data
     */
    public function setData($data): void
    {
        $this->data = $data;
    }

    /**
     * @return array
     */
    public function getSpecificData(): array
    {
        return $this->specificData;
    }

    /**
     * @param array $specificData
     */
    public function setSpecificData(array $specificData): void
    {
        $this->specificData = $specificData;
    }

    /**
     * @return mixed
     */
    public function getProductId(): int
    {
        return $this->productId;
    }

    /**
     * @return int
     */
    public function getTargetId(): int
    {
        return $this->getProductId();
    }

    /**
     * @param int $productId
     */
    public function setProductId(int $productId): void
    {
        $this->productId = $productId;
    }

    /**
     * @return bool
     */
    public function isVerbose(): bool
    {
        return $this->verbose;
    }

    /**
     * @param bool $verbose
     */
    public function setVerbose(bool $verbose): void
    {
        $this->verbose = $verbose;
    }

    /**
     * @param $message
     */
    public function addLogData($message){
        $this->logData[] = $message;
    }

    /**
     * @return array
     */
    public function getLogData(): array
    {
        return $this->logData;
    }
}