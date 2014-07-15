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

class Connection {

	protected $element;
	protected $from;
	protected $to;

	public function __construct(\DOMElement $element) {
		$this->element = $element;
		foreach( $element->childNodes as $child ) {
			if ( $child instanceof \DOMElement && $child->nodeName == 'dia:connection' ) {
				$t = $child->getAttribute('to');
				if ( $child->getAttribute('handle') ) {
					$this->to= $t;
				}
				else {
					$this->from = $t;
				}
			}
		}
	}

	public function getFrom() {
		return $this->from;
	}

	public function getTo() {
		return $this->to;
	}
}