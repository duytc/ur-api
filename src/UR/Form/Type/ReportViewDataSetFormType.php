<?php

namespace UR\Form\Type;


use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use UR\Entity\Core\ReportViewDataSet;
use UR\Entity\Core\ReportViewMultiView;

class ReportViewDataSetFormType extends AbstractRoleSpecificFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('reportView')
            ->add('filters')
            ->add('dataSet')
            ->add('dimensions')
            ->add('metrics')
        ;
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(['data_class' => ReportViewDataSet::class]);
    }

    public function getName()
    {
        return 'ur_form_report_view_data_set';
    }
}