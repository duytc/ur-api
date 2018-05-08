<?php

namespace UR\Form\Type;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
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
                'query_builder' => function (DataSourceEntryRepository $ds) use($options) {
                    if ($options['userRole'] instanceof AdminInterface) {
                        return $ds->createQueryBuilder('ds')->select('ds');
                    }
                    // current user is publisher
                    /** @var PublisherInterface publisher */
                    $publisher = $options['userRole'];
                    return $ds->getDataSourceEntriesForPublisherQuery($publisher);
                }
            ))
            ->add('dataSet', 'entity', array(
                'class' => DataSet::class,
                'query_builder' => function (DataSetRepository $ds) use($options) {
                    if ($options['userRole'] instanceof AdminInterface) {
                        return $ds->createQueryBuilder('ds')->select('ds');
                    }
                    // current user is publisher
                    /** @var PublisherInterface publisher */
                    $publisher = $this->userRole;
                    return $ds->getDataSetsForPublisherQuery($publisher);
                }
            ));

        if ($options['userRole'] instanceof AdminInterface) {
            $builder->add(
                $builder->create('publisher')
                    ->addModelTransformer(new RoleToUserEntityTransformer(), false
                    )
            );
        };
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => ImportHistory::class,
            'userRole' => null]);
    }

    public function getName()
    {
        return 'ur_form_import_history';
    }
}