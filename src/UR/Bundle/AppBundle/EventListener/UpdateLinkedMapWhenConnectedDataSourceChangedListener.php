<?php

namespace UR\Bundle\AppBundle\EventListener;


use Doctrine\ORM\Event\LifecycleEventArgs;
use UR\Entity\Core\LinkedMapDataSet;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\ModelInterface;
use UR\Service\Parser\Transformer\Collection\Augmentation;
use UR\Service\Parser\Transformer\TransformerFactory;

/**
 * Class ConnectedDataSourceChangeListener
 *
 * update linkedMap if a connected data source change
 *
 * @package UR\Bundle\AppBundle\EventListener
 */
class UpdateLinkedMapWhenConnectedDataSourceChangedListener
{
    /**
     * @var array|ModelInterface[]
     */
    protected $insertedEntities = [];

    private $transformFactory;

    function __construct()
    {
        $this->transformFactory = new TransformerFactory();
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function postPersist(LifecycleEventArgs $args)
    {
        /** @var ConnectedDataSourceInterface $connectedDataSource */
        $connectedDataSource = $args->getEntity();
        if (!$connectedDataSource instanceof ConnectedDataSourceInterface) {
            return;
        }

        $em = $args->getEntityManager();

        $transforms = $connectedDataSource->getTransforms();
        $augmentationTransforms = $this->getAugmentationTransforms($transforms);
        if (!is_array($augmentationTransforms) || count($augmentationTransforms) < 1) {
            return;
        }

        $linkedMapDataSetRepository = $em->getRepository(LinkedMapDataSet::class);
        /** @var Augmentation[] $augmentationTransforms */
        foreach ($augmentationTransforms as $insertedAugmentationTransform) {
            $linkedMapDataSetRepository->override($insertedAugmentationTransform->getMapDataSet(), $connectedDataSource, $insertedAugmentationTransform->getMapFields());
        }
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function postUpdate(LifecycleEventArgs $args)
    {
        /** @var ConnectedDataSourceInterface $connectedDataSource */
        $connectedDataSource = $args->getEntity();
        if (!$connectedDataSource instanceof ConnectedDataSourceInterface) {
            return;
        }

        $em = $args->getEntityManager();

        $uow = $em->getUnitOfWork();
        $changedFields = $uow->getEntityChangeSet($connectedDataSource);

        // only listen on transforms changed
        if (!array_key_exists('transforms', $changedFields)) {
            return;
        }

        $values = $changedFields['transforms'];

        /** @var Augmentation[] $oldAugmentationTransforms */
        $oldAugmentationTransforms = $this->getAugmentationTransforms($values[0]);

        /** @var Augmentation[] $newAugmentationTransforms */
        $newAugmentationTransforms = $this->getAugmentationTransforms($values[1]);

        $linkedMapDataSetRepository = $em->getRepository(LinkedMapDataSet::class);

        /* delete all linked data set with this connected data source */
        if (count($oldAugmentationTransforms) > 0) {
            $linkedMapDataSetRepository->deleteByConnectedDataSource($connectedDataSource->getId());
        }

        /* add augmentation transform */
        /**
         * @var Augmentation[] $afterAugmentationTransforms
         */
        foreach ($newAugmentationTransforms as $insertedAugmentationTransform) {
            $linkedMapDataSetRepository->override($insertedAugmentationTransform->getMapDataSet(), $connectedDataSource, $insertedAugmentationTransform->getMapFields());
        }
    }

    /**
     * get all augmentation transforms from transforms config
     *
     * @param array $transforms
     * @return array
     */
    private function getAugmentationTransforms(array $transforms)
    {
        $result = [];

        foreach ($transforms as $transform) {
            $transformObjects = $this->transformFactory->getAugmentationTransform($transform);

            if (!is_array($transformObjects)) {
                continue;
            }

            foreach ($transformObjects as $transformObject) {
                if ($transformObject instanceof Augmentation) {
                    $result[] = $transformObject;
                }
            }
        }

        return $result;
    }
}