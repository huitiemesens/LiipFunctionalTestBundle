<?php

namespace Liip\FunctionalTestBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * Command used to update the project.
 */
class RunParatestCommand extends ContainerAwareCommand
{
    private $container;
    private $configuration;
    private $output;
    private $process = 5;
    private $testDbPath;
    private $phpunit = './bin/phpunit';

    /**
     * Configuration of the command.
     */
    protected function configure()
    {
        $this
            ->setName('test:run')
            ->setDescription('Run phpunit tests with multiple process')
        ;
    }

    protected function prepare()
    {
        $this->configuration = $this->getContainer()->hasParameter('liip_functional_test');
        $paratestCfg = (!isset($this->configuration['paratest'])) ? array('process' => $this->process, 'phpunit' => $this->phpunit) : $this->configuration['paratest'];

        $this->process = (!empty($this->configuration['process'])) ? $paratestCfg['process'] : $this->process;
        $this->phpunit = (!empty($this->configuration['phpunit'])) ? $paratestCfg['phpunit'] : $this->phpunit;
        $this->testDbPath = $this->getContainer()->get('kernel')->getRootDir();
        $this->output->writeln("Cleaning old dbs in $this->testDbPath ...");
        $createDirProcess = new Process('mkdir -p '.$this->testDbPath.'/cache/test/');
        $createDirProcess->run();
        $cleanProcess = new Process("rm -fr $this->testDbPath/cache/test/dbTest.db $this->testDbPath/cache/test/dbTest*.db*");
        $cleanProcess->run();
        $this->output->writeln("Creating Schema in $this->testDbPath ...");
        $createProcess = new Process('php app/console doctrine:schema:create --env=test');
        $createProcess->run();

        $this->output->writeln('Initial schema created');
        $populateProcess = new Process("php app/console doctrine:fixtures:load -n --fixtures $this->testDbPath/../src/overlord/AppBundle/Tests/DataFixtures/ORM/ --env=test");
        $populateProcess->run();

        $this->output->writeln('Initial schema populated, duplicating....');
        for ($a = 0; $a < $this->process; ++$a) {
            $test = new Process("cp $this->testDbPath/cache/test/dbTest.db ".$this->testDbPath."/cache/test/dbTest$a.db");
            $test->run();
        }
    }

    /**
     * Content of the command.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;
        $this->prepare();
        if (is_file('vendor/bin/paratest') !== true) {
            $this->output->writeln('Error : Install paratest first');
        } else {
            $this->output->writeln('Done...Running test.');
            $runProcess = new Process('vendor/bin/paratest -c phpunit.xml.dist --phpunit '.$this->phpunit.' --runner WrapRunner  -p '.$this->process);
            $runProcess->run(function ($type, $buffer) {
                echo $buffer;
            });
        }
    }
}
