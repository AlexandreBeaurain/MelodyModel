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

namespace Melody\ModelBundle\Orm;

use Melody\ModelBundle\Orm;
use Symfony\Component\DependencyInjection\Container;

/**
 * Class for generating Doctrine configuration.
 */
class Doctrine extends Orm
{
    public function writeConfiguration($outputDirectory) {
        foreach( $this->schema as $entityName => $entityConfiguration ) {
            $doc = new \DOMDocument('1.0', 'UTF-8');
            $mapping = $doc->createElement('doctrine-mapping');
            $mapping->setAttribute('xmlns', 'http://doctrine-project.org/schemas/orm/doctrine-mapping');
            $mapping->setAttribute('xmlns:xsi','http://www.w3.org/2001/XMLSchema-instance');
            $mapping->setAttribute('xmlns:gedmo','http://gediminasm.org/schemas/orm/doctrine-extensions-mapping');
            $mapping->setAttribute('xsi:schemaLocation','http://doctrine-project.org/schemas/orm/doctrine-mapping http://doctrine-project.org/schemas/orm/doctrine-mapping.xsd');
            $doc->appendChild($mapping);
            $entityName = strtr( $entityName, array('.'=>'_') );
            $entity = $doc->createElement('entity');
            $entity->setAttribute('name', $this->namespace.'\\Entity\\'.Container::camelize($entityName));
            $entity->setAttribute('table', $entityName);
            $mapping->appendChild( $doc->createTextNode("\n\t") );
            $mapping->appendChild( $entity );
            $uniqueContraints = null;
            $indexes = null;
            foreach( $entityConfiguration as $columnName => $columnConfiguration ) {
                if ( $columnName == '_behaviors' ) {
                    foreach ( $columnConfiguration as $behaviorName => $behaviorConfiguration ) {
                        $behavior = $doc->createElement('behavior');
                        $behavior->setAttribute('name', $behaviorName);
                        $entity->appendChild( $doc->createTextNode("\n\t\t") );
                        $entity->appendChild($behavior);
                        foreach( $behaviorConfiguration as $behaviorParameterName => $behaviorParameterValue) {
                            $behavior->appendChild( $doc->createTextNode("\n\t\t\t") );
                            $behaviorParameter = $doc->createElement('parameter');
                            $behaviorParameter->setAttribute('name', $behaviorParameterName);
                            $behaviorParameter->setAttribute('value', $behaviorParameterValue);
                            $behavior->appendChild($behaviorParameter);
                        }
                        $behavior->appendChild( $doc->createTextNode("\n\t\t") );
                    }
                }
                else if ( $columnName == '_attributes' ) {
                    foreach ( $columnConfiguration as $attributeName => $attributeValue ) {
                        $entity->setAttribute($attributeName,$attributeValue);
                    }
                }
                else {
                    $column = $doc->createElement($columnName == 'id' ? 'id' : 'field');
                    $column->setAttribute('name', $columnName);
                    $entity->appendChild( $doc->createTextNode("\n\t\t") );
                    $entity->appendChild( $column );
                    if ( in_array( $columnName, array('created_at','updated_at') ) ) {
                        $behavior = $doc->createElement('gedmo:timestampable');
                        $behavior->setAttribute('on', $columnName == 'created_at' ? 'create' : 'update' );
                        $column->appendChild( $doc->createTextNode("\n\t\t\t") );
                        $column->appendChild( $behavior );
                        $column->appendChild( $doc->createTextNode("\n\t\t") );
                    }
                    if ( $columnConfiguration['type'] == 'timestamp' ) {
                        $columnConfiguration['type'] = 'datetime';
                    }
                    foreach( $columnConfiguration as $attributeName => $attributeValue ) {
                        if ( $attributeName == 'index' ) {
                            $indexName = $attributeValue == 'unique' ? 'unique-constraint' : 'index';
                            $index = $doc->createElement($indexName);
                            if( $attributeValue == 'unique' ) {
                                if ( ! $uniqueContraints ) {
                                    $uniqueContraints = $doc->createElement('unique-constraints');
                                    $entity->appendChild( $doc->createTextNode("\n\t\t") );
                                    $entity->appendChild( $uniqueContraints );
                                }
                                $indexesNode = $uniqueContraints;
                            }
                            else {
                                if ( ! $indexes ) {
                                    $indexes = $doc->createElement('indexes');
                                    $entity->appendChild( $doc->createTextNode("\n\t\t") );
                                    $entity->appendChild( $indexes );
                                }
                                $indexesNode = $indexes;
                            }
                            $indexesNode->appendChild( $doc->createTextNode("\n\t\t\t") );
                            $indexesNode->appendChild($index);
                            $index->setAttribute('columns',$columnName);
                        }
                        else if ( $attributeName == 'foreignTable' ) {
                            /*
                            $foreignKey = $doc->createElement('foreign-key');
                            $entity->appendChild( $doc->createTextNode("\n\t\t") );
                            $entity->appendChild($foreignKey);
                            $foreignKey->setAttribute('foreignTable', $attributeValue);
                            $foreignKey->setAttribute('onDelete', $columnConfiguration['onDelete']);
                            $foreignKey->appendChild( $doc->createTextNode("\n\t\t\t") );
                            $reference = $doc->createElement('reference');
                            $reference->setAttribute('local', $columnName);
                            $reference->setAttribute('foreign', $columnConfiguration['foreignReference']);
                            $foreignKey->appendChild($reference);
                            $foreignKey->appendChild( $doc->createTextNode("\n\t\t") );
                            */
                        }
                        else if ( $attributeName == 'autoIncrement' ) {
                            $generator = $doc->createElement('generator');
                            $generator->setAttribute('strategy', 'AUTO');
                            $column->appendChild( $doc->createTextNode("\n\t\t\t") );
                            $column->appendChild( $generator );
                            $column->appendChild( $doc->createTextNode("\n\t\t") );
                        }
                        else if ( !in_array($attributeName, array('foreignReference','onDelete') ) ) {
                            $column->setAttribute($attributeName, $attributeValue);
                        }
                    }
                }
            }
            $entity->appendChild( $doc->createTextNode("\n\t") );
            $mapping->appendChild( $doc->createTextNode("\n") );
            $doc->save($outputDirectory.'/'.Container::camelize($entityName).'.orm.xml');
        }
    }
}