<?php

namespace UR\Form\Type;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use UR\Entity\Core\DataSourceIntegrationSchedule;

class DataSourceIntegrationScheduleFormType extends AbstractRoleSpecificFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('dataSourceIntegration')
            ->add('uuid')
            ->add('executedAt')
            ->add('scheduleType');
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(['data_class' => DataSourceIntegrationSchedule::class,]);
    }

    public function getName()
    {
        return 'ur_form_data_source_integration_schedule';
    }
}