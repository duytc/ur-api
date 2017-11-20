<?php


namespace UR\Form\Type;


use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use UR\Entity\Core\AutoOptimizationConfigDataSet;
use UR\Model\Core\AutoOptimizationConfigDataSetInterface;

class AutoOptimizationConfigDataSetFormType extends AbstractRoleSpecificFormType
{

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('autoOptimizationConfig')
            ->add('filters')
            ->add('dataSet')
            ->add('dimensions')
            ->add('metrics');

        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) {
                /** @var AutoOptimizationConfigDataSetInterface $autoOptimizationConfigDataSet */
                $autoOptimizationConfigDataSet = $event->getData();
                $form = $event->getForm();

                $this->validateFilters($form, $autoOptimizationConfigDataSet->getFilters());

                $this->validateDimensions($form, $autoOptimizationConfigDataSet->getDimensions());

                $this->validateMetrics($form, $autoOptimizationConfigDataSet->getMetrics());
            }
        );

    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(['data_class' => AutoOptimizationConfigDataSet::class]);
    }

    public function getName()
    {
        return 'ur_form_auto_optimization_config_data_set';
    }

    private function validateFilters($form, $filters)
    {
        return true;
    }

    private function validateDimensions($form, $dimensions)
    {
        // TODO: validate dimensions

        return true;
    }

    private function validateMetrics($form, $metrics)
    {
        return true;
    }
}