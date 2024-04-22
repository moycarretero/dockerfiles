<?php

namespace App\Entity;

use Symfony\Component\Serializer\Annotation as Serial;
use Doctrine\ORM\Mapping as ORM;

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * @ORM\Entity(repositoryClass="App\Repository\TreeNodeRepository")
 * @ORM\Table(name="EDITOR_MIGAS_NODOS")
 */
class TreeNode
{

    /**
     * @var integer $id
     *
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="SEQUENCE")
     * @ORM\SequenceGenerator(sequenceName="NODOS_SEQ", initialValue=1, allocationSize=1)
     * @ORM\Column(name="id", type="integer")
     * @Serial\SerializedName("id")
     */
    protected $id;

    /**
     * @var string $nombre
     *
     * @ORM\Column(name="nombre", type="string")
     * @Serial\SerializedName("nombre")
     */
    protected $nombre;

    /**
     * @var string $url
     *
     * @ORM\Column(name="url", type="string")
     * @Serial\SerializedName("url")
     */
    protected $url;

    /**
     * @var integer $visible
     *
     * @ORM\Column(name="visible", type="integer")
     * @Serial\SerializedName("visible")
     */
    protected $visible;

    /**
     * @var integer $padreId
     *
     * @ORM\Column(name="padre_id", type="integer")
     * @Serial\SerializedName("padreId")
     */
    protected $padreId;

    /**
     * @var integer $orden
     *
     * @ORM\Column(name="orden", type="integer")
     * @Serial\SerializedName("orden")
     */
    protected $orden;

    /**
     * @var string $portalId
     *
     * @ORM\Column(name="portal_id", type="string")
     * @Serial\SerializedName("portalId")
     */
    protected $portalId;

    /**
     * @var string $sectionId
     *
     * @ORM\Column(name="section_id", type="string")
     * @Serial\SerializedName("sectionId")
     */
    protected $sectionId;

    /**
     * Set nombre
     *
     * @param string $nombre
     * @return TreeNode
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
     * Set url
     *
     * @param string $url
     * @return TreeNode
     */
    public function setUrl($url)
    {
        $this->url = $url;
        return $this;
    }

    /**
     * Get url
     *
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Set visible
     *
     * @param integer $visible
     * @return TreeNode
     */
    public function setVisible($visible)
    {
        $this->visible = $visible;
        return $this;
    }

    /**
     * Get visible
     *
     * @return integer
     */
    public function getVisible()
    {
        return $this->visible;
    }

    /**
     * Set padreId
     *
     * @param integer $padreId
     * @return TreeNode
     */
    public function setPadreId($padreId)
    {
        $this->padreId = $padreId;
        return $this;
    }

    /**
     * Get padreId
     *
     * @return integer
     */
    public function getPadreId()
    {
        return $this->padreId;
    }

    /**
     * Set orden
     *
     * @param integer $orden
     * @return TreeNode
     */
    public function setOrden($orden)
    {
        $this->orden = $orden;
        return $this;
    }

    /**
     * Get orden
     *
     * @return integer
     */
    public function getOrden()
    {
        return $this->orden;
    }


    /**
     * Set id
     *
     * @param integer $id
     * @return TreeNode
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
     * Set portalId
     *
     * @param string portal
     * @return TreeNode
     */
    public function setPortalId($portalId)
    {
        $this->portalId = $portalId;
        return $this;
    }

    /**
     * Get portalId
     *
     * @return string
     */
    public function getPortalId()
    {
        return $this->portalId;
    }

    /**
     * Set sectionId
     *
     * @param string sectionId
     * @return TreeNode
     */
    public function setSectionId($sectionId)
    {
        $this->sectionId = $sectionId;
        return $this;
    }

    /**
     * Get sectionId
     *
     * @return string
     */
    public function getSectionId()
    {
        return $this->sectionId;
    }

}
