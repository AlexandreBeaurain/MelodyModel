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

class Attribute {

	protected $element;
	protected $name;
	protected $value;

	public function __construct(\DOMElement $element) {
		$this->element = $element;
		$this->name = $element->getAttribute('name');
		foreach( $element->childNodes as $child ) {
			if ( $child instanceof \DOMElement ) {
				switch ( $child->nodeName ) {
					case 'dia:enum':
						$this->value = (int) $child->getAttribute('val');
						break;
					case 'dia:boolean':
						$this->value = (boolean) $child->getAttribute('val');
						break;
					case 'dia:string':
						$this->value = trim($child->firstChild->nodeValue,'#');
						break;
					case 'dia:composite':
						if ( ! is_array($this->value) ) {
							$this->value = array();
						}
						$this->value[] = new Composite($child);
						break;
					default:
						$this->value = $child->getAttribute('val');
				}
			}
		}
	}

	public function getName() {
		return $this->name;
	}

	public function getValue() {
		return $this->value;
	}

}