<?php

namespace Tagcade\Form\Type;


use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Tagcade\Entity\Core\DataSource;
use Tagcade\Form\DataTransformer\RoleToUserEntityTransformer;
use Tagcade\Model\Core\DataSourceInterface;

class DataSourceFormType extends AbstractRoleSpecificFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name')
            ->add('format');
        if ($this->userRole instanceof DataSourceInterface) {
            $builder->add(
                $builder->create('publisher')
                    ->addModelTransformer(new RoleToUserEntityTransformer(), false
                    )
            );
        }
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(['data_class' => DataSource::class,]);
    }

    public function getName()
    {
        return 'tagcade_form_data_source';
    }
}