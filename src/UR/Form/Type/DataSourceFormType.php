<?php

namespace UR\Form\Type;

use Exception;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use UR\Entity\Core\DataSource;
use UR\Form\DataTransformer\RoleToUserEntityTransformer;
use UR\Model\Core\DataSourceIntegrationInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Model\User\Role\AdminInterface;
use UR\Service\Alert\DataSource\DataSourceAlertInterface;

class DataSourceFormType extends AbstractRoleSpecificFormType
{
    const ALERT_WRONG_FORMAT_KEY = 'wrongFormat';
    const ALERT_DATA_RECEIVED_KEY = 'dataReceived';
    const ALERT_DATA_NO_RECEIVED_KEY = 'notReceived';

    static $SUPPORTED_ALERT_SETTING_KEYS = [
        self::ALERT_WRONG_FORMAT_KEY,
        self::ALERT_DATA_RECEIVED_KEY,
        self::ALERT_DATA_NO_RECEIVED_KEY,
    ];

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name')
            ->add('format', ChoiceType::class, [
                'choices' => [
                    DataSource::CSV_FORMAT => 'CSV',
                    DataSource::EXCEL_FORMAT => 'Excel',
                    DataSource::JSON_FORMAT => 'Json'
                ],
            ])
            ->add('alertSetting')
            ->add('enable')
            ->add('dataSourceIntegrations', CollectionType::class, array(
                'mapped' => true,
                'type' => new DataSourceIntegrationFormType(),
                'allow_add' => true,
                'allow_delete' => true,
            ))
            ->add('useIntegration');

        if ($this->userRole instanceof AdminInterface) {
            $builder->add(
                $builder->create('publisher')
                    ->addModelTransformer(new RoleToUserEntityTransformer(), false)
            );
        };

        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) {
                /** @var DataSourceInterface $dataSource */
                $dataSource = $event->getData();

                // validate alert setting if has
                $form = $event->getForm();
                if (!$this->validateAlertSetting($dataSource->getAlertSetting())) {
                    $form->get('alertSetting')->addError(new FormError('alert setting invalid: not supported key or duplicate'));
                    return;
                }

                $dataSourceIntegrations = $dataSource->getDataSourceIntegrations();

                /** @var DataSourceIntegrationInterface $dataSourceIntegration */
                foreach ($dataSourceIntegrations as $dataSourceIntegration) {
                    $dataSourceIntegration->setDataSource($dataSource);
                }
            }
        );
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(['data_class' => DataSource::class,]);
    }

    public function getName()
    {
        return 'ur_form_data_source';
    }

    /**
     * validate AlertSetting
     *
     * @param $alertSetting
     * @return bool
     * @throws Exception
     */
    private function validateAlertSetting($alertSetting)
    {
        if ($alertSetting === null) {
            return true;
        }

        if (!is_array($alertSetting)) {
            return false;
        }

        $checkedAlertKeys = [];
        foreach ($alertSetting as $alert) {
            if (!in_array($alert[DataSourceAlertInterface::ALERT_TYPE_KEY], self::$SUPPORTED_ALERT_SETTING_KEYS)
                || in_array($alert, $checkedAlertKeys)
            ) {
                return false;
            }

            if (empty($alert[DataSourceAlertInterface::ALERT_TIME_ZONE_KEY])) {
                return true;
            }

            if (!in_array($alert[DataSourceAlertInterface::ALERT_TIME_ZONE_KEY], timezone_identifiers_list())) {
                throw new Exception(sprintf('Timezone %s does not exits', $alert[DataSourceAlertInterface::ALERT_TIME_ZONE_KEY]));
            }

            $checkedAlertKeys[] = $alert;
        }

        return true;
    }
}