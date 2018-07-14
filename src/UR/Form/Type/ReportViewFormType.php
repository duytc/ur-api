<?php

namespace UR\Form\Type;


use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use UR\Behaviors\LargeReportViewUtilTrait;
use UR\Entity\Core\ReportView;
use UR\Form\DataTransformer\RoleToUserEntityTransformer;
use UR\Model\Core\ReportViewDataSetInterface;
use UR\Model\Core\ReportViewInterface;
use UR\Model\User\Role\AdminInterface;

class ReportViewFormType extends AbstractRoleSpecificFormType
{
    use LargeReportViewUtilTrait;

    private $originalReportViewDataSets;
    /**
     * @var EntityManagerInterface
     */
    private $em;

    /** @var  int */
    private $largeThreshold;

    /**
     * ReportViewFormType constructor.
     * @param EntityManagerInterface $em
     * @param $largeThreshold
     */
    public function __construct(EntityManagerInterface $em, $largeThreshold)
    {
        $this->em = $em;
        $this->largeThreshold = $largeThreshold;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name')
            ->add('transforms')
            ->add('joinBy')
            ->add('weightedCalculations')
            ->add('showInTotal')
            ->add('formats')
            ->add('fieldTypes')
            ->add('isShowDataSetName')
            ->add('enableCustomDimensionMetric')
            ->add('subView')
            ->add('masterReportView')
            ->add('filters')
            ->add('largeReport')
            ->add('availableToRun')
            ->add('availableToChange')
            ->add('preCalculateTable')
            ->add('requireJoin')
        ;

        $builder
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
            FormEvents::PRE_SET_DATA,
            function (FormEvent $event) {
                /** @var ReportViewInterface $reportView */
                $reportView = $event->getData();
                $this->originalReportViewDataSets = $reportView->getReportViewDataSets();
                if ($this->originalReportViewDataSets === null) {
                    $this->originalReportViewDataSets = [];
                }
            }
        );

        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) {
                /** @var ReportViewInterface $reportView */
                $reportView = $event->getData();
                $form = $event->getForm();

                $this->validateTransforms($form, $reportView->getTransforms());

                $this->validateJoinBy($form, $reportView->getJoinBy());

                $this->validateWeightedCalculations($form, $reportView->getWeightedCalculations());

                $this->validateShowInTotal($form, $reportView->getShowInTotal());

                $this->validateFormats($form, $reportView->getFormats());

                $this->validateFieldTypes($form, $reportView->getFieldTypes());

                if (!is_array($reportView->getFieldTypes())) {
                    $reportView->setFieldTypes([]);
                }

                $reportViewDataSets = $reportView->getReportViewDataSets();

                /**
                 * @var ReportViewDataSetInterface $reportViewDataSet
                 */
                foreach ($reportViewDataSets as $reportViewDataSet) {
                    $reportViewDataSet->setReportView($reportView);
                }

                $largeReport = $this->isLargeReportView($reportView, $this->getLargeThreshold());
//                $reportView->setLargeReport($largeReport);
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

    private function validateTransforms($form, $transforms)
    {
        // TODO: validate transforms

        return true;
    }

    private function validateJoinBy($form, $joinBy)
    {
        // TODO: validate joinBy

        return true;
    }

    private function validateWeightedCalculations($form, $weightedCalculations)
    {
        // TODO: validate weightedCalculations

        return true;
    }

    private function validateFormats($form, $formats)
    {
        // TODO: validate formats

        return true;
    }

    private function validateFieldTypes($form, $fieldTypes)
    {
        // TODO: validate fieldTypes

        return true;
    }

    /**
     * @return int
     */
    public function getLargeThreshold()
    {
        return $this->largeThreshold;
    }

    /**
     * @param $form
     * @param $getShowInTotal
     * @return bool
     * @throws Exception
     */
    private function validateShowInTotal($form, $getShowInTotal)
    {
        if (empty($getShowInTotal)) {
            return true;
        }

        foreach ($getShowInTotal as $oneGroup) {
            if (!array_key_exists('fields', $oneGroup) || !array_key_exists('aliasName', $oneGroup)) {
                continue;
            }

            $fields = $oneGroup['fields'];
            $aliasNames = $oneGroup['aliasName'];

            $originalNames = [];
            foreach ($aliasNames as $aliasName) {
                if (!array_key_exists('originalName', $aliasName)) {
                    throw new  Exception(sprintf('Invalid submit json for show in total'));
                }

                array_push($originalNames, $aliasName['originalName']);
            }

            $diffKeys = array_diff($fields, $originalNames);

            if (!empty($diffKeys)) {
                    throw new  Exception(sprintf('There is at least one total or average field which does not have alias name'));
            }
        }

        return true;
    }
}