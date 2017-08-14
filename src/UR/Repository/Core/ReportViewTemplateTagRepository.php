<?php

namespace UR\Repository\Core;

use Doctrine\ORM\EntityRepository;
use UR\Model\Core\ReportViewTemplateInterface;
use UR\Model\Core\TagInterface;
use UR\Model\User\Role\PublisherInterface;

class ReportViewTemplateTagRepository extends EntityRepository implements ReportViewTemplateTagRepositoryInterface
{

    /**
     * @inheritdoc
     */
    public function findByReportViewTemplate(ReportViewTemplateInterface $reportViewTemplate)
    {
        return $this->createQueryBuilder('rt')
            ->where('rt.reportViewTemplate = :reportViewTemplate')
            ->setParameter('reportViewTemplate', $reportViewTemplate)->getQuery()->getResult();
    }

    /**
     * @inheritdoc
     */
    public function findByTag(TagInterface $tag) {
        return $this->createQueryBuilder('rt')
            ->where('rt.tag = :tag')
            ->setParameter('tag', $tag)->getQuery()->getResult();
    }
    /**
     * @inheritdoc
     */
    public function findByPublisher(PublisherInterface $publisher)
    {
        $qb = $this->createQueryBuilder('rptt')
            ->leftJoin('rptt.tag', 't')
            ->leftJoin('t.userTags', 'tu')
            ->where('tu.publisher = :publisher')
            ->setParameter('publisher', $publisher);

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * @inheritdoc
     */
    public function findByReportViewTemplateAndTag(ReportViewTemplateInterface $reportViewTemplate, TagInterface $tag)
    {
        $qb = $this->createQueryBuilder('rptt')
            ->where('rptt.tag = :tag')
            ->andWhere('rptt.reportViewTemplate = :reportViewTemplate')
            ->setParameter('tag', $tag)
            ->setParameter('reportViewTemplate', $reportViewTemplate);

        return $qb->getQuery()->getOneOrNullResult();
    }
}