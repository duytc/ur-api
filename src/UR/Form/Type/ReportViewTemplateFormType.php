<?php

namespace UR\Form\Type;


use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use UR\Entity\Core\ReportViewTemplate;

class ReportViewTemplateFormType extends AbstractRoleSpecificFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name')
            ->add('dataSets')
            ->add('reportViews')
            ->add('joinConfig')
            ->add('transforms')
            ->add('formats')
            ->add('showInTotal')
            ->add('showDataSetName')
            ->add('dimensions')
            ->add('metrics');

        $builder->add('reportViewTemplateTags', CollectionType::class, array(
            'mapped' => true,
            'entry_type' => ReportViewTemplateTagFormType::class,
            'allow_add' => true,
            'allow_delete' => true,
        ));
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => ReportViewTemplate::class,
            'userRole' => null
        ]);
    }

    public function getName()
    {
        return 'ur_form_report_view_template';
    }
}