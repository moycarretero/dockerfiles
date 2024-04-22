<?php

namespace App\Entity;

use Symfony\Component\Serializer\Annotation as Serial;
use Doctrine\ORM\Mapping as ORM;


/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * @ORM\Entity(repositoryClass="App\Repository\NodesBreadcrumbsRelationRepository")
 * @ORM\Table(name="EDITOR_MIGAS_RELACIONES")
 * @author jose.serrano
 */
class NodesBreadcrumbsRelation {

    /**
     * @var integer $id
     *
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="SEQUENCE")
     * @ORM\SequenceGenerator(sequenceName="RELACIONES_SEQ", initialValue=1, allocationSize=1)
     * @ORM\Column(name="id", type="integer")
     * @Serial\SerializedName("id")
     */
    protected $id;

    /**
     * @var integer $idNodo
     *
     * @ORM\Column(name="ID_NODO", type="integer")
     */
    protected $idNodo;

    /**
     * @var integer $idPublicacion
     *
     * @ORM\Column(name="ID_PUBLICACION", type="integer")
     */
    protected $idPublicacion;



    /**
     * Set idNodo
     *
     * @param integer $idNodo
     * @return NodesBreadcrumbsRelation
     */
    public function setIdNodo($idNodo)
    {
        $this->idNodo = $idNodo;
        return $this;
    }

    /**
     * Get idNodo
     *
     * @return integer
     */
    public function getIdNodo()
    {
        return $this->idNodo;
    }

    /**
     * Set idPublicacion
     *
     * @param integer $idPublicacion
     * @return NodesBreadcrumbsRelation
     */
    public function setIdPublicacion($idPublicacion)
    {
        $this->idPublicacion = $idPublicacion;
        return $this;
    }

    /**
     * Get idPublicacion
     *
     * @return integer
     */
    public function getIdPublicacion()
    {
        return $this->idPublicacion;
    }

    /**
     * Set id
     *
     * @param integer $id
     * @return NodesBreadcrumbsRelation
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
}
