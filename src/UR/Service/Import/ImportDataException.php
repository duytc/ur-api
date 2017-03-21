<?php

namespace UR\Service\Import;


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
    public function __construct($alertCode, $row, $column, $content, $message)
    {
        parent::__construct($message);
        $this->alertCode = $alertCode;
        $this->row = $row;
        $this->column = $column;
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
}