<?php

namespace UR\Form\Type;


use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use UR\Entity\Core\ReportViewMultiView;
use UR\Model\Core\ReportViewMultiViewInterface;

class ReportViewMultiViewFormType extends AbstractRoleSpecificFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('reportView')
            ->add('filters')
            ->add('subView')
            ->add('dimensions')
            ->add('metrics')
            ->add('enableCustomDimensionMetric');

        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) {
                /** @var ReportViewMultiViewInterface $reportViewMultiView */
                $reportViewMultiView = $event->getData();
                $form = $event->getForm();

                $enableCustomDimensionMetric = $reportViewMultiView->isEnableCustomDimensionMetric();
                if (!$enableCustomDimensionMetric) {
                    $reportViewMultiView->setEnableCustomDimensionMetric(false);
                }

                $this->validateFilters($form, $reportViewMultiView->getFilters());

                $this->validateDimensions($form, $reportViewMultiView->getDimensions());

                $this->validateMetrics($form, $reportViewMultiView->getMetrics());
            }
        );
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(['data_class' => ReportViewMultiView::class]);
    }

    public function getName()
    {
        return 'ur_form_report_view_multi_view';
    }

    private function validateFilters($form, $filters)
    {
        // TODO: validate filters

        return true;
    }

    private function validateDimensions($form, $dimensions)
    {
        // TODO: validate dimensions

        return true;
    }

    private function validateMetrics($form, $metrics)
    {
        // TODO: validate metrics

        return true;
    }
}