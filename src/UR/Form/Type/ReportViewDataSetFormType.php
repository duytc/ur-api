<?php

namespace UR\Form\Type;


use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use UR\Entity\Core\ReportViewDataSet;
use UR\Model\Core\ReportViewDataSetInterface;

class ReportViewDataSetFormType extends AbstractRoleSpecificFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('reportView')
            ->add('filters')
            ->add('dataSet')
            ->add('dimensions')
            ->add('metrics');

        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) {
                /** @var ReportViewDataSetInterface $reportViewDataSet */
                $reportViewDataSet = $event->getData();
                $form = $event->getForm();

                $this->validateFilters($form, $reportViewDataSet->getFilters());

                $this->validateDimensions($form, $reportViewDataSet->getDimensions());

                $this->validateMetrics($form, $reportViewDataSet->getMetrics());
            }
        );
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(['data_class' => ReportViewDataSet::class]);
    }

    public function getName()
    {
        return 'ur_form_report_view_data_set';
    }

    private function validateFilters($form, $filters)
    {
        /*
         * [
         *    {
         *       "field":"ad_impressions",
         *       "type":"number",
         *       "comparison":"greater or equal",
         *       "compareValue":0
         *    },
         *    {
         *       "field":"date",
         *       "type":"date",
         *       "format":null,
         *       "dateValue":{
         *          "startDate":"2017-04-24",
         *          "endDate":"2017-04-24"
         *       },
         *       "userDefine":true,
         *       "dateType":"customRange"
         *    },
         *    ...
         * ]
         */

        // TODO: validate filters

        return true;
    }

    private function validateDimensions($form, $dimensions)
    {
        /*
         * [
         *     "date",
         *     "tag",
         *     "__date_year",
         *     "__date_month",
         *     "__date_day",
         *     ...
         * ]
         */

        // TODO: validate dimensions

        return true;
    }

    private function validateMetrics($form, $metrics)
    {
        /*
         * [
         *    "ad_requests",
         *    "ad_impressions",
         *    "revenue",
         *    ...
         * ]
         */

        // TODO: validate metrics

        return true;
    }
}