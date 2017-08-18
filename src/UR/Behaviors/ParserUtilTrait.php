<?php


namespace UR\Behaviors;


use DateTime;
use DateTimeZone;
use UR\Service\Parser\Transformer\Column\DateFormat;

trait ParserUtilTrait
{
    /**
     * convert a string To ASCII Encoding
     * @param array $data
     * @return array
     */
    protected function convertEncodingToASCII(array $data)
    {
        foreach ($data as &$item) {
            // remove non-ascii characters
            $item = preg_replace('/[[:^print:]]/', '', $item);

            if (!mb_check_encoding($item, 'ASCII')) {
                $item = $this->convert_ascii($item);
            }
        }

        return $data;
    }

    /**
     * Remove any non-ASCII characters and convert known non-ASCII characters
     * to their ASCII equivalents, if possible.
     *
     * @param string $string
     * @return string $string
     * @author Jay Williams <myd3.com>
     * @license MIT License
     * @link http://gist.github.com/119517
     */
    protected function convert_ascii($string)
    {
        // Replace Single Curly Quotes
        $search[] = chr(226) . chr(128) . chr(152);
        $replace[] = "'";
        $search[] = chr(226) . chr(128) . chr(153);
        $replace[] = "'";
        // Replace Smart Double Curly Quotes
        $search[] = chr(226) . chr(128) . chr(156);
        $replace[] = '"';
        $search[] = chr(226) . chr(128) . chr(157);
        $replace[] = '"';
        // Replace En Dash
        $search[] = chr(226) . chr(128) . chr(147);
        $replace[] = '--';
        // Replace Em Dash
        $search[] = chr(226) . chr(128) . chr(148);
        $replace[] = '---';
        // Replace Bullet
        $search[] = chr(226) . chr(128) . chr(162);
        $replace[] = '*';
        // Replace Middle Dot
        $search[] = chr(194) . chr(183);
        $replace[] = '*';
        // Replace Ellipsis with three consecutive dots
        $search[] = chr(226) . chr(128) . chr(166);
        $replace[] = '...';
        // Apply Replacements
        $string = str_replace($search, $replace, $string);
        // Remove any non-ASCII Characters
        $string = preg_replace("/[^\x01-\x7F]/", "", $string);
        return $string;
    }

    /**
     * @param $value
     * @param array $formats
     * @return DateTime
     */
    public function getDate($value, $formats, $timezone)
    {
        $date = null;

        if (!is_array($formats) || empty($formats)) {
            return null;
        }

        /*
         * Parse date by Custom date formats and partial match
         * formats:
         * [
         *     [
         *          format: ..., // YYYY-MM-DD, ...
         *          isCustomDateFormat: true/false,
         *          isPartialMatch: true/false,
         *     ]
         * ]
         */
        foreach ($formats as $formatArray) {
            if (!is_array($formatArray) || !array_key_exists(DateFormat::FORMAT_KEY, $formatArray)) {
                $format = DateFormat::DEFAULT_DATE_FORMAT_FULL;
            } else {
                $format = $formatArray[DateFormat::FORMAT_KEY];
            }

            // support partial match value
            $isPartialMatch = array_key_exists(DateFormat::IS_CUSTOM_FORMAT_DATE_FROM_WITH_PARTIAL_MATCH, $formatArray) ? $formatArray[DateFormat::IS_CUSTOM_FORMAT_DATE_FROM_WITH_PARTIAL_MATCH] : false;
            if ($isPartialMatch) {
                $value = DateFormat::getPartialMatchValue($format, $value);
            }

            // support custom date format
            $isCustomDateFormat = array_key_exists(DateFormat::IS_CUSTOM_FORMAT_DATE_FROM, $formatArray) ? $formatArray[DateFormat::IS_CUSTOM_FORMAT_DATE_FROM] : false;
            if ($isCustomDateFormat) {
                $format = DateFormat::convertCustomFromDateFormatToPHPDateFormat($format);
            } else {
                $format = DateFormat::convertDateFormatFullToPHPDateFormat($format);
            }

            // handle the case: apply T for all text

            $date = DateTime::createFromFormat('!' . $format, $value, new DateTimeZone($timezone)); // auto set time (H,i,s) to 0 if not available
            if ($date instanceof DateTime) {
                break;
            }

            $date = date_create_from_format($format, $value);
            if ($date instanceof DateTime) {
                break;
            }
        }

        /** Parse date by system support formats */
        if (!$date instanceof DateTime) {
            $date = DateFormat::getDateFromText($value);
        }

        return $date;
    }
}