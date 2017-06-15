<?php

namespace UR\Form\Type;

use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use UR\Entity\Core\DataSet;
use UR\Form\DataTransformer\RoleToUserEntityTransformer;
use UR\Model\Core\DataSetInterface;
use UR\Model\User\Role\AdminInterface;
use UR\Service\DataSet\FieldType;

class DataSetFormType extends AbstractRoleSpecificFormType
{
    static $SUPPORTED_DIMENSION_TYPES = [
        FieldType::DATE,
        FieldType::DATETIME,
        FieldType::TEXT
    ];

    static $SUPPORTED_METRIC_TYPES = [
        FieldType::DATE,
        FieldType::DATETIME,
        FieldType::TEXT,
        FieldType::LARGE_TEXT,
        FieldType::NUMBER,
        FieldType::DECIMAL
    ];

    protected $actions = [];

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name')
            ->add('dimensions')
            ->add('metrics')
            ->add('allowOverwriteExistingData')
            ->add('connectedDataSources', CollectionType::class, array(
                'type' => new ConnectedDataSourceFormType(),
                'allow_add' => true,
                'allow_delete' => true,
            ))
            ->add('actions', null, array('mapped' => false));

        if ($this->userRole instanceof AdminInterface) {
            $builder->add(
                $builder->create('publisher')
                    ->addModelTransformer(new RoleToUserEntityTransformer(), false
                    )
            );
        };

        $builder->addEventListener(
            FormEvents::PRE_SUBMIT,
            function (FormEvent $event) {
                $dataSet = $event->getData();

                if (array_key_exists('actions', $dataSet)) {
                    $this->actions = $dataSet['actions'];
                }
            }
        );

        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) {
                /** @var DataSetInterface $dataSet */
                $dataSet = $event->getData();

                // validate dimensions and metrics
                $form = $event->getForm();

                $dimensions = $dataSet->getDimensions();
                $metrics = $dataSet->getMetrics();

                $this->validateDimensions($dimensions);

                if (!$this->validateMetrics($metrics)) ;

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

                foreach ($this->actions as &$action) {
                    foreach ($action as &$item) {
                        if (!array_key_exists('to', $item)) {
                            continue;
                        }
                        $item['to'] = $this->getStandardName($item['to']);
                    }
                }

                $dataSet->setActions($this->actions);
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
            Throw New BadRequestHttpException('dimensions should be array');
        }

        foreach ($dimensions as $dimensionName => $dimensionType) {
            if (!in_array($dimensionType, self::$SUPPORTED_DIMENSION_TYPES)) {
                Throw New BadRequestHttpException('dimensions type should be one of ' . json_encode(self::$SUPPORTED_DIMENSION_TYPES));
            }

            if (strlen($dimensionName) > 64) {
                Throw New BadRequestHttpException('dimensions must be less than 65 characters');
            }
        }
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
            Throw New BadRequestHttpException('metrics should be array');
        }

        foreach ($metrics as $metricName => $metricType) {
            if (!in_array($metricType, self::$SUPPORTED_METRIC_TYPES)) {
                Throw New BadRequestHttpException('metrics type should be one of ' . json_encode(self::$SUPPORTED_DIMENSION_TYPES));
            }

            if (strlen($metricName) > 64) {
                Throw New BadRequestHttpException('metrics must be less than 65 characters');
            }
        }
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