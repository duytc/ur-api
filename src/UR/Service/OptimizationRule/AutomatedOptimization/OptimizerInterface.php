<?php


namespace UR\Service\OptimizationRule\AutomatedOptimization;
use UR\Model\Core\OptimizationIntegrationInterface;

interface OptimizerInterface
{
    /**
     * @param OptimizationIntegrationInterface $optimizationIntegration
     * @return boolean
     */
    public function supportOptimizationIntegration(OptimizationIntegrationInterface $optimizationIntegration);
    
    /**
     * @param OptimizationIntegrationInterface $optimizationIntegration
     * @return mixed
     */
    public function optimizeForOptimizationIntegration(OptimizationIntegrationInterface $optimizationIntegration);

    /**
     * @param OptimizationIntegrationInterface $optimizationIntegration
     * @return mixed
     */
    public function testForOptimizationIntegration(OptimizationIntegrationInterface $optimizationIntegration);
}