<?php

namespace UR\Form\Type;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use UR\Entity\Core\DataSourceIntegrationSchedule;

class DataSourceIntegrationScheduleFormType extends AbstractRoleSpecificFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('dataSourceIntegration')
            ->add('uuid')
            ->add('nextExecutedAt')
            ->add('queuedAt')
            ->add('finishedAt')
            ->add('scheduleType')
            ->add('status');
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => DataSourceIntegrationSchedule::class,
            'userRole' => null
        ]);
    }

    public function getName()
    {
        return 'ur_form_data_source_integration_schedule';
    }
}