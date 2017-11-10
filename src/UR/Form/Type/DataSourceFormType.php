<?php

namespace UR\Form\Type;

use DateTime;
use Doctrine\Common\Collections\Collection;
use Exception;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use UR\Entity\Core\DataSource;
use UR\Form\DataTransformer\RoleToUserEntityTransformer;
use UR\Model\Core\DataSourceIntegrationInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Model\Core\IntegrationPublisherInterface;
use UR\Model\User\Role\AdminInterface;
use UR\Service\Alert\DataSource\AbstractDataSourceAlert;
use UR\Service\Alert\DataSource\DataSourceAlertInterface;

class DataSourceFormType extends AbstractRoleSpecificFormType
{
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
            ->add('useIntegration')
            ->add('dateRangeDetectionEnabled')
            ->add('dateFields')
            ->add('dateFieldsFromMetadata')
            ->add('dateFormats')
            ->add('dateRange')
            ->add('fromMetadata')
            ->add('pattern')
            ->add('emailAnchorTexts')
            ->add('backfillMissingDateRunning')
            ->add('timeSeries');

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
                $dataSource->setLastActivity(new DateTime());

                // validate alert setting if has
                $form = $event->getForm();
                if (!$this->validateAlertSetting($dataSource->getAlertSetting())) {
                    $form->get('alertSetting')->addError(new FormError('alert setting invalid: not supported key or duplicate'));
                    return;
                }

                // validate integration permission
                if (!$this->validateIntegration($dataSource)) {
                    $form->get('dataSourceIntegrations')->addError(new FormError('integration is not yet enabled for this user'));
                    return;
                }

                // check if form "dataSourceIntegrations" has error
                if (!$form->get('dataSourceIntegrations')->isValid()) {
                    // TODO: UI should know the exactly errors of dataSourceIntegrations instead of return error here
                    throw new BadRequestHttpException('integration is not valid, please check the backfill config');
                }

                // re-mapping dataSource - dataSourceIntegrations by cascade persist
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
            if (!in_array($alert[DataSourceAlertInterface::ALERT_TYPE_KEY], AbstractDataSourceAlert::$SUPPORTED_ALERT_SETTING_KEYS)
                || in_array($alert, $checkedAlertKeys)
            ) {
                return false;
            }

            if (empty($alert[DataSourceAlertInterface::ALERT_TIME_ZONE_KEY])) {
                return true;
            }

            if (!in_array($alert[DataSourceAlertInterface::ALERT_TIME_ZONE_KEY], timezone_identifiers_list())) {
                throw new Exception(sprintf('Timezone %s does not exist', $alert[DataSourceAlertInterface::ALERT_TIME_ZONE_KEY]));
            }

            $checkedAlertKeys[] = $alert;
        }

        return true;
    }

    /**
     * validate integration permission in data source
     *
     * @param DataSourceInterface $dataSource
     * @return bool
     */
    private function validateIntegration(DataSourceInterface $dataSource)
    {
        /** @var Collection|DataSourceIntegrationInterface[] $dataSourceIntegrations */
        $dataSourceIntegrations = $dataSource->getDataSourceIntegrations();

        if ($dataSourceIntegrations instanceof Collection) {
            $dataSourceIntegrations = $dataSourceIntegrations->toArray();
        }

        if (!is_array($dataSourceIntegrations) || count($dataSourceIntegrations) < 1) {
            return true;
        }

        foreach ($dataSourceIntegrations as $dataSourceIntegration) {
            $integration = $dataSourceIntegration->getIntegration();

            // check if integration is enabled for all publishers
            if ($integration->isEnableForAllUsers()) {
                continue;
            }

            // validate publisher id
            /** @var Collection|IntegrationPublisherInterface[] $integrationPublishers */
            $integrationPublishers = $integration->getIntegrationPublishers();

            if ($integrationPublishers instanceof Collection) {
                $integrationPublishers = $integrationPublishers->toArray();
            }

            if (!is_array($integrationPublishers) || count($integrationPublishers) < 1) {
                continue;
            }

            $enabledPublisherIds = array_map(function ($integrationPublisher) {
                /** @var IntegrationPublisherInterface $integrationPublisher */
                return $integrationPublisher->getPublisher()->getId();
            }, $integrationPublishers);

            if (!in_array($dataSource->getPublisherId(), $enabledPublisherIds)) {
                return false;
            }
        }

        return true;
    }
}