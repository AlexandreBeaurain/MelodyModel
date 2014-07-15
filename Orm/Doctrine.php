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




        $doc = new \DOMDocument('1.0', 'UTF-8');
        $mapping = $doc->createElement('doctrine-mapping');
        $mapping->setAttribute('xmlns', 'http://doctrine-project.org/schemas/orm/doctrine-mapping');
        $mapping->setAttribute('xmlns:xsi','http://www.w3.org/2001/XMLSchema-instance');
        $mapping->setAttribute('xsi:schemaLocation','http://doctrine-project.org/schemas/orm/doctrine-mapping http://doctrine-project.org/schemas/orm/doctrine-mapping.xsd');
        $doc->appendChild($mapping);
        foreach( $this->schema as $entityName => $entityConfiguration ) {
            $entityName = strtr( $entityName, array('.'=>'_') );
/*
            <entity name="Acme\StoreBundle\Entity\Product" table="product">
            <id name="id" type="integer" column="id">
            <generator strategy="AUTO" />
            </id>
            <field name="name" column="name" type="string" length="100" />
            <field name="price" column="price" type="decimal" scale="2" />
            <field name="description" column="description" type="text" />
            </entity>
            </doctrine-mapping>

*/

            $entity = $doc->createElement('entity');
            $entity->setAttribute('name', $this->namespace.'\\Entity\\'.Container::camelize($entityName));
            $entity->setAttribute('table', $entityName);
            $mapping->appendChild( $doc->createTextNode("\n\t") );
            $mapping->appendChild($entity);
            foreach( $entityConfiguration as $columnName => $columnConfiguration ) {
                if ( $columnName == '_propel_behaviors' ) {
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
                    $column = $doc->createElement('column');
                    $column->setAttribute('name', $columnName);
                    $entity->appendChild( $doc->createTextNode("\n\t\t") );
                    $entity->appendChild($column);
                    foreach( $columnConfiguration as $attributeName => $attributeValue ) {
                        if ( $attributeName == 'index' ) {
                            $indexName = $attributeValue == 'unique' ? 'unique' : 'index';
                            $index = $doc->createElement($indexName);
                            $entity->appendChild( $doc->createTextNode("\n\t\t") );
                            $entity->appendChild($index);
                            $indexColumn = $doc->createElement($indexName.'-column');
                            $indexColumn->setAttribute('name', $columnName);
                            $index->appendChild($indexColumn);
                        }
                        else if ( $attributeName == 'foreignTable' ) {
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
                        }
                        else if ( !in_array($attributeName, array('foreignReference','onDelete') ) ) {
                            $column->setAttribute($attributeName, $attributeValue);
                        }
                    }
                }
            }
            $entity->appendChild( $doc->createTextNode("\n\t") );
        }
        $mapping->appendChild( $doc->createTextNode("\n") );
        $doc->save($outputDirectory.'/schema.xml');
    }
}