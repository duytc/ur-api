<?php

namespace UR\Service\Parser\Transformer\Collection;


class AddField extends AbstractAddField
{
    /**
     * @var string
     */
    protected $column;
    protected $value;

    public function __construct($column, $value = null)
    {
        $this->column = $column;
        $this->value = $value;
    }

    protected function getValue(array $row)
    {
        return $this->value;
    }
}