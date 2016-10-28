<?php

namespace UR\Form\Type;

use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use UR\Entity\Core\DataSet;
use UR\Form\Behaviors\ValidateConnectedDataSourceTrait;
use UR\Form\DataTransformer\RoleToUserEntityTransformer;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;
use UR\Model\User\Role\AdminInterface;

class DataSetFormType extends AbstractRoleSpecificFormType
{
    use ValidateConnectedDataSourceTrait;
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
//                'mapped' => true,
                'type' => new ConnectedDataSourceFormType(),
                'allow_add' => true,
//                'by_reference' => false,
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

                    foreach ($connDataSources as $connDataSource) {

                        //validate mapping fields
                        if (!$this->validateMappingFields($dataSet, $connDataSource)) {
                            $form->get('connectedDataSources')->addError(new FormError('one or more fields of your mapping dose not exist in DataSet Dimensions or Metrics'));
                        }

                        //validate filter
                        if (!$this->validateFilters($dataSet, $connDataSource)) {
                            $form->get('connectedDataSources')->addError(new FormError('Filters Mapping error'));
                        }

                        //validate transform
                        if (!$this->validateTransforms($connDataSource)) {
                            $form->get('connectedDataSources')->addError(new FormError('Transform Mapping error'));
                        }

                        /** @var ConnectedDataSourceInterface $connDataSource */
                        $connDataSource->setDataSet($dataSet);
                    }
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
            foreach ($dimension as $key=>$value) {
                if ($key == 'type') {
                    if (!in_array($value, self::$SUPPORTED_DIMENSION_VALUES)) {
                        return false;
                    }
                }
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
            foreach ($metric as $key=>$value) {
                if ($key == 'type') {
                    if (!in_array($value, self::$SUPPORTED_METRIC_VALUES)) {
                        return false;
                    }
                }
            }
        }
        return true;
    }


}