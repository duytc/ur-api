<?php

namespace UR\Form\Type;


use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use UR\Entity\Core\UserTag;
use UR\Form\DataTransformer\RoleToUserEntityTransformer;
use UR\Model\Core\UserTagInterface;
use UR\Model\User\Role\AdminInterface;

class UserTagFormType extends AbstractRoleSpecificFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('publisher')
            ->add('tag');

        if ($this->userRole instanceof AdminInterface) {
            $builder->add(
                $builder->create('publisher')
                    ->addModelTransformer(new RoleToUserEntityTransformer(), false)
            );
        };

        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) {
                /** @var UserTagInterface $userTag */
                $userTag = $event->getData();
                $form = $event->getForm();

                $tag = $userTag->getTag();
                $publisher = $userTag->getPublisher();
            }
        );
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(['data_class' => UserTag::class]);
    }

    public function getName()
    {
        return 'ur_form_userTag';
    }
}