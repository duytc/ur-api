<?php

namespace UR\Form\Type;


use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use UR\Entity\Core\ReportViewTemplateTag;
use UR\Model\Core\ReportViewTemplateTagInterface;

class ReportViewTemplateTagFormType extends AbstractRoleSpecificFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('tag')
            ->add('reportViewTemplate');

        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) {
                /** @var ReportViewTemplateTagInterface $reportViewTemplateTag */
                $reportViewTemplateTag = $event->getData();
                $form = $event->getForm();

                $tag = $reportViewTemplateTag->getTag();
                $reportViewTemplate = $reportViewTemplateTag->getReportViewTemplate();
            }
        );
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => ReportViewTemplateTag::class,
            'userRole' => null]);
    }

    public function getName()
    {
        return 'ur_form_reportViewTemplateTag';
    }
}