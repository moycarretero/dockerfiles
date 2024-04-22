<?php

namespace App\Entity;

class Format
{
    protected $name;

    protected $outputs;

    public function __construct($name)
    {
        $this->name     = $name;
        $this->outputs = array();
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
     * Set outputs
     *
     * @param string $outputs
     * @return Format
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

    public function addOutput(Output $output)
    {
        $this->outputs[] = $output;
    }
}
