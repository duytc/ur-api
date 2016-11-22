<?php

namespace UR\Service\Parser\Transformer\Collection;

use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use UR\Service\Alert\ProcessAlert;

class AddCalculatedField extends AbstractAddField
{
    /**
     * @var string
     */
    protected $column;
    /**
     * @var string
     */
    protected $expression;
    protected $defaultValue;
    protected $language;

    public function __construct(ExpressionLanguage $language, $column, $expression, $defaultValue = 0)
    {
        $this->column = $column;
        $this->expression = $expression;
        $this->defaultValue = $defaultValue;
        $this->language = $language;
    }

    protected function getValue(array $row)
    {
        try {
            $this->language->register('abs', function ($a) {
            }, function ($arguments, $a) {
                if (!is_numeric($a)) {
                    return 0;
                }
                return abs($a);
            });

            $result = $this->language->evaluate($this->expression, ['row' => $row]);
        } catch (\Exception $exception) {
            $message = $exception->getMessage();
            $result = array(ProcessAlert::ERROR => ProcessAlert::DATA_IMPORT_TRANSFORM_FAIL,
                ProcessAlert::MESSAGE => $message
            );
        }
        return $result;
    }
}