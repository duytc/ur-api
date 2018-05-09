<?php

namespace UR\Form\Type;


use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use UR\Form\DataTransformer\RoleToUserEntityTransformer;
use UR\Model\Core\ReportViewAddConditionalTransformValue;
use UR\Model\Core\ReportViewAddConditionalTransformValueInterface;
use UR\Model\User\Role\AdminInterface;

class ReportViewAddConditionalTransformValueFormType extends AbstractRoleSpecificFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name')
            ->add('defaultValue')
            ->add('sharedConditions')
            ->add('conditions');

        if ($options['userRole'] instanceof AdminInterface) {
            $builder->add(
                $builder->create('publisher')
                    ->addModelTransformer(new RoleToUserEntityTransformer(), false
                    )
            );
        };

        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) {
                /** @var ReportViewAddConditionalTransformValueInterface $reportViewAddConditionalTransformValue */
                $reportViewAddConditionalTransformValue = $event->getData();

                // validate shared conditions
                $sharedConditions = $reportViewAddConditionalTransformValue->getSharedConditions();
                $this->validateSharedConditions($sharedConditions);

                // validate conditions
                $conditions = $reportViewAddConditionalTransformValue->getConditions();
                $this->validateConditions($conditions);
            }
        );
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => ReportViewAddConditionalTransformValue::class,
            'userRole' => null
        ]);
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'ur_form_report_view_add_conditional_transformer_value';
    }

    /**
     * @param $sharedConditions
     * @return bool
     */
    private function validateSharedConditions($sharedConditions)
    {

        return is_array($sharedConditions);
    }

    /**
     * @param $conditions
     * @return bool
     */
    private function validateConditions($conditions)
    {

        return is_array($conditions);
    }
}