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
        $documents = array();
        $entities = array();
        $mappings = array();
        foreach( $this->schema as $entityName => $entityConfiguration ) {
            if ( isset( $entityConfiguration['_attributes']['isCrossRef'] ) ) {
                continue;
            }
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
            $documents[$entityName] = $doc;
            $entities[$entityName] = $entity;
            $mappings[$entityName] = $mapping;
        }
        foreach( $this->schema as $entityName => $entityConfiguration ) {
            if ( isset( $entityConfiguration['_attributes']['isCrossRef'] ) ) {
                continue;
            }
            $doc = $documents[$entityName];
            $entity = $entities[$entityName];
            $mapping = $mappings[$entityName];
            $uniqueContraints = null;
            $indexes = null;
            $behaviors = null;
            if ( isset($entityConfiguration['_behaviors']) ) {
                $behaviors = $entityConfiguration['_behaviors'];
                unset($entityConfiguration['_behaviors']);
            }
            foreach( $entityConfiguration as $columnName => $columnConfiguration ) {
                if ( $columnName == '_attributes' ) {
                    foreach ( $columnConfiguration as $attributeName => $attributeValue ) {
                        if ( $attributeName == 'phpName' ) {
                            $entity->setAttribute('table', $attributeValue);
                        }
                        else {
                            $entity->setAttribute($attributeName,$attributeValue);
                        }
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
                    if ( !isset($columnConfiguration['nullable']) && !isset($columnConfiguration['foreignTable']) ) {
                        $columnConfiguration['nullable'] = 'true';
                    }
                    if ( isset($columnConfiguration['required']) && ( $columnConfiguration['required'] == 'false' ) ) {
                        $columnConfiguration['nullable'] = 'true';
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
                            if ( $columnName != 'id' ) {
                                $tagName = 'many-to-one';
                                $targetEntityName = Container::camelize($attributeValue);
                                $fieldName = strpos($attributeValue,'\\') !== false ? substr( $attributeValue, strrpos($attributeValue,'\\')+1 ) : $attributeValue;
                                $distantFieldName = strpos($entityName,'\\') !== false ? substr( $entityName, strrpos($entityName,'\\')+1 ) : $entityName;
                                $pluralDistantFieldName = \Doctrine\Common\Inflector\Inflector::pluralize($distantFieldName);
                                $foreignKey = $doc->createElement($tagName);
                                $entity->appendChild( $doc->createTextNode("\n\t\t") );
                                $entity->appendChild( $foreignKey );
                                $foreignKey->setAttribute('field', $fieldName );
                                $foreignKey->setAttribute('target-entity', $targetEntityName );
                                if ( isset($documents[$attributeValue]) ) {
                                    $foreignKey->setAttribute('inversed-by', $pluralDistantFieldName );
                                }
                                $foreignKey->appendChild( $doc->createTextNode("\n\t\t\t") );
                                $joinColumn = $doc->createElement('join-column');
                                $joinColumn->setAttribute('name',$columnName);
                                $joinColumn->setAttribute('referenced-column-name',$columnConfiguration['foreignReference']);
                                $joinColumn->setAttribute('on-delete',strtr($columnConfiguration['onDelete'],array('setnull'=>'SET NULL')));
                                $foreignKey->appendChild( $joinColumn );
                                $foreignKey->appendChild( $doc->createTextNode("\n\t\t") );
                                if ( isset($documents[$attributeValue]) ) {
                                    $tagName = 'one-to-many';
                                    $targetDoc = $documents[$attributeValue];
                                    $targetEntity = $entities[$attributeValue];
                                    $foreignKey = $targetDoc->createElement($tagName);
                                    $targetEntity->appendChild( $foreignKey );
                                    $targetEntity->appendChild( $targetDoc->createTextNode("\n\t\t") );
                                    $cascade = $targetDoc->createElement('cascade');
                                    $foreignKey->appendChild($cascade);
                                    $cascadePersist = $targetDoc->createElement('cascade-persist');
                                    $cascade->appendChild($cascadePersist);
                                    $foreignKey->setAttribute('field', $pluralDistantFieldName );
                                    $foreignKey->setAttribute('target-entity', Container::camelize($entityName) );
                                    $foreignKey->setAttribute('mapped-by', $fieldName );
                                }
                            }
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
            foreach( $this->schema as $entityName2 => $entityConfiguration2 ) {
                if ( isset( $entityConfiguration2['_attributes']['isCrossRef'] ) ) {
                    $fTable = null;
                    $fKey = null;
                    $lKey = null;
                    foreach (  $entityConfiguration2 as $columnName2 => $columnConfiguration2 ) {
                        if ( isset( $columnConfiguration2['foreignTable'] ) ) {
                            if ( $columnConfiguration2['foreignTable'] == $entityName ) {
                                $lKey = $columnName2;
                            }
                            else {
                                $fTable = $columnConfiguration2['foreignTable'];
                                $fKey = $columnName2;
                            }
                        }
                    }
                    if ( $lKey ) {
                        $foreignKey = $doc->createElement('many-to-many');
                        $entity->appendChild( $doc->createTextNode("\n\t\t") );
                        $entity->appendChild( $foreignKey );
                        $fieldName = strpos($fTable,'\\') !== false ? substr( $fTable, strrpos($fTable,'\\')+1 ) : $fTable;
                        $pluralFieldName = \Doctrine\Common\Inflector\Inflector::pluralize($fieldName);
                        $distantFieldName = strpos($entityName2,'\\') !== false ? substr( $entityName2, strrpos($entityName2,'\\')+1 ) : $entityName2;
                        $pluralDistantFieldName = \Doctrine\Common\Inflector\Inflector::pluralize($distantFieldName);
                        $foreignKey->setAttribute('field', $pluralFieldName );
                        $foreignKey->setAttribute('target-entity', Container::camelize($fTable) );
                        if ( $lKey > $fKey ) {
                            $foreignKey->setAttribute('inversed-by', $pluralDistantFieldName );
                            $foreignKey->appendChild( $doc->createTextNode("\n\t\t\t") );
                            $cascade = $doc->createElement('cascade');
                            $cascadeAll = $doc->createElement('cascade-all');
                            $foreignKey->appendChild( $cascade );
                            $cascade->appendChild( $cascadeAll );
                            $foreignKey->appendChild( $doc->createTextNode("\n\t\t\t") );
                            $joinTable = $doc->createElement('join-table');
                            $joinTable->setAttribute('name',$entityName2);
                            $foreignKey->appendChild( $joinTable );
                            $joinTable->appendChild( $doc->createTextNode("\n\t\t\t\t") );
                            $joinColumns = $doc->createElement('join-columns');
                            $joinColumn = $doc->createElement('join-column');
                            $joinColumn->setAttribute('name',$lKey);
                            $joinColumn->setAttribute('referenced-column-name','id');
                            $joinColumn->setAttribute('on-delete','cascade');
                            $joinColumns->appendChild( $joinColumn );
                            $joinTable->appendChild( $joinColumns );
                            $joinTable->appendChild( $doc->createTextNode("\n\t\t\t\t") );
                            $joinColumns = $doc->createElement('inverse-join-columns');
                            $joinColumn = $doc->createElement('join-column');
                            $joinColumn->setAttribute('name',$fKey);
                            $joinColumn->setAttribute('referenced-column-name','id');
                            $joinColumn->setAttribute('on-delete','cascade');
                            $joinColumns->appendChild( $joinColumn );
                            $joinTable->appendChild( $joinColumns );
                            $joinTable->appendChild( $doc->createTextNode("\n\t\t\t") );
                        }
                        else {
                            $foreignKey->setAttribute('mapped-by', $pluralDistantFieldName );
                        }
                        $foreignKey->appendChild( $doc->createTextNode("\n\t\t") );
                    }
                }
            }
            if ( !empty( $behaviors ) ) {
                foreach ( $behaviors as $behaviorName => $behaviorConfiguration ) {
                    switch( $behaviorName ) {
                        case 'geocodable':
                            break;
                        case 'class_table_inheritance':
                            $entity->setAttribute('inheritance-type','JOINED');
                            break;
                        case 'nested_set':
                            $xpath = new \DOMXPath($doc);
                            foreach( $behaviorConfiguration as $behaviorParameterName => $behaviorParameterValue ) {
                                foreach( $xpath->evaluate('//field[@name="'.$behaviorParameterValue.'"]') as $column ) {
                                    $behavior = $doc->createElement('gedmo:tree-'.substr( $behaviorParameterName, 0, strpos($behaviorParameterName,'_') ) );
                                    $column->appendChild( $doc->createTextNode("\n\t\t\t") );
                                    $column->appendChild( $behavior );
                                    $column->appendChild( $doc->createTextNode("\n\t\t") );
                                }
                            }
                            $behavior = $doc->createElement('gedmo:tree');
                            $behavior->setAttribute('type','nested');
                            $entity->appendChild( $doc->createTextNode("\n\t\t") );
                            $entity->appendChild( $behavior );
                            break;
                    }
                }
            }
            $entity->appendChild( $doc->createTextNode("\n\t") );
            $mapping->appendChild( $doc->createTextNode("\n") );
        }
        foreach( $documents as $entityName => $doc ) {
            $doc->save($outputDirectory.'/'.Container::camelize($entityName).'.orm.xml');
        }
    }
}