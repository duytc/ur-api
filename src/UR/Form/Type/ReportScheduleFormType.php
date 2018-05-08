<?php

namespace UR\Form\Type;


use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use UR\Entity\Core\ReportSchedule;

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

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => ReportSchedule::class,
            'userRole' => null]);
    }

    public function getName()
    {
        return 'ur_form_report_schedule';
    }
}