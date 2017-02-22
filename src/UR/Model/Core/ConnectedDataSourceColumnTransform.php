<?php

namespace UR\Model\Core;


use UR\Service\DataSet\TransformType;
use UR\Service\Parser\Transformer\Column\DateFormat;
use UR\Service\Parser\Transformer\Column\NumberFormat;

class ConnectedDataSourceColumnTransform
{
    /**
     * @var DateFormat[] $dateFormatTransforms
     */
    protected $dateFormatTransforms = [];

    /**
     * @var NumberFormat[] $numberFormatTransforms
     */
    protected $numberFormatTransforms = [];

    /**
     * ConnectedDataSourceColumnTransform constructor.
     * @param array $configs
     */
    public function __construct($configs)
    {
        if (!is_array($configs))
            $configs = [];
        foreach ($configs as $config) {
            if (!array_key_exists(TransformType::FIELD, $config)
                || !array_key_exists(TransformType::TYPE, $config)
            ) {
                continue;
            }

            $type = $config[TransformType::TYPE];
            switch ($type) {
                case TransformType::DATE:
                    $this->setDateFormatTransforms($config);
                    break;
                case TransformType::NUMBER:
                    $this->setNumberFormatTransforms($config);
                    break;
            }
        }
    }

    /**
     * @return DateFormat[]|null
     */
    public function getDateFormatTransforms()
    {
        return $this->dateFormatTransforms;
    }

    /**
     * @param array $dateFormatConfig
     */
    public function setDateFormatTransforms($dateFormatConfig)
    {
        if (!is_array($dateFormatConfig)
            || !array_key_exists(TransformType::FROM, $dateFormatConfig)
            || !array_key_exists(TransformType::TO, $dateFormatConfig)
        ) {
            return;
        }

        $this->dateFormatTransforms[] = new DateFormat(
            $dateFormatConfig[TransformType::FIELD],
            $dateFormatConfig[TransformType::FROM],
            $dateFormatConfig[TransformType::TO],
            !array_key_exists(TransformType::IS_CUSTOM_FORMAT_DATE_FROM, $dateFormatConfig) ? false : $dateFormatConfig[TransformType::IS_CUSTOM_FORMAT_DATE_FROM]
        );
    }

    /**
     * @return NumberFormat[]|null
     */
    public function getNumberFormatTransforms()
    {
        return $this->numberFormatTransforms;
    }

    /**
     * @param array $numberFormatConfig
     */
    public function setNumberFormatTransforms($numberFormatConfig)
    {
        if (!is_array($numberFormatConfig)
            || !array_key_exists(TransformType::DECIMALS, $numberFormatConfig)
            || !array_key_exists(TransformType::THOUSANDS_SEPARATOR, $numberFormatConfig)
        ) {
            return;
        }

        $this->numberFormatTransforms[] = new NumberFormat(
            $numberFormatConfig[TransformType::FIELD],
            $numberFormatConfig[TransformType::DECIMALS],
            $numberFormatConfig[TransformType::THOUSANDS_SEPARATOR]
        );
    }
}