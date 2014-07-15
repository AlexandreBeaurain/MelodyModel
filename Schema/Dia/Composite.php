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

namespace Melody\ModelBundle\Schema\Dia;

class Composite {

	protected $element;
	protected $type;
	protected $id;
	protected $attributes = array();
	protected $connection;

	public function __construct(\DOMElement $element) {
		$this->element = $element;
		$this->type = $element->getAttribute('type');
		foreach( $element->childNodes as $child ) {
			if ( $child instanceof \DOMElement ) {
				switch ( $child->nodeName ) {
					case 'dia:attribute':
						$attribute = new Attribute($child);
						$this->attributes[$attribute->getName()] = $attribute;
						break;
					case 'dia:connections':
						$connection = new Connection($child);
						$this->connection = $connection;
						break;
				}
			}
		}
	}

	/**
	 * @return string
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 * @return array
	 */
	public function getAttributes() {
		return $this->attributes;
	}

	/**
	 * @return DiaConnection
	 */
	public function getConnection() {
		return $this->connection;
	}

}