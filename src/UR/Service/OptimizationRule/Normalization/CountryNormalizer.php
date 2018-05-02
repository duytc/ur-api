<?php

namespace UR\Service\OptimizationRule\Normalization;

use Rinvex\Country\CountryLoader;

class CountryNormalizer implements NormalizerInterface
{
    /** @var mixed */
    private $countries;


    /**
     * @inheritdoc
     */
    public function isSupport($rows, $segment)
    {
        if (empty($rows)) {
            return false;
        }
        if (empty($this->countries)) {
            $this->initCountryInfo();
        }
        $i = 0;  // count segment available
        foreach ($rows as $row) {
            if (!array_key_exists($segment, $row)) {
                continue;
            }
            $text = mb_strtolower($row[$segment], 'UTF-8');
            if (array_key_exists($text, $this->countries)) {
                $i++;
            }
        }
        $rate = (float)($i / count($rows));
        return $rate > NormalizerInterface::NUMBER_NORMALIZER_SUPPORT_VALUE;
    }

    /**
     * @inheritdoc
     */
    public function normalizeText($text)
    {
        if (empty($text)) {
            return $text;
        }

        if (empty($this->countries)) {
            $this->initCountryInfo();
        }

        $text = mb_strtolower($text, 'UTF-8');

        if (array_key_exists($text, $this->countries)) {
            return $this->countries[$text];
        }

        return $text;
    }

    /**
     *
     */
    private function initCountryInfo()
    {
        $allCCountries = CountryLoader::countries(true);
        $allCCountries = is_array($allCCountries) ? $allCCountries : [$allCCountries];

        foreach ($allCCountries as $countryName => $country) {
            if (array_key_exists('name', $country) && array_key_exists('common', $country['name'])) {
                $countryName = $country['name']['common'];
            } else {
                $countryName = strtoupper($countryName);
            }
            $this->addCountryName($countryName, $country);
            $this->addAltSpelling($countryName, $country);
            $this->addTranslations($countryName, $country);
            $this->add2digit3116($countryName, $country);
            $this->add3digit3116($countryName, $country);

            $this->countries[$countryName] = array_unique($this->countries[$countryName]);
            $this->countries[$countryName] = array_map(function ($text) {
                return mb_strtolower($text, 'UTF-8');
            }, $this->countries[$countryName]);
        }

        /// change map countries array
        $mapCountry = array();
        foreach ($this->countries as $countryName => $info) {
            if (!is_array($info)) {
                continue;
            }
            foreach ($info as $item) {
                $mapCountry[$item] = $countryName;
            }
        }
        $this->countries = $mapCountry;
    }

    /**
     * @param $countryName
     * @param $country
     */
    private function addCountryName($countryName, $country)
    {
        if (!is_array($country) || !array_key_exists("name", $country)) {
            return;
        }

        $name = $country["name"];
        if (!is_array($name)) {
            return;
        }

        if (array_key_exists('common', $name)) {
            $this->countries[$countryName][] = $name['common'];
        }

        if (array_key_exists('official', $name)) {
            $this->countries[$countryName][] = $name['official'];
        }

        $native = $name['native'];

        if (!is_array($native)) {
            return;
        }

        foreach ($native as $item) {
            if (!is_array($item)) {
                continue;
            }

            $this->countries[$countryName] = array_merge($this->countries[$countryName], array_values($item));
        }
    }

    /**
     * @param $countryName
     * @param $country
     */
    private function addAltSpelling($countryName, $country)
    {
        if (is_array($country) && array_key_exists("alt_spellings", $country)) {
            $alternatives = $country["alt_spellings"];
            if (!is_array($alternatives)) {
                return;
            }

            $this->countries[$countryName] = array_merge($this->countries[$countryName], array_values($alternatives));
        }
    }

    /**
     * @param $countryName
     * @param $country
     */
    private function addTranslations($countryName, $country)
    {
        if (is_array($country) && array_key_exists("translations", $country)) {
            $alternatives = $country["translations"];
            if (!is_array($alternatives)) {
                return;
            }

            foreach ($alternatives as $alternative) {
                if (!is_array($alternative)) {
                    continue;
                }
                $this->countries[$countryName] = array_merge($this->countries[$countryName], array_values($alternative));
            }
        }
    }

    /**
     * @param $countryName
     * @param $country
     */
    private function add2digit3116($countryName, $country)
    {
        if (is_array($country) && array_key_exists("iso_3166_1_alpha2", $country)) {
            $this->countries[$countryName][] = $country["iso_3166_1_alpha2"];
        }
    }

    /**
     * @param $countryName
     * @param $country
     */
    private function add3digit3116($countryName, $country)
    {
        if (is_array($country) && array_key_exists("iso_3166_1_alpha3", $country)) {
            $this->countries[$countryName][] = $country["iso_3166_1_alpha3"];
        }
    }
}