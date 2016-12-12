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
    static $SUPPORTED_DIMENSION_TYPES = [
        'date',
        'datetime',
        'text'
    ];

    static $SUPPORTED_METRIC_TYPES = [
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

                $dimensions = $dataSet->getDimensions();
                $metrics = $dataSet->getMetrics();

                if (!$this->validateDimensions($dimensions)) {
                    $form->get('dimensions')->addError(new FormError('dimensions should be array and each type should be one of ' . json_encode(self::$SUPPORTED_DIMENSION_TYPES)));
                    return;
                }

                if (!$this->validateMetrics($metrics)) {
                    $form->get('metrics')->addError(new FormError('metrics should be array and each type should be one of ' . json_encode(self::$SUPPORTED_METRIC_TYPES)));
                    return;
                }

                if (!$this->validateDimensionsMetricsDuplication($dimensions, $metrics)) {
                    $form->get('metrics')->addError(new FormError('dimensions and metrics should be array and their names should not be the same'));
                    return;
                }

                // standardize dimensions and metrics names
                $standardDimensions = [];
                $standardMetrics = [];

                foreach ($dimensions as $dimension => $type) {
                    $dimension = $this->getStandardName($dimension);
                    $standardDimensions[$dimension] = $type;
                }

                foreach ($metrics as $metric => $type) {
                    $metric = $this->getStandardName($metric);
                    $standardMetrics[$metric] = $type;
                }

                $dataSet->setDimensions($standardDimensions);
                $dataSet->setMetrics($standardMetrics);
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

    /**
     * validate dimensions
     *
     * @param array $dimensions
     * @return bool
     */
    public function validateDimensions($dimensions)
    {
        if (!is_array($dimensions)) {
            return false;
        }

        foreach ($dimensions as $dimensionName => $dimensionType) {
            if (!in_array($dimensionType, self::$SUPPORTED_DIMENSION_TYPES)) {
                return false;
            }
        }

        return true;
    }

    /**
     * validate metrics
     *
     * @param array $metrics
     * @return bool
     */
    public function validateMetrics($metrics)
    {
        if (!is_array($metrics)) {
            return false;
        }

        foreach ($metrics as $metricName => $metricType) {
            if (!in_array($metricType, self::$SUPPORTED_METRIC_TYPES)) {
                return false;
            }
        }

        return true;
    }

    /**
     * check if dimensions and metrics has same elements
     * @param array $dimensions
     * @param array $metrics
     * @return bool
     */
    public function validateDimensionsMetricsDuplication($dimensions, $metrics)
    {
        if (!is_array($dimensions) || !is_array($metrics)) {
            return false;
        }

        $commonNames = array_intersect_key($dimensions, $metrics);
        if (is_array($commonNames) && count($commonNames) > 0) {
            return false;
        }

        return true;
    }

    public function getStandardName($name)
    {
        $name = strtolower(trim($name));

        $name = preg_replace("/ +/", "_", $name);
        $name = preg_replace("/-+/", "_", $name);
        $name = preg_replace("/[^a-zA-Z0-9]/ ", "_", $name);
        $name = preg_replace("/_+/ ", "_", $name);

        return $name;
    }
}