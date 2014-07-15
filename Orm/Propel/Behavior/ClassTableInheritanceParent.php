<?php
/**
 * Melody library
 *
 * @category   Melody
 * @package    Melody_ModelBundle
 * @subpackage Melody_ModelBundle_Orm
 * @version    $Id:  $
 * @link       https://github.com/AlexandreBeaurain/melody
 */

namespace Melody\ModelBundle\Orm\Propel\Behavior;

/**
 * Symmetrical behavior of the concrete_inheritance. When model A extends model B,
 * model A gets the concrete_inheritance behavior, and model B gets the
 * class_table_inheritance_parent
 *
 * @author     Alexandre Beaurain <alexandre.beaurain@adfab.fr>
 */
class ClassTableInheritanceParent extends \ConcreteInheritanceParentBehavior
{
	public function objectMethods($builder) {
		$script = parent::objectMethods($builder);
		$this->addBuildInheritanceCriteria($script);
		return $script;
	}

	protected function addBuildInheritanceCriteria(&$script) {
		$class = $this->builder->getNewStubObjectBuilder($this->table)->getClassname();
		$peer = $this->builder->getNewStubPeerBuilder($this->table)->getClassname();
		$script .= '
/**
 * @return Criteria
 */
public function build'.$class.'Criteria()
{
	return '.$class.'::buildCriteria();
}

/**
 * @return Criteria
 */
public function build'.$class.'PkeyCriteria()
{
	return '.$class.'::buildPkeyCriteria();
}
';
	}

	public function objectFilter(&$script,$builder) {
		$class = $builder->getNewStubObjectBuilder($this->table)->getClassname();
		$script = preg_replace(array(
			'|\$this\->buildCriteria|ims',
			'|\$this\->buildPkeyCriteria|ims',
			'|\$this\->doInsert|ims',
			'|\$this\->doUpdate|ims'
		), array(
			'$this->build'.$class.'Criteria',
			'$this->build'.$class.'PkeyCriteria',
			$class.'::doInsert',
			$class.'::doUpdate'
		), $script);
	}

	public function peerFilter(&$script,$builder) {
		$class = $builder->getNewStubObjectBuilder($this->table)->getClassname();
		$script = preg_replace(array(
			'|\$values\->buildCriteria|ims',
			'|\$values\->buildPkeyCriteria|ims',
			'|private static \$field|s'
		), array(
			'$values->build'.$class.'Criteria',
			'$values->build'.$class.'PkeyCriteria',
			'protected static $field'
		), $script);
		$this->peerName = $class.'Peer';
		$script = preg_replace_callback('/BasePeer::TYPE_COLNAME => array[^\)]*\)/ims',array($this,'replaceSelfColumn'), $script);
	}

	public function replaceSelfColumn($match) {
		return strtr( $match[0], array('self::'=>$this->peerName.'::') );
	}

}
