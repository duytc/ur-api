<?php

namespace UR\Form\Type;

use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use UR\Entity\Core\DataSourceIntegrationBackfillHistory;
use UR\Model\Core\DataSourceIntegrationBackfillHistoryInterface;
use UR\Model\Core\Integration;

class DataSourceIntegrationBackfillHistoryFormType extends AbstractRoleSpecificFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('id')
            ->add('dataSourceIntegration')
            ->add('lastExecutedAt')
            // back fill feature
            ->add('backFillStartDate', DateType::class, array(
                // render as a single text box
                'widget' => 'single_text',
            ))
            ->add('backFillEndDate', DateType::class, array(
                // render as a single text box
                'widget' => 'single_text',
            ));

    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(['data_class' => DataSourceIntegrationBackfillHistory::class,]);
    }

    public function getName()
    {
        return 'ur_form_data_source_integration_backfill_history';
    }

}