<?php

namespace UR\Form\Type;


use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use UR\Entity\Core\ReportViewMultiView;

class ReportViewMultiViewFormType extends AbstractRoleSpecificFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('reportView')
            ->add('filters')
            ->add('subView')
            ->add('dimensions')
            ->add('metrics')
        ;
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(['data_class' => ReportViewMultiView::class]);
    }

    public function getName()
    {
        return 'ur_form_report_view_multi_view';
    }
}