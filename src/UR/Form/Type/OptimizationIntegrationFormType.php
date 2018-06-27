<?php

namespace UR\Form\Type;

use DateTime;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use UR\Entity\Core\OptimizationIntegration;
use UR\Model\Core\OptimizationIntegrationInterface;
use UR\Service\OptimizationRule\AutomatedOptimization\Pubvantage\PubvantageOptimizer;

class OptimizationIntegrationFormType extends AbstractRoleSpecificFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name')
            ->add('optimizationRule')
            ->add('identifierMapping')
            ->add('identifierField')
            ->add('segments')
            ->add('supplies')
            ->add('adSlots')
            ->add('active')
            ->add('optimizationAlerts')
            ->add('optimizationFrequency')
            ->add('platformIntegration')
            ->add('videoPublishers')
            ->add('waterfallTags')
            ->add('reminder');

        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) {
                /** @var OptimizationIntegrationInterface $optimizationIntegration */
                $optimizationIntegration = $event->getData();
                $form = $event->getForm();

                // validate alert code
                $name = $optimizationIntegration->getName();
                $this->validateName($name, $form);
                $this->validatePlatformIntegration($optimizationIntegration->getPlatformIntegration(), $form);
                $this->validateSupplies($optimizationIntegration->getSupplies(), $optimizationIntegration->getPlatformIntegration(), $form);
                $this->validateIdentifierMapping($optimizationIntegration->getIdentifierMapping(), $form);
                $this->validateIdentifierField($optimizationIntegration->getIdentifierField(), $form);
                $this->validateOptimizeSegments($optimizationIntegration->getSegments(), $form);
                $this->validateOptimizeAlerts($optimizationIntegration->getOptimizationAlerts(), $form);
                $this->validateOptimizationFrequency($optimizationIntegration->getOptimizationFrequency(), $form);
                $this->setActiveForOptimizationIntegration($optimizationIntegration);
                if (empty($optimizationIntegration->getId())) {
                    $optimizationIntegration->setStartRescoreAt(new DateTime('now'));
                    $optimizationIntegration->setEndRescoreAt(new DateTime('now'));
                }
            }
        );
    }

    /**
     * @param OptionsResolverInterface $resolver
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(['data_class' => OptimizationIntegration::class]);
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'ur_form_optimization_integration';
    }

    /**
     * validate name must be at least 2 characters
     *
     * @param string $name
     * @param FormInterface $form
     * @return bool
     */
    private function validateName($name, FormInterface $form)
    {
        if (empty($name)) {
            $form->addError(new FormError('Name can not be null.'));
            return false;
        }

        if (strlen($name) < 2) {
            $form->addError(new FormError('Name must be at least 2 characters.'));
            return false;
        }

        return true;
    }

    /**
     * validate identifierMapping
     *
     * @param array $identifierMapping
     * @param FormInterface $form
     * @return bool
     */
    private function validateIdentifierMapping($identifierMapping, FormInterface $form)
    {
        if (empty($identifierMapping)) {
            $form->addError(new FormError('Expect "Identifier Mapping" must be not null'));
            return false;
        }

        return true;
    }

    /*
     * "segments":[
       {
           "dimension":"country",
            "toFactor":"country_102",
            "neededValue":[
               "GB",
                "US"
            ]
        }
     ]
     */
    /**
     * validate segments
     *
     * @param array $segments
     * @param FormInterface $form
     * @return bool
     */
    private function validateOptimizeSegments($segments, FormInterface $form)
    {
        /* allow the user does not choose segments */
        if (empty($segments)) {
            return true;
        }

        if (!is_array($segments)) {
            $form->addError(new FormError('Expect "segments" to be an array or array must be not null'));
            return false;
        }

        foreach ($segments as $segment) {
            if (!array_key_exists('dimension', $segment)) {
                $form->addError(new FormError('Segment Mapping is missing dimension key.'));
                return false;
            }

            if (!array_key_exists('toFactor', $segment)) {
                $form->addError(new FormError('Segment Mapping is missing toFactor key.'));
                return false;
            }

            if (!array_key_exists('neededValue', $segment)) {
                $form->addError(new FormError('Segment Mapping is missing neededValue key.'));
                return false;
            }
        }

        return true;
    }

    /**
     * validate Identifier Field
     *
     * @param array $identifierField
     * @param FormInterface $form
     * @return bool
     */
    private function validateIdentifierField($identifierField, $form)
    {
        if (empty($identifierField)) {
            $form->addError(new FormError('Expect "Identifier Field" must be not null'));
            return false;
        }

        return true;
    }

    /**
     * validate Supplies can not be null
     *
     * @param array $supplies
     * @param $PlatformIntegration
     * @param FormInterface $form
     * @return bool
     */
    private function validateSupplies($supplies, $PlatformIntegration, $form)
    {
        if ($PlatformIntegration !== PubvantageOptimizer::PLATFORM_INTEGRATION) {
            return true;
        }

        if (!is_array($supplies) || empty($supplies)) {
            $form->addError(new FormError('Expect Supplies must be not null'));
            return false;
        }

        return true;
    }

    /**
     * validate optimize alerts
     *
     * @param $optimizationAlerts
     * @param FormInterface $form
     * @return bool
     */
    private function validateOptimizeAlerts($optimizationAlerts, $form)
    {
        if (!in_array($optimizationAlerts, OptimizationIntegrationInterface::SUPPORT_OPTIMIZATION_ALERTS)) {
            $form->addError(new FormError(sprintf('Not support Optimization Alerts as %s', $optimizationAlerts)));

            return false;
        }

        return true;
    }

    /**
     * @param $optimizationFrequency
     * @param FormInterface $form
     * @return bool
     */
    private function validateOptimizationFrequency($optimizationFrequency, $form)
    {
        if (!in_array($optimizationFrequency, OptimizationIntegrationInterface::SUPPORT_OPTIMIZATION_FREQUENCIES)) {
            $form->addError(new FormError(sprintf('Not support optimization frequency as %s', $optimizationFrequency)));

            return false;
        }

        return true;
    }

    /**
     * @param $PlatformIntegration
     * @param FormInterface $form
     * @return bool
     */
    private function validatePlatformIntegration($PlatformIntegration, $form)
    {
        if (!in_array($PlatformIntegration, OptimizationIntegrationInterface::SUPPORT_PLATFORM_INTEGRATION)) {
            $form->addError(new FormError(sprintf('Not support platform as %s', $PlatformIntegration)));

            return false;
        }

        return true;
    }

    /**
     * @param OptimizationIntegrationInterface $optimizationIntegration
     */
    private function setActiveForOptimizationIntegration(OptimizationIntegrationInterface $optimizationIntegration)
    {
        if ($optimizationIntegration->getOptimizationAlerts() == OptimizationIntegrationInterface::ALERT_AUTO_OPTIMIZATION) {
            $optimizationIntegration->setActive(OptimizationIntegrationInterface::ACTIVE_APPLY);
        }

        if ($optimizationIntegration->getOptimizationAlerts() == OptimizationIntegrationInterface::ALERT_AUTO_OPTIMIZE_AND_NOTICE_ME) {
            $optimizationIntegration->setActive(OptimizationIntegrationInterface::ACTIVE_APPLY);
        }
    }
}