<?php

namespace UR\Bundle\UserBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use UR\Bundle\UserBundle\DependencyInjection\Compiler\AuthenticationListenerCompilerPass;

class URUserBundle extends Bundle
{
//    public function getParent()
//    {
//        return 'FOSUserBundle';
//    }

    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new AuthenticationListenerCompilerPass());
    }
}
