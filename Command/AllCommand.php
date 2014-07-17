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

use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Finder\Finder;

/**
 * Cascade all commands from shema file to update sql structure
 */
class AllCommand extends AbstractCommand
{

    protected function configure()
    {
        $this
        ->setName('model:all')
        ->setDescription('Cascade all commands from shema file to update sql structure')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();
        $kernel = $container->get('kernel');
        $commandList = array( array( 'name' => 'model:schema', 'arguments' => array('') ) );
        foreach( $this->getRegistredOrmList($input) as $orm ) {
            switch ( $orm ) {
                case 'Propel':
                    $commandList[] = array( 'name' => 'propel:build', 'arguments' => array('') );
                    //$commandList[] = array( 'name' => 'propel:form:generate', 'arguments' => array('') );
                    $commandList[] = array( 'name' => 'propel:migration:generate-diff', 'arguments' => array('') );
                    $commandList[] = array( 'name' => 'propel:migration:migrate', 'arguments' => array('') );
                    $commandList[] = array( 'name' => 'cache:clear', 'arguments' => array('') );
                    break;
                case 'Doctrine':
                    foreach( $container->getParameter('kernel.bundles') as $bundleName => $bundleClass ) {
                        $resource = '@'.$bundleName.'/Resources/config';
                        $namespace = substr( $bundleClass, 0, strrpos($bundleClass, '\\') );
                        try {
                            $path = $kernel->locateResource($resource);
                            $entityRepository = dirname(dirname($resource)).'/Entity';
                            $finder = new Finder();
                            $generateEntities = false;
                            foreach( $finder->files()->name('schema.dia')->in($path) as $uml ) {
                                $generateEntities = true;
                            }
                            if ( $generateEntities ) {
                                $commandList[] = array( 'name' => 'doctrine:generate:entities', 'arguments' => array($bundleName) );
                            }
                        }
                        catch (\InvalidArgumentException $e ) {
                        }
                    }
                    $commandList[] = array( 'name' => 'doctrine:schema:update', 'arguments' => array('--force') );
                    $commandList[] = array( 'name' => 'cache:clear', 'arguments' => array('') );
                    break;
                default:
                    break;
            }
        }
        foreach( $commandList as $commandNameAndArguments ) {
            $command = $this->getApplication()->find($commandNameAndArguments['name']);
            $localInput = new ArrayInput($commandNameAndArguments['arguments']);
            var_dump($commandNameAndArguments);
            $command->run($localInput, $output);
        }
    }

}
