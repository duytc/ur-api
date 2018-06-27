<?php

namespace UR\Service\OptimizationRule\AutomatedOptimization\Pubvantage;

use UR\Model\Core\OptimizationIntegrationInterface;
use UR\Model\Core\OptimizationRuleInterface;
use UR\Service\DynamicTable\DynamicTableServiceInterface;
use UR\Service\OptimizationRule\AutomatedOptimization\OptimizerInterface;
use UR\Service\OptimizationRule\DataTrainingTableService;
use UR\Service\OptimizationRule\DataTrainingTableServiceInterface;
use UR\Service\OptimizationRule\OptimizationRuleScoreServiceInterface;
use UR\Util\TagcadeRestClient;

class PubvantageOptimizer implements OptimizerInterface
{
    const PLATFORM_INTEGRATION = 'pubvantage';
    const MAPPING_AD_TAG_ID = 'adTagId';
    const MAPPING_AD_TAG_NAME = 'adTagName';

    const OPTIMIZATION_RULE_ID_KEY = 'optimizationRuleId';
    const TOKEN_KEY = 'token';
    const IDENTIFIERS_KEY = 'identifiers';
    const CONDITIONS_KEY = 'conditions';
    const IS_GLOBAL_KEY = 'isGlobal';
    const SEGMENT_FIELDS_KEY = self::REFRESH_CACHE_SEGMENT_FIELDS_KEY;
    const SEGMENT_FIELD_KEY = 'segmentField';
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
    const REFRESH_CACHE_AD_SLOTS_KEY = 'adSlots';
    const REFRESH_CACHE_MAPPED_BY_KEY = 'mappedBy';
    const REFRESH_CACHE_SEGMENT_FIELDS_KEY = 'segmentFields';
    const REFRESH_CACHE_AD_TAG_SCORES_KEY = 'adTagScores';
    const REFRESH_CACHE_IDENTIFIER_KEY = 'identifier';
    const REFRESH_CACHE_SCORE_KEY = 'score';
    const REFRESH_CACHE_OVERALL_SCORE_KEY = 'overallScore';

    //config for value of segments
    const SEGMENT_AD_TAG_SCORES = 'adTagScores';
    const SEGMENT_AD_TAG_SCORES_IDENTIFIER = 'identifier';
    const GLOBAL = 'global';

    /** @var TagcadeRestClient */
    private $restClient;

    /** @var DataTrainingTableServiceInterface */
    private $dataTrainingTableService;

    /** @var DynamicTableServiceInterface */
    private $dynamicTableService;

    /** @var OptimizationRuleScoreServiceInterface */
    private $optimizationRuleScoreService;

    /**
     * PubvantageOptimizer constructor.
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
        return $optimizationIntegration->getPlatformIntegration() == PubvantageOptimizer::PLATFORM_INTEGRATION;
    }

    /**
     * @inheritdoc
     */
    public function optimizeForOptimizationIntegration(OptimizationIntegrationInterface $optimizationIntegration)
    {
        $mappedBy = $optimizationIntegration->getIdentifierMapping();
        $mappedAdSlots = $optimizationIntegration->getAdSlots();
        $scoresOfRefreshData[self::REFRESH_CACHE_AD_SLOTS_KEY] = $mappedAdSlots;
        $scoresOfRefreshData[self::REFRESH_CACHE_MAPPED_BY_KEY] = $mappedBy;

        // ignore update if no identifier or adSlot map config...
        if (!is_array($mappedAdSlots) || empty($mappedBy) || empty($mappedAdSlots)) {
            return [
                'message' => 'either mappedBy or mappedAdSlots is empty. Skip update cache for ad slots.'
            ];
        }

        if ($optimizationIntegration->getActive() == OptimizationIntegrationInterface::ACTIVE_APPLY) {
            // get scores from data base
            $scoresFromScorers = $this->getScoresFromDatabase($optimizationIntegration);

            // remove duplicated scores
            $scoresFromScorers = $this->removeDuplicatedScores($scoresFromScorers);

            $scoresOfRefreshData[self::REFRESH_CACHE_SCORES_KEY] = $scoresFromScorers;
        } else {
            $scoresOfRefreshData[self::REFRESH_CACHE_SCORES_KEY] = [];
        }

        // ignore update if no score
        if (!is_array($scoresOfRefreshData[self::REFRESH_CACHE_SCORES_KEY]) || empty($scoresOfRefreshData[self::REFRESH_CACHE_SCORES_KEY])) {
            return [
                'message' => 'Score is empty. Skip update cache for ad slots.'
            ];
        }

        return $this->restClient->updateCacheForAdSlots($scoresOfRefreshData);
    }

    /**
     * @inheritdoc
     */
    public function testForOptimizationIntegration(OptimizationIntegrationInterface $optimizationIntegration)
    {
        $mappedBy = $optimizationIntegration->getIdentifierMapping();
        $mappedAdSlots = $optimizationIntegration->getAdSlots();
        $scoresOfRefreshData[self::REFRESH_CACHE_AD_SLOTS_KEY] = $mappedAdSlots;
        $scoresOfRefreshData[self::REFRESH_CACHE_MAPPED_BY_KEY] = $mappedBy;

        $scoresFromScorers = $this->getTestScoresFromDatabase($optimizationIntegration);
        $scoresOfRefreshData[self::REFRESH_CACHE_SCORES_KEY] = $scoresFromScorers;

        return ['positions' => $this->restClient->testCacheForAdSlots($scoresOfRefreshData)];
    }

    /**
     * @param OptimizationIntegrationInterface $optimizationIntegration
     * @return array
     */
    private function getScoresFromDatabase(OptimizationIntegrationInterface $optimizationIntegration)
    {
        $optimizationRule = $optimizationIntegration->getOptimizationRule();
        $segments = $this->createSegmentWithFullNeededValue($optimizationIntegration);

        $scores = [];

        // always get default with no segments
        $tcSegmentFieldValues = [];
        $urSegmentFieldValues = [];

        $this->completeGetScore($optimizationRule, $scores, $tcSegmentFieldValues, $urSegmentFieldValues);

        // we return default immediately if no segments here
        if (empty($segments)) {
            return $scores;
        }

        foreach ($segments as $segment) {
            $this->getScoreByRecursive($optimizationRule, $scores, [$segment], [], []);
        }

        // if have segments, continue getting score for segments
        $this->getScoreByRecursive($optimizationRule, $scores, $segments, [], []);

        // get default score for each segment, to do here to avoid recursive
        $tcSegmentFieldValues = [];
        $urSegmentFieldValues = [];
        foreach ($segments as $segment) {
            $tcSegmentFieldValues[$segment[self::TO_FACTOR_KEY]] = '';
            $urSegmentFieldValues[$segment[self::DIMENSION_FIELD_KEY]] = '';
        }

        $this->completeGetScore($optimizationRule, $scores, $tcSegmentFieldValues, $urSegmentFieldValues);

        foreach ($segments as $segment) {
            $tcSegmentFieldValues = [];
            $urSegmentFieldValues = [];
            $tcSegmentFieldValues[$segment[self::TO_FACTOR_KEY]] = '';
            $urSegmentFieldValues[$segment[self::DIMENSION_FIELD_KEY]] = '';
            $this->completeGetScore($optimizationRule, $scores, $tcSegmentFieldValues, $urSegmentFieldValues);
        }

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
        $data[PubvantageOptimizer::REFRESH_CACHE_SEGMENT_FIELDS_KEY] = $segmentFieldValues;
        if (!array_key_exists('rows', $localScores)) {
            return null;
        }

        $rows = $localScores['rows'];
        $rows = is_array($rows) ? $rows : [$rows];
        $identifiersMap = [];

        foreach ($rows as $row) {
            if (!array_key_exists(PubvantageOptimizer::REFRESH_CACHE_IDENTIFIER_KEY, $row) && !array_key_exists(PubvantageOptimizer::REFRESH_CACHE_SCORE_KEY, $row)) {
                continue;
            }

            $identifiersMap[$row[PubvantageOptimizer::REFRESH_CACHE_IDENTIFIER_KEY]] = [
                PubvantageOptimizer::REFRESH_CACHE_IDENTIFIER_KEY => $row[PubvantageOptimizer::REFRESH_CACHE_IDENTIFIER_KEY],
                PubvantageOptimizer::REFRESH_CACHE_SCORE_KEY => $row[PubvantageOptimizer::REFRESH_CACHE_SCORE_KEY]
            ];
        }

        $data[PubvantageOptimizer::REFRESH_CACHE_AD_TAG_SCORES_KEY] = array_values($identifiersMap);

        return $data;
    }

    /**
     * @param OptimizationIntegrationInterface $optimizationIntegration
     * @return mixed|null
     */
    private function createSegmentWithFullNeededValue(OptimizationIntegrationInterface $optimizationIntegration)
    {
        $segments = $optimizationIntegration->getSegments();
        $segments = is_array($segments) ? $segments : [$segments];
        $optimizationRule = $optimizationIntegration->getOptimizationRule();

        if (empty($segments) || !$optimizationRule instanceof OptimizationRuleInterface) {
            return [];
        }

        $tableName = $this->dataTrainingTableService->getDataTrainingTableName($optimizationRule);
        foreach ($segments as $key => $segment) {
            if (!is_array($segment) || !array_key_exists(PubvantageOptimizer::DIMENSION_FIELD_KEY, $segment) || !array_key_exists(PubvantageOptimizer::TO_FACTOR_KEY, $segment) || !array_key_exists(PubvantageOptimizer::NEEDED_VALUE_KEY, $segment)) {
                continue;
            }
            $neededValue = $segment[PubvantageOptimizer::NEEDED_VALUE_KEY];
            if (empty($neededValue)) {
                //Select all
                $neededValue = empty($countryMapField) ? [] : $this->dynamicTableService->selectDistinctOneColumns($tableName, $segment[PubvantageOptimizer::TO_FACTOR_KEY], $whereClause = '');
            }

            if (empty($neededValue)) {
                unset($segments[$key]);
            }
        }

        return $segments;
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

        $neededValue = $currentSegment[PubvantageOptimizer::NEEDED_VALUE_KEY];
        foreach ($neededValue as $value) {
            $tcProcessSegments[$currentSegment[PubvantageOptimizer::DIMENSION_FIELD_KEY]] = $value;
            $urProcessedSegments[$currentSegment[PubvantageOptimizer::TO_FACTOR_KEY]] = $value;
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

    /**
     * @param array $scoresFromScorers
     * @return array
     */
    private function removeDuplicatedScores(array $scoresFromScorers)
    {
        /* validate */
        if (empty($scoresFromScorers)) {
            return $scoresFromScorers;
        }

        // remove item the same segment value
        /*
         *
         * [0] => [
         *      [segmentFields] => [
         *               [country] => United Kingdom
         *       ],
         *       [adTagScores] => [...]
         *   ],
         *   [1] => [
         *       [segmentFields] => [
         *               [country] => United Kingdom
         *        ],
         *       [adTagScores] => [...]
         *   ]

         * => remove [1]
         */
        $scoresFromScorers = $this->scanToMakeDataDistinct($scoresFromScorers);

        // remove item the same AdTagScore with global
        /*
         *
         * [0] => [
         *      [segmentFields] => [
         *          [country] => ''  // this one is global value
         *      ],
         *      [adTagScores] => [1, 0.8, 0.6, 0.3]
         *   ],
         * [1] => [
         *       [segmentFields] => [
         *           [country] => United Kingdom
         *       ],
         *       [adTagScores] => [1, 0.8, 0.6, 0.3]
         *   ]

         * => remove [1]
         */
        $scoresFromScorers = $this->removeItemDuplicateWithTheSameScoreForEachSegmentValue($scoresFromScorers);

        return $scoresFromScorers;
    }

    /**
     * @param $scoresFromScorers
     * @return mixed
     */
    private function scanToMakeDataDistinct($scoresFromScorers)
    {
        $arrayToCompare = [];
        // make data distinct
        foreach ($scoresFromScorers as $keyScore => $scoresFromScorer) {
            if (!is_array($scoresFromScorer) && !array_key_exists(self::REFRESH_CACHE_SEGMENT_FIELDS_KEY, $scoresFromScorer)) {
                continue;
            }
            $segmentFields = $scoresFromScorer[self::REFRESH_CACHE_SEGMENT_FIELDS_KEY];
            $hash = $this->calculateHash($segmentFields);

            if (in_array($hash, $arrayToCompare)) {
                unset($scoresFromScorers[$keyScore]);
            } else {
                $arrayToCompare[] = $hash;
            }
        }
        unset($scoresFromScorer, $arrayToCompare);

        return array_values($scoresFromScorers);
    }

    /**
     * @param $scoresFromScorers
     * @return array
     */
    private function removeItemDuplicateWithTheSameScoreForEachSegmentValue($scoresFromScorers)
    {
        $scoresFromScorers = array_filter($scoresFromScorers, function ($score) {
            return array_key_exists(self::REFRESH_CACHE_SEGMENT_FIELDS_KEY, $score);
        });

        // sort adTagScore by score desc
        foreach ($scoresFromScorers as &$scoresFromScorer) {
            $adTagScores = $scoresFromScorer[self::REFRESH_CACHE_AD_TAG_SCORES_KEY];
            usort($adTagScores, function ($a, $b) {
                $scoreA = floatval($a[self::REFRESH_CACHE_SCORE_KEY]);
                $scoreB = floatval($b[self::REFRESH_CACHE_SCORE_KEY]);
                if ($scoreA == $scoreB) {
                    return $a[self::SEGMENT_AD_TAG_SCORES_IDENTIFIER] < $b[self::SEGMENT_AD_TAG_SCORES_IDENTIFIER];
                }
                return ($scoreA < $scoreB) ? 1 : -1;
            });
            $scoresFromScorer[self::REFRESH_CACHE_AD_TAG_SCORES_KEY] = $adTagScores;
        }

        //Groups as: global, country, domain, country.domain
        $groups = [];
        foreach ($scoresFromScorers as $score) {
            $segmentFieldKeys = $score[self::REFRESH_CACHE_SEGMENT_FIELDS_KEY];
            $group = implode(".", array_keys($segmentFieldKeys));
            if (empty($group) || $group == '.') {
                $group = self::GLOBAL;
            }
            $segmentValues = array_values($segmentFieldKeys);
            $segmentValues = array_filter($segmentValues);
            if (empty($segmentValues)) {
                $segmentValues = self::GLOBAL;
            } else {
                $names = array_merge(array_keys($segmentFieldKeys), array_values($segmentFieldKeys));
                $segmentValues = implode(".", $names);
            }
            $groups[$group][$segmentValues] = $score;
        }

        $allScores = [];
        foreach ($groups as $group => $scores) {
            if (count($scores) < 2) {
                // group: global - only run once
                $allScores = array_merge($allScores, array_values($scores));
                continue;
            }

            // do with groups: country, domain, country.domain
            // filter to make sure all items (will be scanned) have to adTagsScores
            $scores = array_filter($scores, function ($score) {
                return array_key_exists(self::SEGMENT_AD_TAG_SCORES, $score);
            });

            $global = $scores[self::GLOBAL];
            $globalSerializer = serialize($global[self::SEGMENT_AD_TAG_SCORES]);
            foreach ($scores as $index => $score) {
                if ($index == self::GLOBAL) {
                    continue;
                }
                $adTagScores = $score[self::SEGMENT_AD_TAG_SCORES];
                $itemSerializer = serialize($adTagScores);
                // remove item score when duplicate with global score
                if ($globalSerializer == $itemSerializer) {
                    //Found duplicate, ignore
                    unset($scores[$index]);
                }
            }
            $allScores = array_merge($allScores, array_values($scores));
        }

        return array_values($allScores);
    }

    /**
     * @param array $arrayInput
     * @return string
     */
    private function calculateHash(array $arrayInput)
    {
        // TODO: find best algorithm for this hash function!
        $output = implode(', ', array_map(
            function ($v, $k) {
                return sprintf("%s='%s'", $k, $v);
            },
            $arrayInput,
            array_keys($arrayInput)
        ));

        return hash('sha256',$output);
    }
}