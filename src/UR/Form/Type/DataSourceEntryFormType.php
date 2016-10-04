<?php

namespace UR\Form\Type;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use UR\Entity\Core\DataSource;
use UR\Entity\Core\DataSourceEntry;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\User\Role\AdminInterface;
use UR\Model\User\Role\PublisherInterface;
use UR\Repository\Core\DataSourceRepository;

class DataSourceEntryFormType extends AbstractRoleSpecificFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('dataSource', 'entity', array(
                'class' => DataSource::class,
                'query_builder' => function (DataSourceRepository $ds) {
                    if ($this->userRole instanceof AdminInterface) {
                        return $ds->createQueryBuilder('ds')->select('ds');
                    }
                    // current user is publisher
                    /** @var PublisherInterface publisher */
                    $publisher = $this->userRole;
                    return $ds->getDataSourcesForPublisherQuery($publisher);
                }
            ))
            ->add('metaData')
            ->add('valid')
            ->add('path');

        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) {
                // It's important here to fetch $event->getForm()->getData(), as
                // $event->getData() will get you the client data (that is, the ID)
                /** @var DataSourceEntryInterface $dataSourceEntry */
                $dataSourceEntry = $event->getData();
                $form = $event->getForm();

                if (!$this->validateMetaData($dataSourceEntry->getMetaData())) {
                    $form->get('metaData')->addError(new FormError('Metadata should be null or array'));
                }
            }
        );
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
        return 'ur_form_data_source_entry';
    }

    /**
     * validate MetaData
     *
     * @param null|array $metaData
     * @return bool
     */
    private function validateMetaData($metaData)
    {
        if (null !== $metaData && !is_array($metaData)) {
            return false;
        }

        // validate more...
        return true;
    }
}