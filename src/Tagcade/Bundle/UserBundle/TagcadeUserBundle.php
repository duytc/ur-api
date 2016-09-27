<?php

namespace Tagcade\Bundle\UserBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tagcade\Bundle\UserBundle\DependencyInjection\Compiler\AuthenticationListenerCompilerPass;

class TagcadeUserBundle extends Bundle
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
