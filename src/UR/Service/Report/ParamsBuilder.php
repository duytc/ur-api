<?php


namespace UR\Service\Report;


use UR\Model\Core\ReportViewInterface;

class ParamsBuilder implements ParamsBuilderInterface
{
    const FILTERS_KEY = 'filters';
    const TRANSFORMS_KEY = 'transforms';
    const TRANSFORM_TARGET_KEY = 'transformType';
    const TRANSFORM_TYPE_KEY = 'type';
    const FIELD_NAME_KEY = 'field';

    const TYPE_NUMBER_DECIMAL_KEY = 'decimals';
    const TYPE_NUMBER_THOUSAND_SEPARATOR_KEY = 'thousandSeparator';

    public function buildFromArray(array $params)
    {
        // TODO: Implement build() method.
    }

    public function buildFromReportView(ReportViewInterface $reportView)
    {
        // TODO: Implement buildFromReportView() method.
    }
}