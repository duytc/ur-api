<?php


namespace UR\Service\DataSource\CSV;

use php_user_filter;

class FilterReplace extends php_user_filter
{
    const FILTER_NAME = 'convert.replace.';
    const SEARCH_VALUE = '\\""';
    const REPLACE_VALUE = '""';
    private $search;
    private $replace;

    public function onCreate()
    {
        if( strpos( $this->filtername, self::FILTER_NAME ) !== 0 ){
            return false;
        }

        $this->search  = self::SEARCH_VALUE;
        $this->replace = self::REPLACE_VALUE;

        return true;
    }
    public function filter( $in, $out, &$consumed, $closing )
    {
        while( $res = stream_bucket_make_writeable( $in ) ){
            $res->data = str_replace( $this->search, $this->replace, $res->data );
            $consumed += $res->datalen;
            /** @noinspection PhpParamsInspection */
            stream_bucket_append( $out, $res );
        }
        return PSFS_PASS_ON;
    }
}