<?php

namespace UR\Form\Type;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use UR\Entity\Core\ConnectedDataSource;
use UR\Form\Behaviors\ValidateConnectedDataSourceTrait;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;

class ConnectedDataSourceFormType extends AbstractRoleSpecificFormType
{
    use ValidateConnectedDataSourceTrait;
    const IS_DRY_RUN = 'isDryRun';
    const FILE_PATHS = 'filePaths';

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('connectedDataSourceId', null, ['mapped' => false])
            ->add('dataSet')
            ->add('dataSource')
            ->add('mapFields')
            ->add('filters')
            ->add('transforms')
            ->add('requires')
            ->add('alertSetting')
            ->add('replayData')
            ->add('userReorderTransformsAllowed')
            ->add(self::IS_DRY_RUN, null, ['mapped' => false])
            ->add('filePaths', null, ['mapped' => false]);

        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) {
                /** @var ConnectedDataSourceInterface $connDataSource */
                $connDataSource = $event->getData();
                $form = $event->getForm();

                // validate mapping fields
                /** @var DataSetInterface $dataSet */
                $dataSet = $connDataSource->getDataSet();

                if ($dataSet !== null || $connDataSource->getId() !== null) {

                    if (!$this->validateMappingFields($dataSet, $connDataSource)) {
                        $form->get('mapFields')->addError(new FormError('one or more fields of your mapping dose not exist in DataSet Dimensions or Metrics'));
                    }

                    if ($connDataSource->getRequires() !== null) {
                        if (!$this->validateRequireFields($connDataSource)) {
                            $form->get('requires')->addError(new FormError('one or more fields of your require fields dose not exist in your Mapping'));
                        }
                    }

                    if($connDataSource->getFilters()!==null) {
                        $this->validateFilters($connDataSource->getFilters());
                    }

                    if($connDataSource->getTransforms()!==null){
                        $this->validateTransforms($dataSet, $connDataSource);
                    }

                    if (!$this->validateAlertSetting($connDataSource)) {
                        $form->get('transforms')->addError(new FormError('Alerts Setting error'));
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