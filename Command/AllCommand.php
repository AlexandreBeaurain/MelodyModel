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
        $commandList = array( array('command'=>'model:schema','') );
        foreach( $this->getRegistredOrmList($input) as $orm ) {
            switch ( $orm ) {
                case 'Propel':
                    $commandList[] = array('command'=>'propel:build','');
                    //$commandList[] = array('command'=>'propel:form:generate','');
                    $commandList[] = array('command'=>'propel:migration:generate-diff','');
                    $commandList[] = array('command'=>'propel:migration:migrate','');
                    $commandList[] = array('command'=>'cache:clear','');
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
                            foreach( $finder->files()->name('*.orm.xml')->in($path.'/doctrine') as $xml ) {
                                $generateEntities = true;
                            }
                            if ( $generateEntities ) {
                                $commandList[] = array('command'=>'doctrine:generate:entities','--no-backup'=>true,'name'=>$bundleName);
                            }
                        }
                        catch (\InvalidArgumentException $e ) {
                        }
                    }
                    $commandList[] = array('command'=>'doctrine:schema:update','--force'=>true);
                    $commandList[] = array('command'=>'cache:clear','');
                    break;
                default:
                    break;
            }
        }
        foreach( $commandList as $arguments ) {
            $command = $this->getApplication()->find($arguments['command']);
            $localInput = new ArrayInput($arguments);
            $command->run($localInput, $output);
        }
    }

}
