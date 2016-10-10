<?php

namespace UR\Form\Type;

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

class DataSourceFormType extends AbstractRoleSpecificFormType
{
    static $SUPPORTED_ALERT_SETTING_KEYS = [
        'wrongFormat',
        'dataReceived',
        'notReceived'
    ];

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name')
            ->add('format', ChoiceType::class, [
                'choices' => [
                    'csv' => 'csv',
                    'excel' => 'excel',
                    'json' => 'json'
                ],
            ])
            ->add('alertSetting')
            ->add('dataSourceIntegrations', CollectionType::class, array(
                'mapped' => true,
                'type' => new DataSourceIntegrationFormType(),
                'allow_add' => true,
                'allow_delete' => true,
            ));

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
        foreach ($alertSetting as $value) {
            if (!in_array($value, self::$SUPPORTED_ALERT_SETTING_KEYS)
                || in_array($value, $checkedAlertKeys)
            ) {
                return false;
            }

            $checkedAlertKeys[] = $value;
        }

        return true;
    }
}