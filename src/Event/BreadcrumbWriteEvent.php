<?php

namespace App\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * BreadcrumbWriteEvent.
 *
 * Event launched whenever a breadcrumb is written.
 *
 * @author Jose Manuel García Maleno <josemanuel.garcia@unidadeditorial.es>
 */
class BreadcrumbWriteEvent extends Event
{
    private $path;
    private $children;

    /**
     * __construct.
     *
     * @param string $path Path to the breadcrumb file.
     * @param array  $children Associative array with the breadcrumb children nodes and their urls.
     */
    public function __construct($path, $children)
    {
        $this->path = $path;
        $this->children = $children;
    }

    /**
     * getPath.
     *
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * getChildren.
     *
     * @return array
     */
    public function getChildren()
    {
        return $this->children;
    }
}
