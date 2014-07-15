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

namespace Melody\ModelBundle;

/**
 * Abstract class for generating orm configuration.
 */
abstract class Orm
{
    protected $schema;
    protected $namespace;
    protected $repository;

    public function __construct($schema,$namespace,$repository) {
        $this->schema = $schema;
        $this->namespace = $namespace;
        $this->repository = $repository;
    }

    abstract public function writeConfiguration($outputDirectory);
}