<?php

namespace App\Entity;

use Symfony\Component\Serializer\Annotation as Serial;
use Doctrine\ORM\Mapping as ORM;

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * @ORM\Entity(repositoryClass="App\Repository\TemplatesRepository")
 * @ORM\Table(name="EDITOR_MIGAS_PLANTILLAS")
 */
class Templates {

    /**
     * @var integer $id
     *
     * @ORM\Id
     * @ORM\Column(name="id", type="integer")
     *
     * @Serial\SerializedName("id")
     */
    protected $id;

    /**
     * @var string $nombre
     *
     * @ORM\Column(name="nombre", type="string")
     * @Serial\SerializedName("nombre")
     *
     */
    protected $nombre;

    /**
     * Set id
     *
     * @param integer $id
     * @return Breadcrumb
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set nombre
     *
     * @param string $nombre
     * @return Breadcrumb
     */
    public function setNombre($nombre)
    {
        $this->nombre = $nombre;
        return $this;
    }

    /**
     * Get nombre
     *
     * @return string
     */
    public function getNombre()
    {
        return $this->nombre;
    }

}
