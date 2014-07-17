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

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

/**
 * Find orm used in project
 *
 * @author Alexandre Beaurain
 */
abstract class AbstractCommand extends ContainerAwareCommand
{

	private $ormList = array(
		'CouchDB' => 'DoctrineCouchDBBundle',
		'MongoDB' => 'DoctrineMongoDBBundle',
		'PHPCR' => 'DoctrinePHPCRBundle',
		'Doctrine' => 'DoctrineBundle',
		'Propel' => 'PropelBundle'
	);

	protected function getOrmList() {
		return array_keys( $this->ormList );
	}

	protected function getRegistredOrmList(InputInterface $input) {
		$bundles = $this->getContainer()->get('kernel')->getBundles();
		if ($input->hasOption('orm') && isset($this->ormList[$input->getOption('orm')]) ) {
			return array($input->getOption('orm'));
		}
		$ormList = array();
		foreach( $this->ormList as $orm => $bundle ) {
			if ( isset($bundles[$bundle]) ) {
				$ormList[] = $orm;
			}
		}
		if ( ! empty($ormList) ) {
			return $ormList;
		}
		throw new Exception('No orm bundle detected nor specified.');
	}
}
