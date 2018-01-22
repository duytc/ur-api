<?php

use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\Entity\Core\Learner;
use UR\Model\Core\AutoOptimizationConfigInterface;
use UR\Model\Core\LearnerInterface;

const LINEAR_REGRESSION = "LinearRegression";
$loader = require_once __DIR__ . '/../app/autoload.php';
require_once __DIR__ . '/../app/AppKernel.php';

$kernel = new AppKernel('dev', true);
$kernel->boot();

/** @var ContainerInterface $container */
$container = $kernel->getContainer();

$autoOptimizationConfigIds = [2,3];
$autoOptimizationConfigManager = $container->get('ur.domain_manager.auto_optimization_config');
$dataTrainingManager = $container->get('ur.service.auto_optimization.data_training_table_service');
$learnerManager = $container->get('ur.domain_manager.learner');
$entityManager = $container->get('doctrine.orm.entity_manager');
$logger = $container->get('logger');

$params = null;

$model = [];
$coefficient = [];
$forecastFactorValues = [];
$categoricalFieldWeight = [];

$logger->info('Creating learner model');

foreach ($autoOptimizationConfigIds as $autoOptimizationConfigId) {
    /**@var AutoOptimizationConfigInterface $autoOptimizationConfig */
    $autoOptimizationConfig = $autoOptimizationConfigManager->find($autoOptimizationConfigId);
    if (!$autoOptimizationConfig instanceof AutoOptimizationConfigInterface) {
        continue;
    }

    $factors = $autoOptimizationConfig->getFactors();
    $positiveFactors = $autoOptimizationConfig->getPositiveFactors();
    $negativeFactors = $autoOptimizationConfig->getNegativeFactors();
    $types = $autoOptimizationConfig->getFieldTypes();

    $identifiers = $dataTrainingManager->getIdentifiersForAutoOptimizationConfig($autoOptimizationConfig, $params);

    if (empty($identifiers)) {
        continue;
    }

    foreach ($identifiers as $identifier) {
        $logger->info(sprintf('Create learner model for identifier =%s', $identifier));
        foreach ($factors as $factor) {
            if (in_array($factor, $positiveFactors)) {
                $coefficient[$factor] = float_rand(1, 5);
            } elseif (in_array($factor, $negativeFactors)) {
                $coefficient[$factor] = float_rand(-5, -1);
            } else {
                $coefficient[$factor] = float_rand(1, 15);
            }

            $forecastFactorValues[$factor] = float_rand(10, 100);

            if ($types[$factor] == 'text') {
                $randomValues = [];
                $allValuesOfFactors = $dataTrainingManager->getAllValuesOfOneColumn($autoOptimizationConfig, $factor);
                foreach ($allValuesOfFactors as $allValuesOfFactor) {
                    $randomValues[$allValuesOfFactor] = float_rand(0, 10) / 10;
                }
                $categoricalFieldWeight[$factor] = $randomValues;
                $forecastFactorValues[$factor] = max($categoricalFieldWeight[$factor]);
            }
        }
        $model['coefficient'] = $coefficient;
        $model['intercept'] = float_rand(5, 10);

        $learner = new Learner();
        $learner->setAutoOptimizationConfig($autoOptimizationConfig);
        $learner->setIdentifier($identifier);
        $learner->setModel($model);
        $learner->setForecastFactorValues($forecastFactorValues);
        $learner->setCategoricalFieldWeights($categoricalFieldWeight);
        $learner->setUpdatedDate(new DateTime());
        $learner->setType("" . LINEAR_REGRESSION . "");

        $learnerInDataBase = $learnerManager->getLearnerByParams($autoOptimizationConfig, $identifier, LINEAR_REGRESSION);
        if ($learnerInDataBase instanceof LearnerInterface) {
            $learnerManager->delete($learnerInDataBase);
        }

        $learnerManager->save($learner);
    }
}
$entityManager->flush();
$logger->info(sprintf('Finishing program, number model create %d', count($identifiers)));

function float_rand($Min, $Max, $round = 3)
{
    //validate input
    if ($Min > $Max) {
        $min = $Max;
        $max = $Min;
    } else {
        $min = $Min;
        $max = $Max;
    }
    $randomFloat = $min + mt_rand() / mt_getrandmax() * ($max - $min);
    if ($round > 0)
        $randomFloat = round($randomFloat, $round);

    return $randomFloat;
}




