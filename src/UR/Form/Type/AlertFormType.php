<?php

namespace UR\Form\Type;


use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use UR\Model\Core\Alert;

class AlertFormType extends AbstractRoleSpecificFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('type')
            ->add('isRead')
            ->add('title')
            ->add('dataSourceEntry')
            ->add('message');
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(['data_class' => Alert::class,]);
    }

    public function getName()
    {
        return 'ur_form_alert';
    }
}