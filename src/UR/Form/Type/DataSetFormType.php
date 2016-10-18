<?php

namespace UR\Form\Type;

use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use UR\Entity\Core\DataSet;
use UR\Form\DataTransformer\RoleToUserEntityTransformer;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;
use UR\Model\User\Role\AdminInterface;

class DataSetFormType extends AbstractRoleSpecificFormType
{
    static $SUPPORTED_DIMENSION_VALUES = [
        'date',
        'datetime',
        'text'
    ];

    static $SUPPORTED_METRIC_VALUES = [
        'date',
        'datetime',
        'text',
        'multiLineText',
        'number',
        'decimal'
    ];

    static $COMPARISON_NUMBER_VALUES = [
        'smaller',
        'smaller or equal',
        'equal',
        'not equal',
        'greater',
        'greater or equal',
        'in',
        'not'
    ];

    static $COMPARISON_TEXT_VALUES = [
        'contains',
        'not contains',
        'start with',
        'end with',
        'in',
        'not'
    ];

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name')
            ->add('dimensions')
            ->add('metrics')
            ->add('connectedDataSources', CollectionType::class, array(
                'mapped' => true,
                'type' => new ConnectedDataSourceFormType(),
                'allow_add' => true,
                'by_reference' => false,
                'allow_delete' => true,
            ));

        if ($this->userRole instanceof AdminInterface) {
            $builder->add(
                $builder->create('publisher')
                    ->addModelTransformer(new RoleToUserEntityTransformer(), false
                    )
            );
        };

        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) {
                /** @var DataSetInterface $dataSet */
                $dataSet = $event->getData();

                // validate dimensions and metrics
                $form = $event->getForm();

                if (!$this->validateDimensions($dataSet->getDimensions())) {
                    $form->get('dimensions')->addError(new FormError('dimension values should not null and be one of ' . json_encode(self::$SUPPORTED_DIMENSION_VALUES)));
                }

                if (!$this->validateMetrics($dataSet->getMetrics())) {
                    $form->get('metrics')->addError(new FormError('metric values should not null and be one of ' . json_encode(self::$SUPPORTED_METRIC_VALUES)));
                }

                //validate connDataSources
                $connDataSources = $dataSet->getConnectedDataSources();

                if (count($connDataSources) > 0) {

                    //validate mapping fields
                    if (!$this->validateMappingFields($dataSet, $connDataSources)) {
                        $form->get('connectedDataSources')->addError(new FormError('one or more fields of your mapping dose not exist in DataSet Dimensions or Metrics'));
                    }

                    //validate filter
                    if (!$this->validateFilters($dataSet, $connDataSources)) {
                        $form->get('connectedDataSources')->addError(new FormError('Filters Mapping error'));
                    }
                }

                foreach ($connDataSources as $connDataSource) {
                    /** @var ConnectedDataSourceInterface $connDataSource */
                    $connDataSource->setDataSet($dataSet);
                }

            }
        );
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(['data_class' => DataSet::class,]);
    }

    public function getName()
    {
        return 'ur_form_data_source';
    }

    public function validateDimensions($dimensions)
    {
        if ($dimensions == null) {
            return false;
        }

        foreach ($dimensions as $dimension) {
            if (!in_array($dimension, self::$SUPPORTED_DIMENSION_VALUES)) {
                return false;
            }
        }
        return true;
    }

    public function validateMetrics($metrics)
    {
        if ($metrics == null) {
            return false;
        }

        foreach ($metrics as $metric) {
            if (!in_array($metric, self::$SUPPORTED_METRIC_VALUES)) {
                return false;
            }
        }
        return true;
    }

    public function validateMappingFields(DataSetInterface $dataSet, $connDataSources)
    {
        /**@var ConnectedDataSourceInterface $connDataSource */
        foreach ($connDataSources as $connDataSource) {
            foreach ($connDataSource->getMapFields() as $mapField) {
                if (!array_key_exists($mapField, $dataSet->getDimensions()) && !array_key_exists($mapField, $dataSet->getMetrics())) {
                    return false;
                }
            }
        }
        return true;
    }

    public function validateFilters(DataSetInterface $dataSet, $connDataSources)
    {
        /**@var ConnectedDataSourceInterface $connDataSource */
        foreach ($connDataSources as $connDataSource) {
            if ($connDataSource->getFilters() !== null)
                foreach ($connDataSource->getFilters() as $fieldName => $value) {

                    if (!array_key_exists($fieldName, $dataSet->getDimensions()) && !array_key_exists($fieldName, $dataSet->getMetrics())) {
                        return false;
                    }

                    if (strcmp($value['type'], "date") === 0 && !$this->validateFilterDateType($value)) {
                        return false;
                    }

                    if (strcmp($value['type'], "number") === 0 && !$this->validateFilterNumberType($value)) {
                        return false;
                    }

                    if (strcmp($value['type'], "text") === 0 && !$this->validateFilterTextType($value)) {
                        return false;
                    }

                }
        }
        return true;
    }

    public function validateFilterDateType($value)
    {
        if (count($value) !== 3 || !array_key_exists("from", $value) || !array_key_exists("to", $value)) {
            return false;
        }
        return true;
    }

    public function validateFilterNumberType($value)
    {
        if (count($value) !== 3 || !array_key_exists("comparison", $value) || !array_key_exists("compareValue", $value)) {
            return false;
        }

        if (!in_array($value['comparison'], self::$COMPARISON_NUMBER_VALUES, true)) {
            return false;
        }

        return true;
    }

    public function validateFilterTextType($value)
    {
        if (count($value) !== 3 || !array_key_exists("comparison", $value) || !array_key_exists("compareValue", $value)) {
            return false;
        }

        if (!in_array($value['comparison'], self::$COMPARISON_TEXT_VALUES, true)) {
            return false;
        }

        return true;
    }

}