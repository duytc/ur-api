<?php

namespace UR\Form\Type;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use UR\DomainManager\DataSourceManagerInterface;
use UR\Entity\Core\ConnectedDataSource;
use UR\Form\Behaviors\ValidateConnectedDataSourceTrait;
use UR\Form\DataTransformer\RoleToUserEntityTransformer;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;
use UR\Model\User\Role\AdminInterface;

class ConnectedDataSourceFormType extends AbstractRoleSpecificFormType
{
    use ValidateConnectedDataSourceTrait;

    /**
     * @var DataSourceManagerInterface
     */
    protected $dataSourceManager;

    function __construct(DataSourceManagerInterface $dataSourceManager)
    {
        $this->dataSourceManager = $dataSourceManager;
    }

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

                if ($dataSet != null) {

                    if (!$this->validateMappingFields($dataSet, $connDataSource)) {
                        $form->get('mapFields')->addError(new FormError('one or more fields of your mapping dose not exist in DataSet Dimensions or Metrics'));
                    }

                    if (!$this->validateFilters($dataSet, $connDataSource)) {
                        $form->get('filters')->addError(new FormError('Filters Mapping error'));
                    }

                    if(!$this->validateTransforms($dataSet, $connDataSource)){
                        $form->get('transforms')->addError(new FormError('Transforms Mapping error'));
                    }
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
}