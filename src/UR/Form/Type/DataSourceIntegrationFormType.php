<?php

namespace UR\Form\Type;

use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use UR\Entity\Core\DataSourceIntegration;
use UR\Model\Core\DataSourceIntegrationInterface;
use UR\Model\Core\Integration;

class DataSourceIntegrationFormType extends AbstractRoleSpecificFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('id')
            ->add('dataSource')
            ->add('integration')
            ->add('params')
            ->add('schedule')
            // back fill feature
            ->add('backFill')
            ->add('backFillStartDate', DateType::class, array(
                // render as a single text box
                'widget' => 'single_text',
            ))
            ->add('backFillEndDate', DateType::class, array(
                // render as a single text box
                'widget' => 'single_text',
            ))
            ->add('backFillExecuted')
            ->add('backFillForce')
            ->add('active');

        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) {
                /** @var DataSourceIntegrationInterface $dataSourceIntegration */
                $dataSourceIntegration = $event->getData();

                // validate alert setting if has
                $form = $event->getForm();

                if (!$this->validateParams($dataSourceIntegration->getOriginalParams(), $form)) {
                    $form->get('params')->addError(new FormError('params invalid'));
                    return;
                }

                if (!$this->validateScheduleSetting($dataSourceIntegration->getSchedule())) {
                    $form->get('schedule')->addError(new FormError('schedule setting invalid'));
                    return;
                }

                // validate alert setting if has
                if ($this->validateBackFillSetting($dataSourceIntegration, $form)) {
                    return;
                }
            }
        );
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(['data_class' => DataSourceIntegration::class,]);
    }

    public function getName()
    {
        return 'ur_form_data_source_integration';
    }

    /**
     * validate Schedule Setting
     *
     * @param $params
     * @param FormInterface $form
     * @return bool
     */
    private function validateParams($params, FormInterface $form)
    {
        if (!is_array($params)) {
            return true;
        }

        foreach ($params as $param) {
            if (!is_array($param)) {
                $form->get('params')->addError(new FormError('expect each element in params is an array'));
                return false;
            }

            // validate keys
            if (!array_key_exists(Integration::PARAM_KEY_KEY, $param)
                || !array_key_exists(Integration::PARAM_KEY_TYPE, $param)
                || !array_key_exists(Integration::PARAM_KEY_VALUE, $param)
            ) {
                $form->get('params')->addError(new FormError('expect keys "key", "type" and "value" in each element of params'));
                return false;
            }

            // validate types
            $type = $param[Integration::PARAM_KEY_TYPE];
            if (!in_array($type, $param)) {
                $form->get('params')->addError(new FormError(sprintf('not supported "type" as %s in params', $type)));
                return false;
            }

            // validate dynamic date range if existed
            $value = $param[Integration::PARAM_KEY_VALUE];
            if ($type === Integration::PARAM_TYPE_DYNAMIC_DATE_RANGE) {
                // note: allow empty or null value, then dateRange will be yesterday by default
                if (!empty($value) && !in_array($value, Integration::$SUPPORTED_PARAM_VALUE_DYNAMIC_DATE_RANGES)) {
                    $form->get('params')->addError(new FormError(sprintf('not supported dynamicDateRange "value" as %s in params', $value)));
                    return false;
                }
            }

            // validate regex if existed
            if ($type === Integration::PARAM_TYPE_REGEX) {
                if (!preg_match(Integration::SUPPORTED_PARAM_VALUE_REGEX, $value, $matches)) {
                    $form->get('params')->addError(new FormError(sprintf('invalid regex value as %s in params', $value)));
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * validate Schedule Setting
     *
     * @param $schedule
     * @return bool
     */
    private function validateScheduleSetting($schedule)
    {
        if (!is_array($schedule)) {
            return true;
        }

        if (!array_key_exists(DataSourceIntegration::SCHEDULE_KEY_CHECKED, $schedule)) {
            return false; // missing checked key
        }

        $checked = $schedule[DataSourceIntegration::SCHEDULE_KEY_CHECKED];

        // validate checked
        if (!in_array($checked, DataSourceIntegration::$SUPPORTED_SCHEDULE_CHECKED_TYPES)) {
            return false; // not supported checked key
        }

        // validate checked vs existing key
        if (!in_array($checked, $schedule)) {
            return false; // required key match for checked value is not existed
        }

        return true;
    }

    /**
     * validateBackFillSetting
     *
     * @param DataSourceIntegrationInterface $dataSourceIntegration
     * @param FormInterface $form
     * @return bool
     */
    private function validateBackFillSetting(DataSourceIntegrationInterface $dataSourceIntegration, FormInterface $form)
    {
        if (!$dataSourceIntegration->isBackFill()) {
            return true;
        }

        if (null == $dataSourceIntegration->getBackFillStartDate()) {
            $form->get('backFillStartDate')->addError(new FormError('missing backFillStartDate when backFill is enabled'));
            return false;
        }

        if ($dataSourceIntegration->getBackFillStartDate() > date_create()) {
            $form->get('backFillStartDate')->addError(new FormError('backFillStartDate can not greater than today'));
            return false;
        }

        if ($dataSourceIntegration->getBackFillEndDate() != null && $dataSourceIntegration->getBackFillEndDate() > date_create()) {
            $form->get('backFillEndDate')->addError(new FormError('backFillEndDate can not greater than today'));
            return false;
        }

        if ($dataSourceIntegration->getBackFillStartDate() > $dataSourceIntegration->getBackFillEndDate()) {
            $form->get('backFillStartDate')->addError(new FormError('backFillStartDate can not greater than backFillEndDate'));
            return false;
        }

        return true;
    }
}