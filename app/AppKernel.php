<?php

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpKernel\Kernel;

class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = array(
            new Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
            new Symfony\Bundle\SecurityBundle\SecurityBundle(),
            new Symfony\Bundle\MonologBundle\MonologBundle(),
            new Symfony\Bundle\SwiftmailerBundle\SwiftmailerBundle(),
            new Doctrine\Bundle\DoctrineBundle\DoctrineBundle(),
            new Sensio\Bundle\FrameworkExtraBundle\SensioFrameworkExtraBundle(),
            new Symfony\Bundle\TwigBundle\TwigBundle(),

            new FOS\UserBundle\FOSUserBundle(),
            new FOS\RestBundle\FOSRestBundle(),
            new Rollerworks\Bundle\MultiUserBundle\RollerworksMultiUserBundle(),
            new JMS\SerializerBundle\JMSSerializerBundle(),
            new Nelmio\ApiDocBundle\NelmioApiDocBundle(),
            new Lexik\Bundle\JWTAuthenticationBundle\LexikJWTAuthenticationBundle(),
            new Gfreeau\Bundle\GetJWTBundle\GfreeauGetJWTBundle(),
            new Gfreeau\Bundle\CustomValidationPathBundle\GfreeauCustomValidationPathBundle(),
            new Nelmio\CorsBundle\NelmioCorsBundle(),
            new Stof\DoctrineExtensionsBundle\StofDoctrineExtensionsBundle(),
            new Leezy\PheanstalkBundle\LeezyPheanstalkBundle(),

            new UR\Bundle\UserBundle\URUserBundle(),
            new UR\Bundle\ApiBundle\URApiBundle(),
            new UR\Bundle\AdminApiBundle\URAdminApiBundle(),
            new UR\Bundle\AppBundle\URAppBundle(),
            new UR\Bundle\UserSystem\PublisherBundle\URUserSystemPublisherBundle(),
            new UR\Bundle\UserSystem\AdminBundle\URUserSystemAdminBundle(),
            new UR\Bundle\PublicBundle\URPublicBundle(),

            new DataDog\PagerBundle\DataDogPagerBundle(),
            new Doctrine\Bundle\DoctrineCacheBundle\DoctrineCacheBundle(),
            new Liuggio\ExcelBundle\LiuggioExcelBundle(),
            new UR\Bundle\ReportApiBundle\ReportApiBundle(),

            new Tagcade\Bundle\URCustomTransformBundle\URCustomTransformBundle()
        );

        if (in_array($this->getEnvironment(), array('dev', 'test'))) {
            $bundles[] = new \Doctrine\Bundle\FixturesBundle\DoctrineFixturesBundle();
            $bundles[] = new Webfactory\Bundle\ExceptionsBundle\WebfactoryExceptionsBundle();
            $bundles[] = new Symfony\Bundle\WebProfilerBundle\WebProfilerBundle();
            $bundles[] = new Sensio\Bundle\DistributionBundle\SensioDistributionBundle();
            $bundles[] = new Sensio\Bundle\GeneratorBundle\SensioGeneratorBundle();
        }

        return $bundles;
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load($this->getRootDir() . '/config/config_' . $this->getEnvironment() . '.yml');
//        $loader->load(__DIR__.'/config/config_'.$this->getEnvironment().'.yml');
    }

    public function getCacheDir()
    {
        if ($this->isRunningOnDevelopmentVM()) {
            return '/dev/shm/unified-reports-api/cache/' . $this->environment;
        }

        return parent::getCacheDir();
    }

    public function getLogDir()
    {
        if ($this->isRunningOnDevelopmentVM()) {
            return '/dev/shm/unified-reports-api/logs';
        }

        return parent::getLogDir();
    }

    /**
     * Checks that an environment variable is set and has a truthy value
     *
     * @param string $variable
     * @return bool
     */
    protected function checkForEnvironmentVariable($variable)
    {
        return isset($_SERVER[$variable]) && (bool)$_SERVER[$variable];
    }

    /**
     * The application is in development mode if an environment variable TAGCADE_DEV is set
     * and an environment variable TAGCADE_PROD is not set
     *
     * @return bool
     */
    protected function isRunningOnDevelopmentVM()
    {
        return !$this->checkForEnvironmentVariable('TAGCADE_PROD') && $this->checkForEnvironmentVariable('TAGCADE_DEV');
    }
}
