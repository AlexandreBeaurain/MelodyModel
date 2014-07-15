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

class Object extends Composite {

	protected $id;

	public function __construct(\DOMElement $element) {
		if ( $element->hasAttribute('id') ) {
			$this->id = $element->getAttribute('id');
		}
		parent::__construct($element);
	}

	public function getId() {
		return $this->id;
	}

}