<?php

namespace UR\Form\Type;


use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use UR\Entity\Core\ReportView;
use UR\Form\DataTransformer\RoleToUserEntityTransformer;
use UR\Model\Core\ReportViewDataSetInterface;
use UR\Model\Core\ReportViewInterface;
use UR\Model\Core\ReportViewMultiViewInterface;
use UR\Model\User\Role\AdminInterface;

class ReportViewFormType extends AbstractRoleSpecificFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
//            ->add('dataSets')
            ->add('name')
            ->add('alias')
            ->add('transforms')
            ->add('joinBy')
            ->add('weightedCalculations')
            ->add('multiView')
//            ->add('reportViews')
            ->add('showInTotal')
            ->add('formats')
            ->add('fieldTypes')
            ->add('subReportsIncluded')
        ;

        $builder
            ->add('reportViewMultiViews', 'collection', array(
                    'mapped' => true,
                    'type' => new ReportViewMultiViewFormType(),
                    'allow_add' => true,
                    'allow_delete' => true,
                )
            )
            ->add('reportViewDataSets', 'collection', array(
                    'mapped' => true,
                    'type' => new ReportViewDataSetFormType(),
                    'allow_add' => true,
                    'allow_delete' => true,
                )
            );

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
                /** @var ReportViewInterface $reportView */
                $reportView = $event->getData();

                if (!is_array($reportView->getFieldTypes())) {
                    $reportView->setFieldTypes([]);
                }

                $alias = $reportView->getAlias();

                if (!is_string($alias)) {
                    $reportView->setAlias($reportView->getName());
                }

                $reportViewDataSets = $reportView->getReportViewDataSets();

                /**
                 * @var ReportViewDataSetInterface $reportViewDataSet
                 */
                foreach($reportViewDataSets as $reportViewDataSet) {
                    $reportViewDataSet->setReportView($reportView);
                }

                $reportViewMultiViews = $reportView->getReportViewMultiViews();

                /**
                 * @var ReportViewMultiViewInterface $reportViewMultiView
                 */
                foreach($reportViewMultiViews as $reportViewMultiView) {
                    $reportViewMultiView->setReportView($reportView);
                }
            }
        );
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(['data_class' => ReportView::class]);
    }

    public function getName()
    {
        return 'ur_form_report_view';
    }
}