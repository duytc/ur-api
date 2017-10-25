<?php

namespace UR\DomainManager;


use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Instantiator\Exception\InvalidArgumentException;
use ReflectionClass;
use UR\Model\Core\ReportViewAddConditionalTransformValueInterface;
use UR\Model\ModelInterface;
use UR\Repository\Core\ReportViewAddConditionalTransformValueRepositoryInterface;

class ReportViewAddConditionalTransformValueManager implements ReportViewAddConditionalTransformValueManagerInterface
{
	/**
	 * @var ObjectManager
	 */
	private $om;
	/**
	 * @var ReportViewAddConditionalTransformValueRepositoryInterface
	 */
	private $repository;

	/**
	 * ReportViewAddConditionalTransformerValue constructor.
	 * @param ObjectManager $om
	 * @param ReportViewAddConditionalTransformValueRepositoryInterface $repository
	 */
	public function __construct(ObjectManager $om, ReportViewAddConditionalTransformValueRepositoryInterface $repository)
	{
		$this->om = $om;
		$this->repository = $repository;
	}

	/**
	 * @inheritdoc
	 */
	public function supportsEntity($entity)
	{
		return is_subclass_of($entity, ReportViewAddConditionalTransformValueInterface::class);
	}

	/**
	 * @inheritdoc
	 */
	public function save(ModelInterface $reportViewAddConditionalTransformerValue)
	{
		if (!$reportViewAddConditionalTransformerValue instanceof ReportViewAddConditionalTransformValueInterface) {
			throw new InvalidArgumentException('expect ReportViewAddConditionalTransformerValueInterface object');
		}

		try {
			$this->om->persist($reportViewAddConditionalTransformerValue);
			$this->om->flush();
		} catch (\Exception $e) {
			throw $e;
		}
	}

	/**
	 * @inheritdoc
	 */

	public function delete(ModelInterface $reportViewAddConditionalTransformerValue)
	{
		if (!$reportViewAddConditionalTransformerValue instanceof ReportViewAddConditionalTransformValueInterface) throw new InvalidArgumentException('expect ReportViewAddConditionalTransformerValueInterface object');

		$this->om->remove($reportViewAddConditionalTransformerValue);
		$this->om->flush();
	}

	/**
	 * @inheritdoc
	 */
	public function createNew()
	{
		$entity = new ReflectionClass($this->repository->getClassName());

		return $entity->newInstance();
	}

	/**
	 * @inheritdoc
	 */
	public function find($id)
	{
		return $this->repository->find($id);
	}

	/**
	 * @inheritdoc
	 */
	public function all($limit = null, $offset = null)
	{
		return $this->repository->findBy($criteria = [], $orderBy = null, $limit, $offset);
	}
}