<?php

namespace Tagcade\Bundle\UserSystem\PublisherBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Rollerworks\Bundle\MultiUserBundle\DependencyInjection\Compiler\RegisterFosUserMappingsPass;

class TagcadeUserSystemPublisherBundle extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container)
    {
        parent::build($container);
        $container->addCompilerPass(RegisterFosUserMappingsPass::createOrmMappingDriver('tagcade_user_system_publisher'));
    }
}
