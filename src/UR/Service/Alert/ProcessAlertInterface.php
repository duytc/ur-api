<?php

namespace UR\Service\Alert;


interface ProcessAlertInterface
{
    public function importAlert(array $error);

    public function uploadAlert(array $error);
}