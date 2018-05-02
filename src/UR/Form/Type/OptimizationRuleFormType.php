<?php


namespace UR\Form\Type;


use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use UR\Entity\Core\OptimizationRule;
use UR\Form\DataTransformer\RoleToUserEntityTransformer;
use UR\Model\Core\OptimizationRuleInterface;
use UR\Model\User\Role\AdminInterface;

class OptimizationRuleFormType extends AbstractRoleSpecificFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name')
            ->add('token')
            ->add('reportView')
            ->add('dateField')
            ->add('dateRange')
            ->add('identifierFields')
            ->add('optimizeFields')
            ->add('segmentFields')
            ->add('finishLoading');

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
                /** @var OptimizationRuleInterface $optimizationRule */
                $optimizationRule = $event->getData();
                $form = $event->getForm();

                $this->validateDateRange($form, $optimizationRule->getDateRange());
                $this->validateIdentifierFields($form, $optimizationRule->getIdentifierFields());
                $this->validateOptimizeFields($form, $optimizationRule->getOptimizeFields());
                $this->validateSegmentFields($form, $optimizationRule->getSegmentFields());

                if (empty($optimizationRule->getToken())) {
                    $token = bin2hex(random_bytes(15));
                    $optimizationRule->setToken($token);
                }
            }
        );
    }

    private function validateDateRange($form, $dateRange)
    {

        return true;
    }

    private function validateIdentifierFields($form, $identifierFields)
    {

        return true;
    }

    private function validateOptimizeFields($form, $identifierFields)
    {
        return true;
    }

    private function validateSegmentFields($form, $segmentFields)
    {
        return true;
    }

    /**
     * @param OptionsResolverInterface $resolver
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(['data_class' => OptimizationRule::class]);
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'ur_form_optimization_rule';
    }

}