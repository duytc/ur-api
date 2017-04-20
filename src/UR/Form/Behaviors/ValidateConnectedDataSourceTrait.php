<?php

namespace UR\Form\Behaviors;


use Symfony\Component\Config\Definition\Exception\Exception;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;
use UR\Service\DataSet\FieldType;
use UR\Service\Metadata\Email\EmailMetadata;
use UR\Service\Parser\Filter\FilterFactory;
use UR\Service\Parser\Transformer\Column\DateFormat;
use UR\Service\Parser\Transformer\TransformerFactory;
use UR\Service\Parser\Transformer\TransformerInterface;

trait ValidateConnectedDataSourceTrait
{
    /**
     * validate Mapping Fields
     *
     * @param DataSetInterface $dataSet
     * @param ConnectedDataSourceInterface $connDataSource
     * @return bool
     */
    public function validateMappingFields(DataSetInterface $dataSet, ConnectedDataSourceInterface $connDataSource)
    {
        foreach ($connDataSource->getMapFields() as $mapField) {
            if (!array_key_exists($mapField, $dataSet->getDimensions()) && !array_key_exists($mapField, $dataSet->getMetrics())) {
                return false;
            }
        }

        return true;
    }

    /**
     * validate Require Fields
     *
     * @param ConnectedDataSourceInterface $connDataSource
     * @return bool
     */
    public function validateRequireFields(ConnectedDataSourceInterface $connDataSource)
    {
        $allowedFields = array_merge($connDataSource->getMapFields(), array_flip(array_merge(EmailMetadata::$internalFields)));

        foreach ($connDataSource->getRequires() as $require) {
            if (!in_array($require, $allowedFields)) {
                return false;
            }
        }

        return true;
    }

    /**
     * validate Filters of a connectDataSource
     *
     * @param array $connDataSourceFilters
     */
    public function validateFilters($connDataSourceFilters)
    {
        if (!is_array($connDataSourceFilters) && $connDataSourceFilters !== null) {
            throw new Exception(sprintf('ConnectedDataSource Filters Setting should be an array'));
        }

        $filterFactory = new FilterFactory();
        foreach ($connDataSourceFilters as $filters) {
            $filterObject = $filterFactory->getFilter($filters);
            if ($filterObject !== null) {
                $filterObject->validate();
            }
        }
    }

    /**
     * validate Transforms
     *
     * @param DataSetInterface $dataSet
     * @param ConnectedDataSourceInterface $connDataSource
     * @throws \Exception
     */
    public function validateTransforms(DataSetInterface $dataSet, ConnectedDataSourceInterface $connDataSource)
    {
        $transformFactory = new TransformerFactory();

        // validate all transforms
        foreach ($connDataSource->getTransforms() as $transform) {
            /** @var array|TransformerInterface[]|TransformerInterface $transformObjects */
            $transformObjects = $transformFactory->getTransform($transform);

            if (!is_array($transformObjects)) {
                $transformObjects->validate();
                continue;
            }

            foreach ($transformObjects as $transformObject) {
                $transformObject->validate();
            }
        }

        // make sure date field has at least one transformation
        $fields = array_merge($dataSet->getDimensions(), $dataSet->getMetrics());
        foreach ($fields as $field => $type) {
            if (!in_array($field, $connDataSource->getMapFields())) {
                continue;
            }

            if ($type === FieldType::DATE) {
                $transformForDateCount = 0;
                foreach ($connDataSource->getTransforms() as $transform) {
                    $transformObj = $transformFactory->getTransform($transform);

                    if ($transformObj instanceof DateFormat) {
                        if ($transformObj->getField() === $field) {
                            $transformForDateCount++;
                        }
                    }
                }

                if ($transformForDateCount === 0) {
                    throw new Exception('Date type mapped should have a single field transformation');
                }
            }
        }
    }

    /**
     * validate Alert Setting
     *
     * @param ConnectedDataSourceInterface $connDataSource
     * @return bool
     */
    public function validateAlertSetting(ConnectedDataSourceInterface $connDataSource)
    {
        // TODO: do validate here...

        return true;
    }
}