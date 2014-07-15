<?php
/**
 * Melody library
 *
 * @category   Melody
 * @package    Melody_ModelBundle
 * @subpackage Melody_ModelBundle_Command
 * @version    $Id:  $
 * @link       https://github.com/AlexandreBeaurain/melody
 */

namespace Melody\ModelBundle\Command;

use Melody\ModelBundle\Schema\Dia;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

/**
 * Generates model configuration from dia file.
 *
 * @author Alexandre Beaurain
 */
class SchemaCommand extends AbstractCommand
{

    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setDefinition(array(
                new InputOption('orm', '', InputOption::VALUE_NONE, 'ORM to use'),
            ))
            ->setDescription('Generates database schema from UML dia files')
            ->setHelp(
'The <info>model:dia-to-schema</info> command generates schema files from UML dia files.

You can call it throught the following command :

<info>php app/console model:schema</info>

By default the ORM used is automatically detected, but you can force it by specifying the --orm parameter with any of following values
 - '.implode( "\n".' - ', $this->getOrmList() )
            )
            ->setName('model:schema')
        ;
    }

    /**
     * @see Command
     *
     * @throws \InvalidArgumentException When namespace doesn't end with Bundle
     * @throws \RuntimeException         When bundle can't be executed
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $ormList = $this->getRegistredOrmList($input);
        $output->writeln('<info>orm</info> '.implode(', ',$ormList));
        $container = $this->getContainer();
        $kernel = $container->get('kernel');
        /* @var $kernel \AppKernel */
        foreach( $container->getParameter('kernel.bundles') as $bundleName => $bundleClass ) {
            $resource = '@'.$bundleName.'/Resources/config';
            $namespace = substr( $bundleClass, 0, strrpos($bundleClass, '\\') );
            try {
                $path = $kernel->locateResource($resource);
                $entityRepository = dirname(dirname($resource)).'/Entity';
                $finder = new Finder();
                foreach( $finder->files()->name('schema.dia')->in($path) as $uml ) {
                    $output->writeln('<info>converting</info> '.$uml);
                    $this->convert($uml,$ormList,$namespace,$entityRepository,$output);
                }
            }
            catch (\InvalidArgumentException $e ) {
            }
        }
    }

    protected function convert($uml,$ormList,$namespace,$entityRepository,OutputInterface $output) {
        $parser = new Dia($uml,$output);
        foreach( $ormList as $orm ) {
            $configDirectory = dirname( $uml );
            $output->writeln('<info>output</info> '.$configDirectory);
            $ormClass = 'Melody\\ModelBundle\\Orm\\'.$orm;
            $orm = new $ormClass($parser->getSchema(),$namespace,$entityRepository);
            $orm->writeConfiguration($configDirectory);
        }
    }
}
