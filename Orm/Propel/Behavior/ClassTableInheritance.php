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
 * Makes a model inherit another one. The model with this behavior maps model
 * to table. In addition, both the ActiveRecord, ActivePeer and
 * ActiveQuery classes will extend the related classes of the parent model.
 *
 * @author     Alexandre Beaurain <alexandre.beaurain@adfab.fr>
 */
class ClassTableInheritance extends \ConcreteInheritanceBehavior
{

	protected $inherited_behaviors = array();
	protected $inherited_behavior_methods = array(
		'preSave',
		'preUpdate',
		'preInsert',
		'objectMethods',
		'queryMethods',
		'filterMethods'
	);

	public function modifyTable()
	{
		$parentTable = $this->getParentTable();
		foreach ($parentTable->getBehaviors() as $behavior) {
			if (in_array($behavior->getName(), array('concrete_inheritance', 'class_table_inheritance'))) {
				$parent_descendant_column = $behavior->getParameter('descendant_column');
				if ( $parent_descendant_column == $this->getParameter('descendant_column')) {
					$this->addParameter(array('name' => 'descendant_column', 'value' => 'descendant_'.$parent_descendant_column ));
				}
			}
			else if ( ! in_array($behavior->getName(), array('concrete_inheritance_parent', 'class_table_inheritance_parent','symfony','symfony_behaviors'))) {
				$this->inherited_behaviors[] = clone $behavior;
			}
		}

		// tell the parent table that it has a descendant
		if (!$parentTable->hasBehavior('class_table_inheritance_parent')) {
			$parentBehavior = new ClassTableInheritanceParent();
			$parentBehavior->setName('class_table_inheritance_parent');
			$parentBehavior->addParameter(array('name' => 'descendant_column', 'value' => $this->getParameter('descendant_column')));
			$parentTable->addBehavior($parentBehavior);
			// The parent table's behavior modifyTable() must be executed before this one
			$parentBehavior->getTableModifier()->modifyTable();
			$parentBehavior->setTableModified(true);
		}

	}

	protected function getAscendantTable() {
		$parentTable = $this->getParentTable();
		foreach ($parentTable->getBehaviors() as $behavior) {
			if ( in_array($behavior->getName(), array('concrete_inheritance', 'class_table_inheritance')) ) {
				return $behavior->getAscendantTable();
			}
		}
		return $parentTable;
	}

	protected function callAllInheritedBehaviorMethods( $name, $builder ) {
		$return = '';
		foreach ( $this->inherited_behaviors as $behavior ) {
			if ( method_exists( $behavior, $name ) ) {
				$return .= $behavior->$name($builder);
			}
		}
		return $return;
	}

	public function preSave($builder)
	{
		return $this->callAllInheritedBehaviorMethods( 'preSave', $builder);
	}

	public function preInsert($builder)
	{
		return $this->callAllInheritedBehaviorMethods( 'preInsert', $builder);
	}

	public function preUpdate($builder)
	{
		return $this->callAllInheritedBehaviorMethods( 'preUpdate', $builder);
	}

	public function postUpdate($builder)
	{
		return $this->callAllInheritedBehaviorMethods( 'postUpdate', $builder);
	}

	public function post($builder)
	{
		return $this->callAllInheritedBehaviorMethods( 'postInsert', $builder);
	}

	public function postSave($builder)
	{
		return $this->callAllInheritedBehaviorMethods( 'postSave', $builder);
	}

	public function objectMethods($builder) {
		$script = parent::objectMethods($builder);
		$this->addIsParentModified($script,$builder);
		return $script;
	}

	protected function addIsParentModified(&$script,$builder) {
		$class = $builder->getClassname();
		$peer = $builder->getNewStubPeerBuilder($this->table)->getClassname();
		$script .= '
/**
 * @return boolean
 */
public function isParentModified()
{
	if ( !empty($this->modifiedColumns) ) {
		$intersect = array_diff( $this->modifiedColumns, '.$peer.'::getFieldNames( BasePeer::TYPE_COLNAME ) );
		return ! empty( $intersect );
	}
	return false;
}
';
	}

	public function parentClass($builder)
	{
		switch (get_class($builder)) {
			case 'PHP5PeerBuilder':
				return $builder->getNewStubPeerBuilder($this->getParentTable())->getClassname();
				break;
			case 'PHP5ObjectBuilder':
				return $builder->getNewStubObjectBuilder($this->getParentTable())->getClassname();
				break;
			case 'QueryBuilder':
				return $builder->getNewStubQueryBuilder($this->getParentTable())->getClassname();
				break;
			default:
				return null;
				break;
		}
	}

	public function ascendantClass($builder)
	{
		switch (get_class($builder)) {
			case 'PHP5PeerBuilder':
				return $builder->getNewStubPeerBuilder($this->getAscendantTable())->getClassname();
				break;
			case 'PHP5ObjectBuilder':
				return $builder->getNewStubObjectBuilder($this->getAscendantTable())->getClassname();
				break;
			case 'QueryBuilder':
				return $builder->getNewStubQueryBuilder($this->getAscendantTable())->getClassname();
				break;
			default:
				return null;
				break;
		}
	}


	public function postDelete($script) {
		return '$this->setDeleted(false);
parent::delete($con);
';
	}

	protected function isCopyData()
	{
		return false;
	}

	public function objectFilter(&$script,$builder) {
		$class = $builder->getNewStubObjectBuilder($this->table)->getClassname();
		$peer = $builder->getNewStubPeerBuilder($this->table)->getClassname();
		$parentClass = $this->parentClass($builder);
		$parentPeer = $builder->getNewStubPeerBuilder($this->getParentTable())->getClassname();
		$parentPeerFullQualitfied = $builder->getNewStubPeerBuilder($this->getParentTable())->getFullyQualifiedClassname();
		$builder->declareClassNamespace($parentPeerFullQualitfied);
		$modified_keys = '';
		$inherited_modified_keys = '';
		$decendantColumn = $this->getParentTable()->getColumn($this->getParameter('descendant_column'))->getPhpName();
		foreach( $this->table->getColumns() as $column ) {
			if ($column->isPrimaryKey()) {
				$modified_keys .= "\n".'		$this->modifiedColumns[] = '.$peer.'::'.($column->getPeerName()?$column->getPeerName():strtoupper($column->getName())).';';
			}
			if ($column->isPrimaryKey()) {
				$inherited_modified_keys .= "\n".'			$this->modifiedColumns[] = '.$parentPeer.'::'.($column->getPeerName()?$column->getPeerName():strtoupper($column->getName())).';';
			}
		}
		$script = preg_replace(array(
			'|public function hydrate\(([^\)]+)\)[\s\t \n\r]+\{|ims',
			'|protected function doSave\(([^\)]+)\)[\s\t \n\r]+{[\s\t \n\r]+\$affectedRows = 0;|ims',
			'|public function fromArray\(([^\)]+)\)[\s\t \n\r]+\{|ims',
			'|public function toArray\(([^\{]+{)([^\}]+}[\s\t \n\r]+([^;]+);[\s\t \n\r]+([^;]+);[\s\t \n\r]+([^;]+);)|ims',
			'|public function copyInto\(([^\)]+)\)[\s\t \n\r]+\{|ims'
		), array(
			'public function hydrate(\1) {'."\n".
			'		$startcol = parent::hydrate($row, $startcol, $rehydrate);',
			'protected function doSave(\1) {'."\n".
			'		$modified_columns = $this->getModifiedColumns();'."\n".
			'		$is_new = $this->isNew();'."\n".
			'		if ( ( $is_new && ! $this->getId() ) || $this->isParentModified() ) {'."\n".
			'			if ( ! $this->get'.$decendantColumn.'() ) {'."\n".
			'				$this->set'.$decendantColumn.'(\''.$class.'\');'."\n".
			'			}'.$inherited_modified_keys."\n".
			'			$affectedRows = parent::doSave($con);'."\n".
			'		}'."\n".
			'		else {'."\n".
			'			$affectedRows = 0;'."\n".
			'		}'."\n".
			'		$this->setNew($is_new);'."\n".
			'		$this->modifiedColumns = $modified_columns;'.
			$modified_keys,
			'public function fromArray(\1) {'."\n".
			'		parent::fromArray($arr, $keyType);',
			'public function toArray(\1'."\n".
			'		\2;'."\n".
			'		\3;'."\n".
			'		\4;'."\n".
			'		$result = array_merge( parent::toArray($keyType, $includeLazyLoadColumns, $alreadyDumpedObjects, $includeForeignObjects), \5 );',
			'public function copyInto(\1) {'."\n".
			'		parent::copyInto($copyObj, $deepCopy);',
		), $script);
	}

	public function peerFilter(&$script,$builder) {
		$peer = $builder->getClassname();
		$class = $builder->getNewStubObjectBuilder($this->table)->getClassname();
		$parentPeer = $this->parentClass($builder);
		$parentClass = $builder->getNewStubObjectBuilder($this->getParentTable())->getClassname();
		$ascendantPeer = $this->ascendantClass($builder);
		$ascendantClass = $builder->getNewStubObjectBuilder($this->getAscendantTable())->getClassname();

		$decendantColumn = $this->getParentTable()->getColumn($this->getParameter('descendant_column'))->getPhpName();
		$primary_keys = array();
		$foreign_primary_keys = array();
		$alias_primary_keys = array();
		$alias_foreign_primary_keys = array();
		foreach( $this->table->getColumns() as $column ) {
			if ($column->isPrimaryKey()) {
				$primary_keys[] .= 'self::'.($column->getPeerName()?$column->getPeerName():strtoupper($column->getName()));
				$foreign_primary_keys[] = $parentPeer.'::'.($column->getPeerName()?$column->getPeerName():strtoupper($column->getName()));
				$alias_primary_keys[] .= 'self::alias($alias,self::'.($column->getPeerName()?$column->getPeerName():strtoupper($column->getName())).')';
				$alias_foreign_primary_keys[] = $parentPeer.'::alias($alias.\'_parent\','.$parentPeer.'::'.($column->getPeerName()?$column->getPeerName():strtoupper($column->getName())).')';
			}
		}
		if ( count( $primary_keys ) > 1 ) {
			$inherit_join = 'array('.implode(',',$primary_keys).'), array('.implode(',',$foreign_primary_keys).')';
			$inherit_alias_join = 'array('.implode(',',$alias_primary_keys).'), array('.implode(',',$alias_foreign_primary_keys).')';
		}
		else if ( count( $primary_keys ) > 0 ) {
			$inherit_join = $primary_keys[0].','.$foreign_primary_keys[0];
			$inherit_alias_join = $alias_primary_keys[0].','.$alias_foreign_primary_keys[0];
		}

		$script = preg_replace(array(
			'|public static function addSelectColumns\(([^\)]+)\)[\s\t \n\r]+\{|ims',
			'|public static function doValidate\('.$class.' \$obj|ims',
			'|public static function addInstanceToPool\('.$class.' \$obj([^\)]+)\)[\s\t \n\r]+\{|ims',
			'|public static function getInstanceFromPool\(([^\)]+)\)[\s\t \n\r]+\{[\s\t \n\r]+if \(Propel::isInstancePoolingEnabled\(\)\) \{[\s\t \n\r]+if \(isset\(self::\$instances\[\$key\]\)\) \{[\s\t \n\r]+return self::\$instances\[\$key\];|ims',
			'|BasePeer::doDelete\(\$criteria, \$con\);[\s\t \n\r]+([\w]+)::clearRelatedInstancePool\(\);[\s\t \n\r]+\$con->commit\(\);|ims',
			'|private static \$field|s',
			'|function translateFieldName\(([^\)]+)\)[\s\t \n\r]+\{([^\{]+)\{([^\}]+)\}|ims'
		), array(
			'public static function addSelectColumns(\1) {'."\n".
			'		parent::addSelectColumns($criteria,$alias!==null?$alias.\'_parent\':null);'."\n".
			'		$cloned_criteria = clone $criteria;'."\n".
			'		$criteria->clear();'."\n".
			'		if (null === $alias) {'."\n".
			'			$criteria->addJoin('.$inherit_join.');'."\n".
			'		} else {'."\n".
			'			$criteria->addAlias($alias.\'_parent\', '.$parentPeer.'::TABLE_NAME);'."\n".
			'			$criteria->addJoin('.$inherit_alias_join.');'."\n".
			'		}'."\n".
			'		$criteria->mergeWith($cloned_criteria);',
			'public static function doValidate('.$ascendantClass.' $obj',
			'public static function addInstanceToPool('.$ascendantClass.' $obj\1) {'."\n".
			'		parent::addInstanceToPool($obj, $key);',
			'public static function getInstanceFromPool(\1) {'."\n".
			'		if (Propel::isInstancePoolingEnabled()) {'."\n".
			'			if (isset(self::$instances[$key])) {'."\n".
			'				$instance = self::$instances[$key];'."\n".
			'				if ($instance instanceof '.$class.') {'."\n".
			'					return $instance;'."\n".
			'				}',
			'BasePeer::doDelete($criteria, $con);'."\n".
			'			\1::clearRelatedInstancePool();'."\n".
			'			$con->commit();'."\n".
			'			'.$parentPeer.'::doDelete($values, $con);',
			'protected static $field',
			'function translateFieldName(\1) {\2{'."\n".
			'			return '.$parentPeer.'::translateFieldName(\1);'."\n".
			'		}'
		), $script);
	}

	public function queryFilter(&$script,$builder) {
	    $parentQuery = $builder->getNewStubQueryBuilder($this->getParentTable())->getFullyQualifiedClassname();
	    $builder->declareClassNamespace($parentQuery);
		$script = preg_replace(array(
			'|protected function findPkSimple\(([^\)]+)\)[\s\t \n\r]+\{|ims'
		), array(
			'protected function findPkSimple(\1) {'."\n".
			'		return $this->findPkComplex(\1);'."\n".
			'		'
		), $script);
	}

}
