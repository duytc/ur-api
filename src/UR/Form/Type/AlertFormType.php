<?php

namespace UR\Form\Type;


use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use UR\Entity\Core\Alert;
use UR\Form\DataTransformer\RoleToUserEntityTransformer;
use UR\Model\Core\AlertInterface;
use UR\Model\User\Role\AdminInterface;

class AlertFormType extends AbstractRoleSpecificFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('code')
            ->add('detail')
            ->add('isRead')
            ->add('dataSource')
            ->add('type')
            ->add('isSent')
            ->add('optimizationIntegration');

        if ($options['userRole'] instanceof AdminInterface) {
            $builder->add(
                $builder->create('publisher')
                    ->addModelTransformer(new RoleToUserEntityTransformer(), false)
            );
        };

        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) {
                /** @var AlertInterface $alert */
                $alert = $event->getData();
                $form = $event->getForm();

                // validate alert code
                $alertCode = $alert->getCode();
                $this->validateAlertCode($alertCode, $form);

                // validate alert details
                $alertDetails = $alert->getDetail();
                $this->validateAlertDetails($alertDetails, $form);

                // validate alert type
                $alertType = $alert->getType();
                $this->validateAlertType($alertType, $form);
            }
        );
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Alert::class,
            'userRole' => null
        ]);
    }

    public function getName()
    {
        return 'ur_form_alert';
    }

    /**
     * validate alert code
     *
     * @param int $alertCode
     * @param FormInterface $form
     * @return bool
     */
    private function validateAlertCode($alertCode, FormInterface $form)
    {
        // TODO: centralize all alert codes to AlertInterface, so that we have one place to know all supported alert codes

        // validate if is non-negative integer
        if (!is_integer($alertCode) || $alertCode < 0) {
            $form->addError(new FormError('Expect alert code is not a non-negative integer'));
            return false;
        }

        // validate if alert code is supported
        if (!in_array($alertCode, Alert::$SUPPORTED_ALERT_CODES)) {
            $form->addError(new FormError('Not supported this alert code'));
            return false;
        }

        return true;
    }

    /**
     * validate alert details
     *
     * @param array $alertDetails
     * @param FormInterface $form
     * @return bool
     */
    private function validateAlertDetails($alertDetails, FormInterface $form)
    {
        if (!is_array($alertDetails)) {
            $form->addError(new FormError('Expect alert details is json_array'));
            return false;
        }

        // TODO: validate more...

        return true;
    }

    /**
     * validate alert type
     * @param $alertType
     * @param FormInterface $form
     * @return bool
     */
    private function validateAlertType($alertType, FormInterface $form)
    {
        $alertTypes = [Alert::ALERT_TYPE_ERROR, Alert::ALERT_TYPE_WARNING, Alert::ALERT_TYPE_INFO, Alert::ALERT_TYPE_ACTION_REQUIRED];
        if (!in_array($alertType, $alertTypes)) {
            $form->addError(new FormError('Expect alert type is  in error, warning, info, actionRequired '));
            return false;
        }

        // TODO: validate more...

        return true;
    }
}