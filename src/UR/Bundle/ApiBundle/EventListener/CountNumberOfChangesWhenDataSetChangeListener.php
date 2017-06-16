<?php

namespace UR\Bundle\ApiBundle\EventListener;

use Doctrine\ORM\Event\PreUpdateEventArgs;
use UR\Model\Core\DataSetInterface;

class CountNumberOfChangesWhenDataSetChangeListener
{
    /**
     * @param PreUpdateEventArgs $args
     */
    public function preUpdate(PreUpdateEventArgs $args)
    {
        $dataSet = $args->getEntity();

        if (!$dataSet instanceof DataSetInterface) {
            return;
        }

        if ($args->hasChangedField('dimensions') || $args->hasChangedField('metrics')) {
            $dataSet->increaseNoChanges();
        }
    }
}