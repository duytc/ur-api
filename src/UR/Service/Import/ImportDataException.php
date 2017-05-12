<?php

namespace UR\Service\Import;


use UR\Model\Core\ConnectedDataSourceInterface;

class ImportDataException extends \Exception
{
    private $alertCode;
    private $row;
    private $column;
    private $content;

    /**
     * ImportDataException constructor.
     * @param string $alertCode
     * @param int $row
     * @param \Exception $column
     * @param null $content
     * @param string $message
     */
    public function __construct($alertCode, $row = null, $column = null, $content = null, $message = null)
    {
        parent::__construct($message);
        $this->alertCode = $alertCode;
        $this->row = $row;
        $this->removeAllPrefix($column);
        $this->content = $content;
    }

    /**
     * @return mixed
     */
    public function getAlertCode()
    {
        return $this->alertCode;
    }

    /**
     * @return mixed
     */
    public function getRow()
    {
        return $this->row;
    }

    /**
     * @return mixed
     */
    public function getColumn()
    {
        return $this->column;
    }

    /**
     * @return null
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * @param null $content
     */
    public function setContent($content)
    {
        $this->content = $content;
    }

    private function removeAllPrefix($column)
    {
        $column = str_replace(ConnectedDataSourceInterface::PREFIX_FILE_FIELD, '', $column);
        $this->column = str_replace(ConnectedDataSourceInterface::PREFIX_TEMP_FIELD, '', $column);
    }
}