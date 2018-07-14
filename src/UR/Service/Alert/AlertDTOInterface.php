<?php

namespace UR\Service\Alert;


interface AlertDTOInterface
{
    /** int */
    const CODE = 'code';

    /** mixed */
    const DETAILS = 'detail';

    /** string */
    const FILE_NAME = 'fileName';

    /** int */
    const DATA_SOURCE_ID = 'dataSourceId';

    /** string */
    const DATA_SOURCE_NAME = 'dataSourceName';

    /** int */
    const DATA_SET_ID = 'dataSetId';

    /** string */
    const DATA_SET_NAME = 'dataSetName';
}