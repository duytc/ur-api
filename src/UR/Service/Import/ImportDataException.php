<?php

namespace UR\Service\Import;


class ImportDataException extends \Exception
{
    private $alertCode;
    private $row;
    private $column;

    /**
     * ImportDataException constructor.
     * @param string $message
     * @param string $alertCode
     * @param int $row
     * @param \Exception $column
     */
    public function __construct($alertCode, $row, $column, $message = null)
    {
        parent::__construct($message);
        $this->alertCode = $alertCode;
        $this->row = $row;
        $this->column = $column;
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
}