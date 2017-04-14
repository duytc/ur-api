<?php

namespace UR\Service\Command;

class CommandService
{
    /**
     * @var String
     * i.e prod or dev
     */
    private $env;

    private $rootDir;

    private $isDebug;

    private $logDir;

    private $tempFileDir;

    function __construct($rootDir, $env, $isDebug, $logDir, $tempFileDir)
    {
        $this->rootDir = $rootDir;
        $this->env = $env;
        $this->isDebug = $isDebug;
        $this->logDir = $logDir;
        $this->tempFileDir = $tempFileDir;
    }

    /**
     * @param $runCommand
     * @return string
     */
    public function getAppConsoleCommand($runCommand)
    {
        $pathToSymfonyConsole = $this->rootDir;

        $appConsole = sprintf('php %s/console', $pathToSymfonyConsole);

        $envFlags = sprintf('--env=%s -v', $this->env);
        if (!$this->isDebug) {
            $envFlags .= ' --no-debug';
        }

        $command = sprintf('%s %s %s',
            $appConsole,
            $runCommand, $envFlags
        );

        return $command;
    }

    /**
     * @param $fileName
     * @param $suffix
     * @return resource
     */
    public function createLogFile($fileName, $suffix)
    {
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0777, true);
        }

        $logFile = sprintf('%s/%s_%s.log', $this->logDir, $fileName, $suffix);
        return $logFile;
    }

    public function createTempFile($fileName, $suffix)
    {
        if (!is_dir($this->tempFileDir)) {
            mkdir($this->tempFileDir, 0777, true);
        }

        $tempFileName = sprintf('%s_%s.json', $fileName, $suffix);
        $integrationConfigFile = sprintf('%s/%s', $this->tempFileDir, $tempFileName);
        return $integrationConfigFile;
    }
}