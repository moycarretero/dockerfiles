<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\SerializedName;

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * @ORM\Entity(repositoryClass="App\Repository\BreadcrumbRepository")
 * @ORM\Table(name="EDITOR_MIGAS_PUBLICACIONES")
 */
class Breadcrumb {

    /**
     * @var integer $id
     *
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="SEQUENCE")
     * @ORM\SequenceGenerator(sequenceName="PUBLICACIONES_SEQ", initialValue=1, allocationSize=1)
     * @ORM\Column(name="id", type="integer")
     *
     * @SerializedName("id")
     *
     */
    protected $id;

    /**
     * @var string $nombre
     *
     * @ORM\Column(name="nombre", type="string")
     * @SerializedName("nombre")
     *
     */
    protected $nombre;

    /**
     * @var string $path
     *
     * @ORM\Column(name="path", type="string")
     * @SerializedName("path")
     */
    protected $path;

    /**
     * @var integer $idRaiz
     *
     * @ORM\Column(name="ID_RAIZ", type="integer")
     */
    protected $idRaiz;

    /**
     * @var integer $idPlantilla
     *
     * @ORM\Column(name="ID_PLANTILLA", type="integer")
     */
    protected $idPlantilla;

    /**
     * @var string $outputs
     *
     * @ORM\Column(name="outputs", type="string")
     */
    protected $outputs;

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

    /**
     * Set path
     *
     * @param string $path
     * @return Breadcrumb
     */
    public function setPath($path)
    {
        $this->path = $path;
        return $this;
    }

    /**
     * Get path
     *
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Set idRaiz
     *
     * @param integer $idRaiz
     * @return Breadcrumb
     */
    public function setIdRaiz($idRaiz)
    {
        $this->idRaiz = $idRaiz;
        return $this;
    }

    /**
     * Get idRaiz
     *
     * @return integer
     */
    public function getIdRaiz()
    {
        return $this->idRaiz;
    }

    /**
     * Set idPlantilla
     *
     * @param integer $idPlantilla
     * @return Breadcrumb
     */
    public function setIdPlantilla($idPlantilla)
    {
        $this->idPlantilla = $idPlantilla;
        return $this;
    }

    /**
     * Get idPlantilla
     *
     * @return integer
     */
    public function getIdPlantilla()
    {
        return $this->idPlantilla;
    }

    /**
     * Set outputs
     *
     * @param array $outputs
     * @return Breadcrumb
     */
    public function setOutputs($outputs)
    {
        $this->outputs = $outputs;
        return $this;
    }

    /**
     * Get outputs
     *
     * @return array
     */
    public function getOutputs()
    {
        return $this->outputs;
    }

}
