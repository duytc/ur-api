<?php


namespace UR\Service\OptimizationRule\AutomatedOptimization\PubvantageVideo;


use UR\Model\Core\OptimizationIntegrationInterface;
use UR\Model\Core\OptimizationRuleInterface;
use UR\Service\DynamicTable\DynamicTableServiceInterface;
use UR\Service\OptimizationRule\AutomatedOptimization\OptimizerInterface;
use UR\Service\OptimizationRule\DataTrainingTableService;
use UR\Service\OptimizationRule\DataTrainingTableServiceInterface;
use UR\Service\OptimizationRule\OptimizationRuleScoreServiceInterface;
use UR\Util\TagcadeRestClient;

class PubvantageVideoOptimizer implements OptimizerInterface
{
    const PLATFORM_INTEGRATION = 'pubvantage-video';
    const MAPPING_DEMAND_AD_TAG_ID = 'demandAdTagId';
    const MAPPING_DEMAND_AD_TAG_NAME = 'demandAdTagName';

    const OPTIMIZATION_RULE_ID_KEY = 'optimizationRuleId';
    const TOKEN_KEY = 'token';
    const IDENTIFIERS_KEY = 'identifiers';
    const CONDITIONS_KEY = 'conditions';
    const IS_GLOBAL_KEY = 'isGlobal';
    const VALUES_KEY = 'values';
    const IS_ALL_KEY = 'isAll';
    const FACTOR_VALUES_KEY = 'factorValues';
    const IS_PREDICTIVE_KEY = 'isPredictive';

    //config for OptimizationIntegration
    const DIMENSION_FIELD_KEY = 'dimension';
    const TO_FACTOR_KEY = 'toFactor';
    const NEEDED_VALUE_KEY = 'neededValue';

    //config for normalize scores
    const INFO_KEY = 'info';
    const MAPPED_BY_KEY = self::REFRESH_CACHE_MAPPED_BY_KEY;
    const SCORES_KEY = self::REFRESH_CACHE_SCORES_KEY;
    const REFRESH_CACHE_SCORES_KEY = 'scores';
    const REFRESH_CACHE_WATERFALL_TAGS_KEY = 'waterfallTags';
    const REFRESH_CACHE_MAPPED_BY_KEY = 'mappedBy';
    const REFRESH_CACHE_SEGMENT_FIELDS_KEY = 'segmentFields';
    const REFRESH_CACHE_DEMAND_TAG_SCORES_KEY = 'demandTagScores';
    const REFRESH_CACHE_IDENTIFIER_KEY = 'identifier';
    const REFRESH_CACHE_SCORE_KEY = 'score';
    const REFRESH_CACHE_OVERALL_SCORE_KEY = 'overallScore';

    /** @var TagcadeRestClient */
    private $restClient;

    /** @var DataTrainingTableServiceInterface */
    private $dataTrainingTableService;

    /** @var DynamicTableServiceInterface */
    private $dynamicTableService;

    /** @var OptimizationRuleScoreServiceInterface */
    private $optimizationRuleScoreService;

    /**
     * PubvantageVideoOptimizer constructor.
     * @param TagcadeRestClient $restClient
     * @param DataTrainingTableService $dataTrainingTableService
     * @param DynamicTableServiceInterface $dynamicTableService
     * @param OptimizationRuleScoreServiceInterface $optimizationRuleScoreService
     */
    public function __construct(TagcadeRestClient $restClient, DataTrainingTableService $dataTrainingTableService, DynamicTableServiceInterface $dynamicTableService, OptimizationRuleScoreServiceInterface $optimizationRuleScoreService)
    {
        $this->restClient = $restClient;
        $this->dataTrainingTableService = $dataTrainingTableService;
        $this->dynamicTableService = $dynamicTableService;
        $this->optimizationRuleScoreService = $optimizationRuleScoreService;
    }

    /**
     * @inheritdoc
     */
    public function supportOptimizationIntegration(OptimizationIntegrationInterface $optimizationIntegration)
    {
        return $optimizationIntegration->getPlatformIntegration() == self::PLATFORM_INTEGRATION;
    }

    /**
     * @inheritdoc
     */
    public function optimizeForOptimizationIntegration(OptimizationIntegrationInterface $optimizationIntegration)
    {
        $mappedBy = $optimizationIntegration->getIdentifierMapping();
        $mappedWaterFallTags = $optimizationIntegration->getWaterfallTags();
        $scoresOfRefreshData[self::REFRESH_CACHE_WATERFALL_TAGS_KEY] = $mappedWaterFallTags;
        $scoresOfRefreshData[self::REFRESH_CACHE_MAPPED_BY_KEY] = $mappedBy;

        // ignore update if no identifier or waterfall map config...
        if (!is_array($mappedWaterFallTags) || empty($mappedBy) || empty($mappedWaterFallTags)) {
            return [
                'message' => 'either mappedBy or mappedWaterfallTags is empty. Skip update cache for waterfall Tags.'
            ];
        }

        if ($optimizationIntegration->getActive() == OptimizationIntegrationInterface::ACTIVE_APPLY) {
            // get scores from data base
            $scoresFromScorers = $this->getScoresFromDatabase($optimizationIntegration);
            $scoresOfRefreshData[self::REFRESH_CACHE_SCORES_KEY] = $scoresFromScorers;
        } else {
            $scoresOfRefreshData[self::REFRESH_CACHE_SCORES_KEY] = [];
        }

        // ignore update if no score
        if (!is_array($scoresOfRefreshData[self::REFRESH_CACHE_SCORES_KEY]) || empty($scoresOfRefreshData[self::REFRESH_CACHE_SCORES_KEY])) {
            return [
                'message' => 'Score is empty. Skip update cache for waterfall.'
            ];
        }
        return $this->restClient->updateCacheForWaterFallTags($scoresOfRefreshData, self::PLATFORM_INTEGRATION);
    }

    /**
     * @inheritdoc
     */
    public function testForOptimizationIntegration(OptimizationIntegrationInterface $optimizationIntegration)
    {
        $mappedBy = $optimizationIntegration->getIdentifierMapping();
        $mappedVideoWaterfallTags = $optimizationIntegration->getWaterfallTags();
        $scoresOfRefreshData[self::REFRESH_CACHE_WATERFALL_TAGS_KEY] = $mappedVideoWaterfallTags;
        $scoresOfRefreshData[self::REFRESH_CACHE_MAPPED_BY_KEY] = $mappedBy;

        $scoresFromScorers = $this->getTestScoresFromDatabase($optimizationIntegration);
        $scoresOfRefreshData[self::REFRESH_CACHE_SCORES_KEY] = $scoresFromScorers;

        return ['positions' => $this->restClient->testCacheForWaterFallTags($scoresOfRefreshData)];
    }

    /**
     * @param OptimizationIntegrationInterface $optimizationIntegration
     * @return array
     */
    private function getScoresFromDatabase(OptimizationIntegrationInterface $optimizationIntegration)
    {
        $optimizationRule = $optimizationIntegration->getOptimizationRule();

        $scores = [];

        // always get default with no segments
        $tcSegmentFieldValues = [];
        $urSegmentFieldValues = [];

        $this->completeGetScore($optimizationRule, $scores, $tcSegmentFieldValues, $urSegmentFieldValues);

        // we return default immediately if no segments here
        return $scores;
    }

    /**
     * @param OptimizationIntegrationInterface $optimizationIntegration
     * @return array
     */
    private function getTestScoresFromDatabase(OptimizationIntegrationInterface $optimizationIntegration)
    {
        $scores = [];
        // Get default with no segments
        $this->completeGetScore($optimizationIntegration->getOptimizationRule(), $scores, [], []);

        return $scores;
    }

    /**
     * @param $localScores
     * @param $segmentFieldValues
     * @return mixed
     */
    private function normalizeScore($localScores, $segmentFieldValues)
    {
        $data[self::REFRESH_CACHE_SEGMENT_FIELDS_KEY] = $segmentFieldValues;
        if (!array_key_exists('rows', $localScores)) {
            return null;
        }

        $rows = $localScores['rows'];
        $rows = is_array($rows) ? $rows : [$rows];
        $identifiersMap = [];

        foreach ($rows as $row) {
            if (!array_key_exists(self::REFRESH_CACHE_IDENTIFIER_KEY, $row) && !array_key_exists(self::REFRESH_CACHE_SCORE_KEY, $row)) {
                continue;
            }

            $identifiersMap[$row[self::REFRESH_CACHE_IDENTIFIER_KEY]] = [
                self::REFRESH_CACHE_IDENTIFIER_KEY => $row[self::REFRESH_CACHE_IDENTIFIER_KEY],
                self::REFRESH_CACHE_SCORE_KEY => $row[self::REFRESH_CACHE_SCORE_KEY]
            ];
        }

        $data[self::REFRESH_CACHE_DEMAND_TAG_SCORES_KEY] = array_values($identifiersMap);

        return $data;
    }

    /**
     * @param OptimizationRuleInterface $optimizationRule
     * @param $scores
     * @param $remainSegments
     * @param $urProcessedSegments
     * @param $tcProcessSegments
     */
    private function getScoreByRecursive(OptimizationRuleInterface $optimizationRule, &$scores, $remainSegments, $urProcessedSegments, $tcProcessSegments)
    {
        if (empty($remainSegments)) {
            $this->completeGetScore($optimizationRule, $scores, $urProcessedSegments, $tcProcessSegments);

            return;
        }

        $currentSegment = reset($remainSegments);
        array_splice($remainSegments, 0, 1);

        $neededValue = $currentSegment[self::NEEDED_VALUE_KEY];
        foreach ($neededValue as $value) {
            $tcProcessSegments[$currentSegment[self::DIMENSION_FIELD_KEY]] = $value;
            $urProcessedSegments[$currentSegment[self::TO_FACTOR_KEY]] = $value;
            $this->completeGetScore($optimizationRule, $scores, $urProcessedSegments, $tcProcessSegments);
            $this->getScoreByRecursive($optimizationRule, $scores, $remainSegments, $urProcessedSegments, $tcProcessSegments);
        }
    }

    /**
     * @param OptimizationRuleInterface $optimizationRule
     * @param $scores
     * @param $tcSegmentFieldValues
     * @param $urSegmentFieldValues
     */
    private function completeGetScore(OptimizationRuleInterface $optimizationRule, &$scores, $tcSegmentFieldValues, $urSegmentFieldValues)
    {
        $tomorrow = date_create('tomorrow');
        $localScores = $this->optimizationRuleScoreService->getScoresByDateRange($optimizationRule, $tcSegmentFieldValues, $tomorrow, $tomorrow);
        $data = $this->normalizeScore($localScores, $urSegmentFieldValues);

        if (!empty($data)) {
            $scores[] = $data;
        }
    }
}
