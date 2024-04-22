<?php

namespace App\Entity;

class Output
{
    protected $name;

    protected $template;

    protected $suffix;


    public function __construct($name, $template, $suffix)
    {
        $this->name     = $name;
        $this->template = $template;
        $this->suffix   = $suffix;
    }

    /**
     * Set name
     *
     * @param string $name
     * @return Format
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set template
     *
     * @param string $template
     * @return Format
     */
    public function setTemplate($template)
    {
        $this->template = $template;
        return $this;
    }

    /**
     * Get template
     *
     * @return string
     */
    public function getTemplate()
    {
        return $this->template;
    }

    /**
     * Set suffix
     *
     * @param integer $suffix
     * @return Format
     */
    public function setSuffix($suffix)
    {
        $this->suffix = $suffix;
        return $this;
    }

    /**
     * Get suffix
     *
     * @return integer
     */
    public function getSuffix()
    {
        return $this->suffix;
    }
}
