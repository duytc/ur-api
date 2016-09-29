<?php

namespace Tagcade\Form\Type;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Tagcade\Entity\Core\DataSourceEntry;
use Tagcade\Form\DataTransformer\RoleToUserEntityTransformer;
use Tagcade\Model\User\Role\AdminInterface;

class DataSourceEntryFormType extends AbstractRoleSpecificFormType
{
    function __construct()
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('dataSource')
            ->add('metaData')
            ->add('valid')
            ->add('path');
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver
            ->setDefaults([
                'data_class' => DataSourceEntry::class,
            ]);
    }

    public function getName()
    {
        return 'tagcade_form_data_source_entry';
    }
}