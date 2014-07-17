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
        $commandList = array(
            'model:schema'=>array('')
        );
        foreach( $this->getRegistredOrmList($input) as $orm ) {
            switch ( $orm ) {
                case 'Propel':
                    $commandList['propel:build']=array('');
                    //$commandList['propel:form:generate']=array('');
                    $commandList['propel:migration:generate-diff']=array('');
                    $commandList['propel:migration:migrate']=array('');
                    $commandList['cache:clear']=array('');
                    break;
                case 'Doctrine':
                    break;
                default:
                    break;
            }
        }
        foreach( $commandList as $commandName => $arguments ) {
            $command = $this->getApplication()->find($commandName);
            $localInput = new ArrayInput($arguments);
            $command->run($localInput, $output);
        }
    }

}
