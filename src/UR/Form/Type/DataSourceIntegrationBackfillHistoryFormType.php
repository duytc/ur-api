<?php

namespace UR\Form\Type;

use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use UR\Entity\Core\DataSourceIntegrationBackfillHistory;
use UR\Model\Core\DataSourceIntegrationBackfillHistoryInterface;

class DataSourceIntegrationBackfillHistoryFormType extends AbstractRoleSpecificFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('id')
            ->add('dataSourceIntegration')
            ->add('queuedAt')
            ->add('finishedAt')
            // back fill feature
            ->add('backFillStartDate', DateType::class, array(
                // render as a single text box
                'widget' => 'single_text',
            ))
            ->add('backFillEndDate', DateType::class, array(
                // render as a single text box
                'widget' => 'single_text',
            ))
            ->add('autoCreate');

        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) {
                /** @var DataSourceIntegrationBackfillHistoryInterface $dataSourceIntegrationBackFillHistory */
                $dataSourceIntegrationBackFillHistory = $event->getData();

                // validate alert setting if has
                $form = $event->getForm();

                if (!$this->validateBackFillSetting($dataSourceIntegrationBackFillHistory, $form)) {
                    return;
                }
            }
        );

    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => DataSourceIntegrationBackfillHistory::class,
            'userRole' => null
        ]);
    }

    public function getName()
    {
        return 'ur_form_data_source_integration_backfill_history';
    }


    /**
     * validateBackFillSetting
     *
     * @param DataSourceIntegrationBackfillHistoryInterface $dataSourceIntegrationBackFillHistory
     * @param FormInterface $form
     * @return bool
     */
    private function validateBackFillSetting(DataSourceIntegrationBackfillHistoryInterface $dataSourceIntegrationBackFillHistory, FormInterface $form)
    {
        if ($dataSourceIntegrationBackFillHistory->getBackFillStartDate() == null && $dataSourceIntegrationBackFillHistory->getBackFillEndDate() == null) {
            return true;
        }

        if (null == $dataSourceIntegrationBackFillHistory->getBackFillStartDate()) {
            $form->get('backFillStartDate')->addError(new FormError('missing backFillStartDate when backFill is enabled'));
            return false;
        }

        if ($dataSourceIntegrationBackFillHistory->getBackFillStartDate() > date_create()) {
            $form->get('backFillStartDate')->addError(new FormError('backFillStartDate can not greater than today'));
            return false;
        }

        if ($dataSourceIntegrationBackFillHistory->getBackFillEndDate() != null && $dataSourceIntegrationBackFillHistory->getBackFillEndDate() > date_create()) {
            $form->get('backFillEndDate')->addError(new FormError('backFillEndDate can not greater than today'));
            return false;
        }

        if ($dataSourceIntegrationBackFillHistory->getBackFillStartDate() > $dataSourceIntegrationBackFillHistory->getBackFillEndDate()) {
            $form->get('backFillStartDate')->addError(new FormError('backFillStartDate can not greater than backFillEndDate'));
            return false;
        }

        return true;
    }
}