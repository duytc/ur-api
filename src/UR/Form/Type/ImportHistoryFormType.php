<?php

namespace UR\Form\Type;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use UR\Entity\Core\DataSet;
use UR\Entity\Core\DataSourceEntry;
use UR\Entity\Core\ImportHistory;
use UR\Form\DataTransformer\RoleToUserEntityTransformer;
use UR\Model\User\Role\AdminInterface;
use UR\Model\User\Role\PublisherInterface;
use UR\Repository\Core\DataSetRepository;
use UR\Repository\Core\DataSourceEntryRepository;

class ImportHistoryFormType extends AbstractRoleSpecificFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('createdDate')
            ->add('description')
            ->add('dataSourceEntry', 'entity', array(
                'class' => DataSourceEntry::class,
                'query_builder' => function (DataSourceEntryRepository $ds) {
                    if ($this->userRole instanceof AdminInterface) {
                        return $ds->createQueryBuilder('ds')->select('ds');
                    }
                    // current user is publisher
                    /** @var PublisherInterface publisher */
                    $publisher = $this->userRole;
                    return $ds->getDataSourceEntriesForPublisherQuery($publisher);
                }
            ))
            ->add('dataSet', 'entity', array(
                'class' => DataSet::class,
                'query_builder' => function (DataSetRepository $ds) {
                    if ($this->userRole instanceof AdminInterface) {
                        return $ds->createQueryBuilder('ds')->select('ds');
                    }
                    // current user is publisher
                    /** @var PublisherInterface publisher */
                    $publisher = $this->userRole;
                    return $ds->getDataSetsForPublisherQuery($publisher);
                }
            ));

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
        $resolver->setDefaults(['data_class' => ImportHistory::class,]);
    }

    public function getName()
    {
        return 'ur_form_import_history';
    }
}