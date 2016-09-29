<?php

namespace Tagcade\Form\Type;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Tagcade\Entity\Core\DataSourceIntegration;

class DataSourceIntegrationFormType extends AbstractRoleSpecificFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('dataSource')
            ->add('integration')
            ->add('username')
            ->add('password')
            ->add('schedule');
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(['data_class' => DataSourceIntegration::class,]);
    }

    public function getName()
    {
        return 'tagcade_form_data_source_integration';
    }
}