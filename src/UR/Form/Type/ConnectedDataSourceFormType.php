<?php

namespace UR\Form\Type;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use UR\Entity\Core\ConnectedDataSource;
use UR\Form\DataTransformer\RoleToUserEntityTransformer;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;
use UR\Model\User\Role\AdminInterface;

class ConnectedDataSourceFormType extends AbstractRoleSpecificFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('dataSet')
            ->add('dataSource')
            ->add('mapFields')
            ->add('filters')
            ->add('transforms');

        if ($this->userRole instanceof AdminInterface) {
            $builder->add(
                $builder->create('publisher')
                    ->addModelTransformer(new RoleToUserEntityTransformer(), false
                    )
            );
        };

        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) {
                /** @var ConnectedDataSourceInterface $connDataSource */
                $connDataSource = $event->getData();
                $form = $event->getForm();

                // validate mapping fields
                /** @var DataSetInterface $dataSet */
                $dataSet = $connDataSource->getDataSet();

                if ($dataSet != null && !$this->validateMappingFields($dataSet, $connDataSource->getMapFields())) {
                    $form->get('mapFields')->addError(new FormError('one or more fields you mapping are not exist in DataSet Dimensions or Metrics'));
                }
            }
        );
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(['data_class' => ConnectedDataSource::class,]);
    }

    public function getName()
    {
        return 'ur_form_connected_data_source';
    }

    public function validateMappingFields(DataSetInterface $dataSet, $mapFields)
    {
        // check if mapping fields are exist in Data Set
        foreach ($mapFields as $mapField) {
            if (!array_key_exists($mapField, $dataSet->getDimensions()) && !array_key_exists($mapField, $dataSet->getMetrics())) {
                return false;
            }
        }
        return true;
    }
}