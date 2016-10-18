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

                $connDataSources = $dataSet->getConnectedDataSources();

                if (count($connDataSources) > 0) {
                    if (!$this->validateMappingFields($dataSet, $connDataSources)) {
                        $form->get('connectedDataSources')->addError(new FormError('one or more fields you mapping are not exist in DataSet Dimensions or Metrics'));
                    }
                }

                // todo: hhhhhh
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
}