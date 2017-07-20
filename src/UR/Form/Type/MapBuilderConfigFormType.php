<?php

namespace UR\Form\Type;


use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use UR\Entity\Core\MapBuilderConfig;
use UR\Model\Core\MapBuilderConfigInterface;
use UR\Service\StringUtilTrait;

class MapBuilderConfigFormType extends AbstractRoleSpecificFormType
{
    use StringUtilTrait;

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name')
            ->add('filters')
            ->add('mapFields')
            ->add('dataSet')
            ->add('mapDataSet')
            ->add('leftSide');

        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) {
                /** @var MapBuilderConfigInterface $config */
                $config = $event->getData();
                $mapFields = $config->getMapFields();
                foreach ($mapFields as $key => $field) {
                    unset($mapFields[$key]);
                    $mapFields[$this->getStandardName($key)] = $field;
                }

                $config->setMapFields($mapFields);
            }
        );
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(['data_class' => MapBuilderConfig::class]);
    }

    public function getName()
    {
        return 'ur_form_map_builder_config';
    }
}