<?php

namespace UR\Form\Type;


use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use UR\Entity\Core\ReportViewTemplateTag;
use UR\Form\DataTransformer\RoleToUserEntityTransformer;
use UR\Model\Core\ReportViewTemplateTagInterface;
use UR\Model\User\Role\AdminInterface;

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

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(['data_class' => ReportViewTemplateTag::class]);
    }

    public function getName()
    {
        return 'ur_form_reportViewTemplateTag';
    }
}