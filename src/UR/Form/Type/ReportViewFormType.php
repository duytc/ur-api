<?php

namespace UR\Form\Type;


use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use UR\Entity\Core\ReportView;
use UR\Form\DataTransformer\RoleToUserEntityTransformer;
use UR\Model\User\Role\AdminInterface;

class ReportViewFormType extends AbstractRoleSpecificFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('dataSets')
            ->add('name')
            ->add('transforms')
            ->add('joinBy')
            ->add('weightedCalculations')
            ->add('multiView')
            ->add('reportViews')
        ;

        if ($this->userRole instanceof AdminInterface) {
            $builder->add(
                $builder->create('publisher')
                    ->addModelTransformer(new RoleToUserEntityTransformer(), false
                    )
            );
        };
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(['data_class' => ReportView::class]);
    }

    public function getName()
    {
        return 'ur_form_report_view';
    }
}