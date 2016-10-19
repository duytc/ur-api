<?php

namespace UR\Form\Type;


use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use UR\Entity\Core\DataSourceEntry;
use UR\Entity\Core\Alert;
use UR\Model\User\Role\AdminInterface;
use UR\Model\User\Role\PublisherInterface;
use UR\Repository\Core\DataSourceEntryRepository;

class AlertFormType extends AbstractRoleSpecificFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('type')
            ->add('isRead')
            ->add('title')
            ->add('dataSourceEntry', 'entity', array(
                'class' => DataSourceEntry::class,
                'query_builder' => function (DataSourceEntryRepository $er) {
                    if ($this->userRole instanceof AdminInterface) {
                        return $er->createQueryBuilder('dse')->select('dse');
                    }

                    // current user is publisher
                    /** @var PublisherInterface publisher */
                    $publisher = $this->userRole;
                    return $er->getDataSourceEntriesForPublisherQuery($publisher);
                }
            ))
            ->add('message');
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(['data_class' => Alert::class]);
    }

    public function getName()
    {
        return 'ur_form_alert';
    }
}