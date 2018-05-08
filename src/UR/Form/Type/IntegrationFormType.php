<?php

namespace UR\Form\Type;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use UR\Entity\Core\Integration;

class IntegrationFormType extends AbstractRoleSpecificFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name')
            ->add('canonicalName')
            ->add('params');
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver
            ->setDefaults([
                'data_class' => Integration::class,
                'userRole' => null
            ]);
    }

    public function getName()
    {
        return 'ur_form_integration';
    }
}