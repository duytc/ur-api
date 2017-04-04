<?php

namespace UR\Service\Parser\Transformer;

interface TransformerInterface
{
    /**
     * validate if transform is valid
     *
     * @return bool
     * @throws \Exception if error
     */
    public function validate();
}