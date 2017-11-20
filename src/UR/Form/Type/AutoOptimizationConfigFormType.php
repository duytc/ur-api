<?php


namespace UR\Form\Type;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use UR\Entity\Core\AutoOptimizationConfig;
use UR\Form\DataTransformer\RoleToUserEntityTransformer;
use UR\Model\Core\AutoOptimizationConfigDataSetInterface;
use UR\Model\Core\AutoOptimizationConfigInterface;
use UR\Model\User\Role\AdminInterface;

class AutoOptimizationConfigFormType extends AbstractRoleSpecificFormType
{

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('transforms')
            ->add('filters')
            ->add('metrics')
            ->add('dimensions')
            ->add('name')
            ->add('fieldTypes')
            ->add('joinBy')
            ->add('factors')
            ->add('objective')
            ->add('dateRange')
            ->add('active');

        $builder
            ->add('autoOptimizationConfigDataSets', 'collection', array(
                    'mapped' => true,
                    'type' => new AutoOptimizationConfigDataSetFormType(),
                    'allow_add' => true,
                    'allow_delete' => true,
                )
            );

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
                /** @var AutoOptimizationConfigInterface $autoOptimizationConfig */
                $autoOptimizationConfig = $event->getData();
                $form = $event->getForm();


                $this->validateTransforms($form, $autoOptimizationConfig->getTransforms());
                $this->validateFilters($form, $autoOptimizationConfig->getFilters());
                $this->validateJoinBy($form, $autoOptimizationConfig->getJoinBy());
                $this->validateFieldTypes($form, $autoOptimizationConfig->getFieldTypes());

                if (!is_array($autoOptimizationConfig->getFieldTypes())) {
                    $autoOptimizationConfig->setFieldTypes([]);
                }

                $autoOptimizationConfigDataSets = $autoOptimizationConfig->getAutoOptimizationConfigDataSets();
                /**
                 * @var AutoOptimizationConfigDataSetInterface[] $autoOptimizationConfigDataSets
                 */
                foreach ($autoOptimizationConfigDataSets as $autoOptimizationConfigDataSet) {
                    $autoOptimizationConfigDataSet->setAutoOptimizationConfig($autoOptimizationConfig);
                }
            }
        );
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(['data_class' => AutoOptimizationConfig::class]);
    }

    public function getName()
    {
        return 'ur_form_auto_optimization_config';
    }

    private function validateTransforms($form, $transforms)
    {
        return true;
    }

    private function validateFilters($form, $filters)
    {
        return true;
    }

    private function validateJoinBy($form, $joinBy)
    {
        return true;
    }

    private function validateFieldTypes($form, $fieldTypes)
    {
        return true;
    }

}