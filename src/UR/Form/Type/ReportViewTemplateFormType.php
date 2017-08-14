<?php

namespace UR\Form\Type;


use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use UR\Entity\Core\ReportViewTemplate;
use UR\Model\Core\ReportViewTemplateInterface;

class ReportViewTemplateFormType extends AbstractRoleSpecificFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name')
            ->add('multiView')
            ->add('dataSets')
            ->add('reportViews')
            ->add('joinConfig')
            ->add('transforms')
            ->add('formats')
            ->add('showInTotal')
            ->add('showDataSetName')
            ->add('enableCustomDimensionMetric')
            ->add('dimensions')
            ->add('metrics');

        $builder->add('reportViewTemplateTags', CollectionType::class, array(
            'mapped' => true,
            'type' => new ReportViewTemplateTagFormType(),
            'allow_add' => true,
            'allow_delete' => true,
        ));

        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) {
                /** @var ReportViewTemplateInterface $reportViewTemplate */
                $reportViewTemplate = $event->getData();
                $form = $event->getForm();
            }
        );
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(['data_class' => ReportViewTemplate::class]);
    }

    public function getName()
    {
        return 'ur_form_report_view_template';
    }
}