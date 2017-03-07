<?php

namespace UR\DomainManager;

use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\Proxy\Exception\InvalidArgumentException;
use ReflectionClass;
use UR\Model\Core\ReportViewMultiViewInterface;
use UR\Model\ModelInterface;
use UR\Repository\Core\ReportViewMultiViewRepositoryInterface;

class ReportViewMultiViewManager implements ReportViewMultiViewManagerInterface
{
	/**
	 * @var ObjectManager
	 */
	private $objectManager;
	/**
	 * @var ReportViewMultiViewRepositoryInterface
	 */
	private $reportViewMultiViewRepository;


	/**
	 * ReportViewMultiViewManager constructor.
	 * @param ObjectManager $objectManager
	 * @param ReportViewMultiViewRepositoryInterface $reportViewMultiViewRepository
	 */
	public function __construct(ObjectManager $objectManager, ReportViewMultiViewRepositoryInterface $reportViewMultiViewRepository)
	{
		$this->objectManager = $objectManager;
		$this->reportViewMultiViewRepository = $reportViewMultiViewRepository;
	}

	/**
	 * @inheritdoc
	 */
	public function supportsEntity($entity)
	{
		return is_subclass_of($entity, ReportViewMultiViewInterface::class);
	}

	/**
	 * @inheritdoc
	 */
	public function save(ModelInterface $reportViewMultiView)
	{
		if (!$reportViewMultiView instanceof ReportViewMultiViewInterface) throw new InvalidArgumentException('expect ReportViewInterface object');

		try {
			$this->objectManager->persist($reportViewMultiView);
			$this->objectManager->flush();
		} catch (\Exception $e) {
			$msg = $e->getMessage();
		}
	}

	/**
	 * @inheritdoc
	 */
	public function delete(ModelInterface $reportViewMultiView)
	{
		if (!$reportViewMultiView instanceof ReportViewMultiViewInterface) throw new InvalidArgumentException('expect ReportViewInterface object');

		try {
			$this->objectManager->remove($reportViewMultiView);
			$this->objectManager->flush();
		} catch (\Exception $e) {
			$msg = $e->getMessage();
		}
	}

	/**
	 * @inheritdoc
	 */
	public function createNew()
	{
		$entity = new ReflectionClass($this->reportViewMultiViewRepository->getClassName());
		return $entity->newInstance();
	}

	/**
	 * @inheritdoc
	 */
	public function find($id)
	{
		$this->reportViewMultiViewRepository->find($id);
	}

	/**
	 * @inheritdoc
	 */
	public function all($limit = null, $offset = null)
	{
		return $this->reportViewMultiViewRepository->findBy($criteria = [], $orderBy = null, $limit, $offset);
	}
}