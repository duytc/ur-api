<?php

namespace UR\Form\Type;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use UR\Entity\Core\Integration;

class IntegrationFormType extends AbstractRoleSpecificFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name')
            ->add('canonicalName')
            ->add('type', ChoiceType::class, [
                'choices' => [
                    Integration::TYPE_UI => 'UI',
                    Integration::TYPE_API => 'API'
                ],
            ])
            ->add('method', ChoiceType::class, [
                'choices' => [
                    Integration::METHOD_GET => 'GET',
                    Integration::METHOD_POST => 'POST'
                ],
            ])
            ->add('url');
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver
            ->setDefaults([
                'data_class' => Integration::class,
            ]);
    }

    public function getName()
    {
        return 'ur_form_integration';
    }
}