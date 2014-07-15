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

/**
 * Class for generating Propel configuration.
 */
class Propel extends Orm
{
    public function writeConfiguration($outputDirectory) {
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $database = $doc->createElement('database');
        $database->setAttribute('name', 'default');
        $database->setAttribute('namespace', $this->namespace.'\\Model');
        $database->setAttribute('defaultIdMethod', 'native');
        $doc->appendChild($database);
        foreach( $this->schema as $tableName => $tableConfiguration ) {
            $tableName = strtr( $tableName, array('.'=>'_') );
            $table = $doc->createElement('table');
            $table->setAttribute('name', $tableName);
            $database->appendChild( $doc->createTextNode("\n\t") );
            $database->appendChild($table);
            foreach( $tableConfiguration as $columnName => $columnConfiguration ) {
                if ( $columnName == '_propel_behaviors' ) {
                    foreach ( $columnConfiguration as $behaviorName => $behaviorConfiguration ) {
                        $behavior = $doc->createElement('behavior');
                        $behavior->setAttribute('name', $behaviorName);
                        $table->appendChild( $doc->createTextNode("\n\t\t") );
                        $table->appendChild($behavior);
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
                        $table->setAttribute($attributeName,$attributeValue);
                    }
                }
                else {
                    $column = $doc->createElement('column');
                    $column->setAttribute('name', $columnName);
                    $table->appendChild( $doc->createTextNode("\n\t\t") );
                    $table->appendChild($column);
                    foreach( $columnConfiguration as $attributeName => $attributeValue ) {
                        if ( $attributeName == 'index' ) {
                            $indexName = $attributeValue == 'unique' ? 'unique' : 'index';
                            $index = $doc->createElement($indexName);
                            $table->appendChild( $doc->createTextNode("\n\t\t") );
                            $table->appendChild($index);
                            $indexColumn = $doc->createElement($indexName.'-column');
                            $indexColumn->setAttribute('name', $columnName);
                            $index->appendChild($indexColumn);
                        }
                        else if ( $attributeName == 'foreignTable' ) {
                            $foreignKey = $doc->createElement('foreign-key');
                            $table->appendChild( $doc->createTextNode("\n\t\t") );
                            $table->appendChild($foreignKey);
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
            $table->appendChild( $doc->createTextNode("\n\t") );
        }
        $database->appendChild( $doc->createTextNode("\n") );
        $doc->save($outputDirectory.'/schema.xml');
    }
}