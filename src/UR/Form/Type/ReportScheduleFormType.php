<?php

namespace UR\Form\Type;


use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use UR\Entity\Core\ReportSchedule;
use UR\Entity\Core\ReportView;

class ReportScheduleFormType extends AbstractRoleSpecificFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('emails')
            ->add('schedule')
            ->add('reportView')
            ->add('alertMissingData');
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(['data_class' => ReportSchedule::class]);
    }

    public function getName()
    {
        return 'ur_form_report_schedule';
    }
}