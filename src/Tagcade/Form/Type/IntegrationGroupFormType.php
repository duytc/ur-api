<?php

namespace Tagcade\Form\Type;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Tagcade\Entity\Core\AdNetwork;
use Tagcade\Entity\Core\IntegrationGroup;
use Tagcade\Form\DataTransformer\RoleToUserEntityTransformer;
use Tagcade\Model\User\Role\AdminInterface;

class IntegrationGroupFormType extends AbstractRoleSpecificFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name');
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver
            ->setDefaults([
                'data_class' => IntegrationGroup::class,
            ]);
    }

    public function getName()
    {
        return 'tagcade_form_integration_group';
    }
}