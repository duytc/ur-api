<?php


namespace UR\Service;


use UR\Domain\DTO\Report\Transforms\AddCalculatedFieldTransform;
use UR\Domain\DTO\Report\Transforms\AddFieldTransform;
use UR\Domain\DTO\Report\Transforms\ComparisonPercentTransform;
use UR\Domain\DTO\Report\Transforms\NewFieldTransform;
use UR\Domain\DTO\Report\Transforms\ReplaceTextTransform;
use UR\Domain\DTO\Report\Transforms\TransformInterface;
use UR\Exception\InvalidArgumentException;

trait StringUtilTrait
{
    /**
     * Check if the given string is a valid domain
     *
     * @param $domain
     * @return bool
     */
    protected function validateDomain($domain)
    {
        return preg_match('/^(?:[-A-Za-z0-9]+\.)+[A-Za-z]{2,6}$/', $domain) > 0;
    }

    /**
     * @param $domain
     * @return mixed|string
     */
    protected function extractDomain($domain)
    {
        if (false !== stripos($domain, 'http')) {
            $domain = parse_url($domain, PHP_URL_HOST); // remove http part, get only domain
        }

        // remove the 'www' prefix
        if (0 === stripos($domain, 'www.')) {
            $domain = substr($domain, 4);
        }

        $slashPos = strpos($domain, '/');
        if (false !== $slashPos) {
            $domain = substr($domain, 0, $slashPos);
        }

        if (!$this->validateDomain($domain)) {
            throw new InvalidArgumentException(sprintf('The value "%s" is not a valid domain.', $domain));
        }

        return $domain;
    }

    public static function generateUuidV4() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            // 16 bits for "time_mid"
            mt_rand(0, 0xffff),
            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand(0, 0x0fff) | 0x4000,
            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand(0, 0x3fff) | 0x8000,
            // 48 bits for "node"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    /**
     * extract parent id form formatted string
     * @param $formattedResult
     * @return mixed
     * @throws \Exception
     */
    public function extractParentId($formattedResult)
    {
        // validate input format
        $tmp = explode(':', $formattedResult);
        if (!is_array($tmp) || count($tmp) < 2) {
            throw new \Exception('Not valid input format');
        }

        $parentId = $tmp[0];

        return $parentId;
    }


    protected function removeIdSuffix($column)
    {
        $idAndField = $this->getIdSuffixAndField($column);
        if ($idAndField) {
            return $idAndField[NewFieldTransform::FIELD_NAME_KEY];
        }

        return $column;
    }


    protected function convertExpressionForm($expression, $removeSuffix = false)
    {
        if (is_null($expression)) {
            throw new \Exception(sprintf('Expression for calculated field can not be null'));
        }

        $regex = '/\[(.*?)\]/';
        if (!preg_match_all($regex, $expression, $matches)) {
            return $expression;
        };

        $fieldsInBracket = $matches[0];
        $fields = $matches[1];
        $newExpressionForm = null;

        foreach ($fields as $index => $field) {
            if ($removeSuffix === true) {
                $field = $this->removeIdSuffix($field);
            }
            $expression = str_replace($fieldsInBracket[$index], $field, $expression);
        }

        return $expression;
    }

    protected function convertExpressionFormForJoin($expression, $dataSetIndex)
    {
        if (is_null($expression)) {
            throw new \Exception(sprintf('Expression for calculated field can not be null'));
        }

        $regex = '/\[(.*?)\]/';
        if (!preg_match_all($regex, $expression, $matches)) {
            return $expression;
        };

        $fieldsInBracket = $matches[0];
        $fields = $matches[1];
        $newExpressionForm = null;

        foreach ($fields as $index => $field) {
            $field = $this->removeIdSuffix($field);
            $field = sprintf('t%d.%s', $dataSetIndex, $field);

            $expression = str_replace($fieldsInBracket[$index], $field, $expression);
        }

        return $expression;
    }

    protected function getIdSuffixAndField($column)
    {
        if (preg_match('/^(.*)_([0-9]+)$/', $column, $matches)) {
            return array(
                NewFieldTransform::FIELD_NAME_KEY => $matches[1],
                'id' => $matches[2]
            );
        }

        return null;
    }

    public function getStandardName($name)
    {
        $name = strtolower(trim($name));

        $name = preg_replace("/ +/", "_", $name);
        $name = preg_replace("/-+/", "_", $name);
        $name = preg_replace("/[^a-zA-Z0-9]/ ", "_", $name);
        $name = preg_replace("/_+/ ", "_", $name);

        return $name;
    }

    public function getNewFieldsFromTransforms(array $transforms)
    {
        $newFields = [];
        foreach ($transforms as $transform) {
            if (!array_key_exists(TransformInterface::TRANSFORM_TYPE_KEY, $transform)) {
                continue;
            }
            $type = $transform[TransformInterface::TRANSFORM_TYPE_KEY];

            if (in_array($type, [AddCalculatedFieldTransform::TRANSFORMS_TYPE, AddFieldTransform::TRANSFORMS_TYPE, ComparisonPercentTransform::TRANSFORMS_TYPE])) {
                if (!array_key_exists(TransformInterface::FIELDS_TRANSFORM, $transform)) {
                    continue;
                }
                $fields = $transform[TransformInterface::FIELDS_TRANSFORM];

                foreach ($fields as $field) {
                    if (!array_key_exists(NewFieldTransform::FIELD_NAME_KEY, $field)) {
                        continue;
                    }
                    $newFields[] = $field[NewFieldTransform::FIELD_NAME_KEY];
                }
            }
            if (in_array($type, [ReplaceTextTransform::TRANSFORMS_TYPE])) {
                if (!array_key_exists(TransformInterface::FIELDS_TRANSFORM, $transform)) {
                    continue;
                }
                $fields = $transform[TransformInterface::FIELDS_TRANSFORM];

                foreach ($fields as $field) {
                    if (!array_key_exists(ReplaceTextTransform::IS_OVERRIDE_KEY, $field)) {
                        continue;
                    }
                    $isOverwrite = $field[ReplaceTextTransform::IS_OVERRIDE_KEY];

                    if (!$isOverwrite) {
                        if (!array_key_exists(ReplaceTextTransform::TARGET_FIELD_KEY, $field)) {
                            continue;
                        }
                        $targetField = $field[ReplaceTextTransform::TARGET_FIELD_KEY];
                        $newFields[] = $targetField;
                    }
                }
            }
        }

        return $newFields;
    }
}