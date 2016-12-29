<?php


namespace UR\Bundle\AdminApiBundle\Utils;

trait ModuleNameMapper
{
    /**
     * @param array $modules
     * @return array
     */
    protected function mapModuleName(array $modules)
    {
        return array_map(function (&$key) {
            if ($key === 'MODULE_DISPLAY') return 'displayAds';
            elseif ($key === 'MODULE_ANALYTICS') return 'analytics';
            elseif ($key === 'MODULE_VIDEO_ANALYTICS') return 'videoAnalytics';
            elseif ($key === 'MODULE_VIDEO') return 'video';
            elseif ($key === 'MODULE_FRAUD_DETECTION') return 'fraudDetection';
            elseif ($key === 'MODULE_UNIFIED_REPORT') return 'unifiedReport';
            elseif ($key === 'MODULE_HEADER_BIDDING') return 'headerBidding';
            elseif ($key === 'MODULE_RTB') return 'realTimeBidding';
            else return $key;
        }, $modules);
    }

    /**
     * @param array $moduleConfigs
     * @return array
     */
    protected function mapModuleConfig(array $moduleConfigs)
    {
        foreach ($moduleConfigs as $key => $value) {
            switch ($key) {
                case 'MODULE_DISPLAY' :
                    $moduleConfigs['displayAds'] = $moduleConfigs['MODULE_DISPLAY'];
                    unset($moduleConfigs['MODULE_DISPLAY']);
                    break;
                case 'MODULE_ANALYTICS':
                    $moduleConfigs['analytics'] = $moduleConfigs['MODULE_ANALYTICS'];
                    unset($moduleConfigs['MODULE_ANALYTICS']);
                    break;
                case 'MODULE_VIDEO_ANALYTICS':
                    $moduleConfigs['videoAnalytics'] = $moduleConfigs['MODULE_VIDEO_ANALYTICS'];
                    unset($moduleConfigs['MODULE_VIDEO_ANALYTICS']);
                    break;
                case 'MODULE_VIDEO':
                    $moduleConfigs['video'] = $moduleConfigs['MODULE_VIDEO'];
                    unset($moduleConfigs['MODULE_VIDEO']);
                    break;
                case 'MODULE_FRAUD_DETECTION':
                    $moduleConfigs['fraudDetection'] = $moduleConfigs['MODULE_FRAUD_DETECTION'];
                    unset($moduleConfigs['MODULE_FRAUD_DETECTION']);
                    break;
                case 'MODULE_UNIFIED_REPORT':
                    $moduleConfigs['unifiedReport'] = $moduleConfigs['MODULE_UNIFIED_REPORT'];
                    unset($moduleConfigs['MODULE_UNIFIED_REPORT']);
                    break;
                case 'MODULE_RTB':
                    $moduleConfigs['realTimeBidding'] = $moduleConfigs['MODULE_RTB'];
                    unset($moduleConfigs['MODULE_RTB']);
                    break;
                case 'MODULE_HEADER_BIDDING':
                    $moduleConfigs['headerBidding'] = $moduleConfigs['MODULE_HEADER_BIDDING'];
                    unset($moduleConfigs['MODULE_HEADER_BIDDING']);
                    break;
            }
        }

        return $moduleConfigs;
    }
}