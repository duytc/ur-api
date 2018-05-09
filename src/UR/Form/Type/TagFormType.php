<?php

namespace UR\Form\Type;


use Doctrine\Common\Collections\Collection;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use UR\DomainManager\TagManagerInterface;
use UR\Entity\Core\Tag;
use UR\Model\Core\IntegrationTagInterface;
use UR\Model\Core\ReportViewTemplateTagInterface;
use UR\Model\Core\TagInterface;
use UR\Model\Core\UserTagInterface;

class TagFormType extends AbstractRoleSpecificFormType
{
    /** @var TagManagerInterface */
    protected $tagManager;

    /**
     * TagFormType constructor.
     * @param TagManagerInterface $tagManager
     */
    public function __construct(TagManagerInterface $tagManager)
    {
        $this->tagManager = $tagManager;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name');

        $builder->add('userTags', CollectionType::class, array(
            'mapped' => true,
            'entry_type' => UserTagFormType::class,
            'allow_add' => true,
            'allow_delete' => true,
        ));

        $builder->add('integrationTags', CollectionType::class, array(
            'mapped' => true,
            'entry_type' => IntegrationTagFormType::class,
            'allow_add' => true,
            'allow_delete' => true,
        ));

        $builder->add('reportViewTemplateTags', CollectionType::class, array(
            'mapped' => true,
            'entry_type' => ReportViewTemplateTagFormType::class,
            'allow_add' => true,
            'allow_delete' => true,
        ));

        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) {
                /** @var TagInterface $tag */
                $tag = $event->getData();
                $form = $event->getForm();

                $this->validateTag($tag, $form);

                $userTags = $tag->getUserTags();
                if ($userTags instanceof Collection) {
                    $userTags = $userTags->toArray();
                }

                foreach ($userTags as $userTag) {
                    if (!$userTag instanceof UserTagInterface) {
                        continue;
                    }
                    $userTag->setTag($tag);
                }
                $tag->setUserTags($userTags);

                $integrationTags = $tag->getIntegrationTags();
                if ($integrationTags instanceof Collection) {
                    $integrationTags = $integrationTags->toArray();
                }

                foreach ($integrationTags as $integrationTag) {
                    if (!$integrationTag instanceof IntegrationTagInterface) {
                        continue;
                    }
                    $integrationTag->setTag($tag);
                }
                $tag->setIntegrationTags($integrationTags);

                $reportTemplateTags = $tag->getReportViewTemplateTags();
                if ($reportTemplateTags instanceof Collection) {
                    $reportTemplateTags = $reportTemplateTags->toArray();
                }

                foreach ($reportTemplateTags as $reportTemplateTag) {
                    if (!$reportTemplateTag instanceof ReportViewTemplateTagInterface) {
                        continue;
                    }
                    $reportTemplateTag->setTag($tag);
                }
                $tag->setReportViewTemplateTags($reportTemplateTags);
            }
        );
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Tag::class,
            'userRole' => null
        ]);
    }

    public function getName()
    {
        return 'ur_form_tag';
    }

    /**
     * @param TagInterface $tag
     * @param FormInterface $form
     */
    private function validateTag(TagInterface $tag, FormInterface $form)
    {
        /** Validate name of tag*/
        $name = $tag->getName();

        /** @var TagInterface $existTag */
        $existTag = $this->tagManager->findByName($name);

        if (!empty($existTag) && $tag->getId() != $existTag->getId()) {
            $form->get('name')->addError(new FormError(sprintf('Tag with name %s is exist. Please retry with different name', $name)));
        }
    }
}