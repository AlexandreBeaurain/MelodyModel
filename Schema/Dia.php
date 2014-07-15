<?php
/**
 * Melody library
 *
 * @category   Melody
 * @package    Melody_ModelBundle
 * @subpackage Melody_ModelBundle_Schema
 * @version    $Id:  $
 * @link       https://github.com/AlexandreBeaurain/melody
 */

namespace Melody\ModelBundle\Schema;

use Melody\ModelBundle\Schema;
use Symfony\Component\DependencyInjection\Container;
use Melody\ModelBundle\Schema\Dia\Attribute;
use Melody\ModelBundle\Schema\Dia\Composite;
use Melody\ModelBundle\Schema\Dia\Connection;
use Melody\ModelBundle\Schema\Dia\Object;

/**
 * Class for reading and writing dia file.
 */
class Dia extends Schema
{

    protected $file;
    protected $classes = array();
    protected $id2classes = array();
    protected $types = array(
        'boolean',
        'integer',
        'float',
        'decimal',
        'string',
        'array',
        'object',
        'blob',
        'clob',
        'timestamp',
        'time',
        'date',
        'enum',
        'gzip'
    );
    protected $values = array(
        'notnull',
        'type',
        'unique',
        'default',
        'primary',
        'i18n'
    );
    protected $behaviors = array(
        'Versionable',
        'Timestampable',
        'Sluggable',
        'NestedSet',
        'Searchable',
        'Geocodable',
        'SoftDelete',
        'Sortable'
    );

    public function __construct($file) {
        $this->file = $file;
        if ( ! is_file( $file ) ) {
            throw \FileNotFoundException($file.' not found');
        }
        $dom = new \DOMDocument();
        $dom->loadXML(implode("\n",gzfile($file)));
        $this->classes = array();
        $o_class_list = array();
        $o_generalization_list = array();
        $o_association_list = array();
        foreach( $dom->getElementsByTagName('object') as $element ) {
            $o = new Object($element);
            switch ( $o->getType() ) {
                case 'UML - Class':
                    $o_class_list[] = $o;
                    $this->parseClass($o);
                    break;
                case 'UML - Generalization':
                    $o_generalization_list[] = $o;
                    break;
                case 'UML - Association':
                    $o_association_list[] = $o;
                    break;
            }
        }
        foreach( $o_generalization_list as $o ) {
            $this->parseGeneralization($o);
        }
        foreach( $o_association_list as $o ) {
            $this->parseAssociation($o);
        }
    }

    public function getSchema() {
        return $this->classes;
    }

    protected function parseAttributeValue($value) {
        $values = array();
        foreach( explode(' ',$value) as $part ) {
            $parts = explode(':',$part,2);
            if ( strlen($parts[0]) > 0 ) {
                if ( isset( $parts[1] ) ) {
                    $values[$parts[0]] = $parts[1];
                }
                else {
                    $values[$parts[0]] = true;
                }
            }
        }
        return $values;
    }

    protected function parseAttribute($className, Composite $attribute) {
        $a = $attribute->getAttributes();
        $attributeName = $a['name']->getValue();
        $type = $a['type']->getValue();
        $description = $a['comment']->getValue();
        $values = $this->parseAttributeValue( $a['value']->getValue() );
        $match = array();
        if ( preg_match('|^string\(([\d]+)\)$|ims', $type, $match ) ) {
            $size = (int) $match[1];
            if ( $size > 65536 ) {
                $type = 'clob';
                unset($size);
            }
            else if ( $size > 255 ) {
                $type = 'longvarchar';
                unset($size);
            }
            else {
                $type = 'varchar';
                $values['size'] = $size;
            }
        }
        if ( isset( $values['notnull'] ) ) {
            unset( $values['notnull'] );
            $values['required'] = true;
        }
        if ( isset( $values['unique'] ) ) {
            unset( $values['unique'] );
            $values['index'] = 'unique';
        }
        if ($description) {
            $values['description'] = $description;
        }
        if ( isset( $values['collation'] ) ) {
            $values['vendor']['Collate'] = $values['collation'];
            unset( $values['collation'] );
        }
        if ( isset( $values['i18n'] ) ) {
            unset( $values['i18n'] );
            $i18nClassName = $className.'_i18n';
            $this->classes[$className]['_attributes']['isI18N'] = true;
            $this->classes[$className]['_attributes']['i18nTable'] = $className.'_i18n';
            $this->classes[$i18nClassName]['id']['type'] = 'integer';
            $this->classes[$i18nClassName]['id']['required'] = true;
            $this->classes[$i18nClassName]['id']['primaryKey'] = true;
            $this->classes[$i18nClassName]['id']['foreignTable'] = $className;
            $this->classes[$i18nClassName]['id']['foreignReference'] = 'id';
            $this->classes[$i18nClassName]['id']['onDelete'] = 'cascade';
            $this->classes[$i18nClassName]['culture']['type'] = 'varchar';
            $this->classes[$i18nClassName]['culture']['size'] = 7;
            $this->classes[$i18nClassName]['culture']['required'] = true;
            $this->classes[$i18nClassName]['culture']['primaryKey'] = true;
            $this->classes[$i18nClassName]['culture']['isCulture'] = true;
            $this->classes[$i18nClassName][$attributeName]['type'] = $type;
            if ( isset( $values['slug'] ) ) {
                unset( $values['slug'] );
                $values['primaryString'] = true;
                $this->classes[$i18nClassName]['_propel_behaviors']['sluggable'] = array(
                    'slug_column' => 'slug_column',
                    'replacement' => '-'
                );
                $this->classes[$i18nClassName]['slug_column']['type'] = 'varchar';
                $this->classes[$i18nClassName]['slug_column']['size'] = 255;
            }
            foreach ( $values as $name => $value ) {
                $this->classes[$i18nClassName][$attributeName][$name] = $value;
            }
        }
        else {
            $this->classes[$className][$attributeName]['type'] = $type;
            if ( isset( $values['slug'] ) ) {
                unset( $values['slug'] );
                $values['primaryString'] = true;
                $this->classes[$className]['_propel_behaviors']['sluggable'] = array(
                    'slug_column' => 'slug_column',
                    'replacement' => '-'
                );
                $this->classes[$className]['slug_column']['type'] = 'varchar';
                $this->classes[$className]['slug_column']['size'] = 255;
            }
            foreach ( $values as $name => $value ) {
                $this->classes[$className][$attributeName][$name] = $value;
            }
        }
    }

    protected function parseOperation($className, Composite $operation) {
        $o = $operation->getAttributes();
        $operationName = $o['name']->getValue();
        switch( $operationName ) {
            case 'Versionable':
                $this->classes[$className]['version']['type'] = 'integer';
                $this->classes[$className]['_propel_behaviors']['versionable'] = array('version_column'=>'version');
                break;
            case 'Timestampable':
                $this->classes[$className]['created_at']['type'] = 'timestamp';
                $this->classes[$className]['updated_at']['type'] = 'timestamp';
                break;
            case 'NestedSet':
                //$this->classes[$className]['_attributes']['treeMode'] = 'NestedSet';
                $this->classes[$className]['tree_left']['type'] = 'integer';
                //$this->classes[$className]['tree_left']['nestedSetLeftKey'] = true;
                $this->classes[$className]['tree_right']['type'] = 'integer';
                //$this->classes[$className]['tree_right']['nestedSetRightKey'] = true;
                $this->classes[$className]['tree_level']['type'] = 'integer';
                $this->classes[$className]['_propel_behaviors']['nested_set'] = array(
                    'left_column' => 'tree_left',
                    'right_column' => 'tree_right',
                    'level_column' => 'tree_level',
                );
                break;
            case 'Searchable':
                break;
            case 'Sortable':
                $this->classes[$className]['order']['type'] = 'integer';
                $options = array(
                    'rank_column' => 'order'
                );
                $values = $o['parameters']->getValue() ? $o['parameters']->getValue() : array();
                foreach ( $values as $param) {
                    $p = $param->getAttributes();
                    $options['scope_column'] = $p['name']->getValue();
                    $options['use_scope'] = true;
                }
                $this->classes[$className]['_propel_behaviors']['sortable'] = $options;
                break;
            case 'Geocodable':
                $geocodableAddressFields = array();
                foreach ($o['parameters']->getValue() as $param) {
                    $p = $param->getAttributes();
                    $geocodableAddressFields[] =  $p['name']->getValue();
                }
                $this->classes[$className]['_propel_behaviors']['geocodable'] = array(
                    'geocode_address'=>true,
                    'address_columns'=>implode(',',$geocodableAddressFields)
                );
                break;
            case 'SoftDelete':
                $this->classes[$className]['deleted_at']['type'] = 'timestamp';
                break;
            case 'Unique':
                $name = 'uniq_'.(isset($this->classes[$className]['_uniques']) ? count($this->classes[$className]['_uniques']) : 0);
                foreach ($o['parameters']->getValue() as $param) {
                    $p = $param->getAttributes();
                    $this->classes[$className]['_uniques'][$name][] = $p['name']->getValue();
                }
                break;
        }
    }

    protected function parseClass(Object $o) {
        $oa = $o->getAttributes();
        $className = Container::underscore( $oa['name']->getValue() );
        $this->id2classes[$o->getId()] = $className;
        $description = $oa['comment']->getValue();
        $this->classes[$className]['_attributes']['description'] = $description;
        if ( isset(  $oa['stereotype'] ) && $oa['stereotype']->getValue() ) {
            $this->classes[$className]['_attributes']['phpName'] = $oa['stereotype']->getValue();
        }
        $this->classes[$className]['id']['type'] = 'integer';
        $this->classes[$className]['id']['required'] = true;
        $this->classes[$className]['id']['primaryKey'] = true;
        $this->classes[$className]['id']['autoIncrement'] = true;
        if ( isset( $oa['attributes']) ) {
            $attributes = $oa['attributes']->getValue();
            if ( ! empty($attributes) ) {
                foreach( $attributes as $attribute ) {
                    $this->parseAttribute($className,$attribute);
                }
            }
        }
        if ( isset($oa['operations']) ) {
            $operations = $oa['operations']->getValue();
            if ( ! empty($operations) ) {
                foreach( $operations as $operation ) {
                    $this->parseOperation($className,$operation);
                }
            }
        }
    }

    protected function parseGeneralization(Object $o) {
        $connection = $o->getConnection();
        $oa = $o->getAttributes();
        $fromClassName = isset($this->id2classes[$connection->getFrom()]) ? $this->id2classes[$connection->getFrom()] : $oa['name']->getValue();
        $toClassName = isset($this->id2classes[$connection->getTo()]) ? $this->id2classes[$connection->getTo()] : $oa['name']->getValue();
        if ( isset( $this->classes[$toClassName]['id']['autoIncrement'] ) ) {
            unset( $this->classes[$toClassName]['id']['autoIncrement'] );
        }
        $this->classes[$toClassName]['id']['foreignTable'] = $fromClassName;
        $this->classes[$toClassName]['id']['foreignReference'] = 'id';
        $this->classes[$toClassName]['id']['onDelete'] = 'cascade';
        // concrete_inheritance / class_table_inheritance / single_inheritance
        $this->classes[$toClassName]['_propel_behaviors']['class_table_inheritance'] = array(
            'extends' => $fromClassName
        );
        if ( isset( $this->classes[$toClassName]['_attributes']['isI18N'] ) && isset( $this->classes[$fromClassName]['_attributes']['isI18N'] ) ) {
            $toI18nClassName = $this->classes[$toClassName]['_attributes']['i18nTable'];
            $fromI18nClassName = $this->classes[$fromClassName]['_attributes']['i18nTable'];
            $this->classes[$toI18nClassName]['_propel_behaviors']['class_table_inheritance'] = array(
                'extends' => $fromI18nClassName,
                'descendant_column' => 'descendant_class_i18n'
            );
            $fkey = array(
                'foreignTable' => $fromI18nClassName,
                'onDelete' => 'cascade',
                'references' => array(
                    array('local'=>'id','foreign'=>'id'),
                    array('local'=>'culture','foreign'=>'culture')
                )
            );
            $this->classes[$toI18nClassName]['_foreignKeys'][] = $fkey;
        }
    }

    protected function parseAssociationValues(Object $o) {
        $oa = $o->getAttributes();
        $direction = $oa['direction']->getValue();
        $connection = $o->getConnection();
        $associationName = $oa['name']->getValue();
        $type = $oa['assoc_type']->getValue();
        $multiplicities = array();
        foreach( array('a','b') as $extremity ) {
            $match = array();
            if ( preg_match('|([\*0-9]+)[ \.]*([\*n0-9]*)|ims',$oa['multipicity_'.$extremity]->getValue(),$match) ) {
                if ( $match[1] == '*' ) {
                    $multiplicities[$extremity] = array(
                            'min'=>0
                    );
                }
                else {
                    $multiplicities[$extremity] = array(
                            'min'=> (int) $match[1]
                    );
                }
                if ( strlen($match[2]) == 0 ) {
                    if ( $match[1] != '*' ) {
                        $multiplicities[$extremity]['max'] = $multiplicities[$extremity]['min'];
                    }
                }
                else if ( ! in_array( $match[2], array('*','n') ) ) {
                    $multiplicities[$extremity]['max'] = (int) $match[2];
                }
            }
            else {
                $multiplicities[$extremity] = array('min'=>( $type == 2 ) ? 1 : 0,'max'=>1);
            }

        }
        switch( $direction ) {
            default:
            case 1:
                $fromClassName = isset( $this->id2classes[$connection->getFrom()] ) ? $this->id2classes[$connection->getFrom()] : $oa['role_a']->getValue();
                $toClassName = isset( $this->id2classes[$connection->getTo()] ) ? $this->id2classes[$connection->getTo()] : $oa['role_b']->getValue();
                $toMultiplicity = $multiplicities['a'];
                $fromMultiplicity = $multiplicities['b'];
                break;
            case 2:
                $toClassName = isset( $this->id2classes[$connection->getFrom()] ) ? $this->id2classes[$connection->getFrom()] : $oa['role_a']->getValue();
                $fromClassName = isset( $this->id2classes[$connection->getTo()] ) ? $this->id2classes[$connection->getTo()] : $oa['role_b']->getValue();
                $fromMultiplicity = $multiplicities['a'];
                $toMultiplicity = $multiplicities['b'];
                break;
        }
        if ( ! $associationName ) {
            if ( ( ! isset( $fromMultiplicity['max'] ) ) || ( $fromMultiplicity['max'] > 1 ) ) {
                if ( $fromClassName > $toClassName ) {
                    $associationName = $toClassName.'_'.$fromClassName;
                }
                else {
                    $associationName = $fromClassName.'_'.$toClassName;
                }
            }
            else {
                $associationName = $toClassName;
            }
        }
        return array($associationName,$fromClassName,$toClassName,$fromMultiplicity,$toMultiplicity);
    }

    protected function parseAssociation(Object $o) {
        list($associationName,$fromClassName,$toClassName,$fromMultiplicity,$toMultiplicity) = $this->parseAssociationValues($o);
        $associationName = strtr( Container::underscore( $associationName ), array('.'=>'_') );
        $fromClassName = strtr( Container::underscore( $fromClassName ), array('.'=>'_') );
        $toClassName = strtr( Container::underscore( $toClassName ), array('.'=>'_') );
        if (
                ( ! isset($fromMultiplicity['max']) || $fromMultiplicity['max'] > 1 ) &&
                ( ! isset($toMultiplicity['max']) || $toMultiplicity['max'] > 1 )
        ) {
            $this->classes[$associationName]['id']['type'] = 'integer';
            $this->classes[$associationName]['id']['required'] = true;
            $this->classes[$associationName]['id']['primaryKey'] = true;
            $this->classes[$associationName]['id']['autoIncrement'] = true;
            $this->classes[$associationName][$fromClassName.'_id']['type'] = 'integer';
            $this->classes[$associationName][$fromClassName.'_id']['required'] = true;
            $this->classes[$associationName][$fromClassName.'_id']['foreignTable'] = $fromClassName;
            $this->classes[$associationName][$fromClassName.'_id']['foreignReference'] = 'id';
            $this->classes[$associationName][$fromClassName.'_id']['onDelete'] = 'cascade';
            $this->classes[$associationName][$toClassName.'_id']['type'] = 'integer';
            $this->classes[$associationName][$toClassName.'_id']['required'] = true;
            $this->classes[$associationName][$toClassName.'_id']['foreignTable'] = $toClassName;
            $this->classes[$associationName][$toClassName.'_id']['foreignReference'] = 'id';
            $this->classes[$associationName][$toClassName.'_id']['onDelete'] = 'cascade';
            $this->classes[$associationName]['_attributes']['isCrossRef'] = true;
            $this->classes[$associationName]['_attributes']['phpName'] = Container::camelize($associationName);
        }
        else {
            $this->classes[$fromClassName][$associationName.'_id']['type'] = 'integer';
            if ( $toMultiplicity['min'] ) {
                $this->classes[$fromClassName][$associationName.'_id']['required'] = true;
            }
            $this->classes[$fromClassName][$associationName.'_id']['foreignTable'] = $toClassName;
            $this->classes[$fromClassName][$associationName.'_id']['foreignReference'] = 'id';
            $this->classes[$fromClassName][$associationName.'_id']['onDelete'] = $toMultiplicity['min']?'cascade':'setnull';
        }
    }

}