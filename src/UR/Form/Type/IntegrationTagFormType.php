<?php

namespace UR\Form\Type;


use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use UR\Entity\Core\IntegrationTag;
use UR\Model\Core\IntegrationTagInterface;

class IntegrationTagFormType extends AbstractRoleSpecificFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('tag')
            ->add('integration');

        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) {
                /** @var IntegrationTagInterface $integrationTag */
                $integrationTag = $event->getData();
                $form = $event->getForm();

                $tag = $integrationTag->getTag();
                $integration = $integrationTag->getIntegration();
            }
        );
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => IntegrationTag::class,
            'userRole' => null]);
    }

    public function getName()
    {
        return 'ur_form_integrationTag';
    }
}