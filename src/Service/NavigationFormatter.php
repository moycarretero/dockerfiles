<?php

namespace App\Service;

use App\Entity\Format;
use App\Entity\Output;

class NavigationFormatter
{
    private $formats = array();

    public function getFormats()
    {
        return $this->formats;
    }

    public function addFormat($formatName, $outputName, $template, $suffix)
    {
        $output = new Output($outputName, $template, $suffix);
        $this->addOutputToFormat($formatName, $output);
    }

    private function addOutputToFormat($formatName, Output $output)
    {
        $added = false;

        foreach ($this->formats as $format) {
            if ($format->getName() == $formatName) {
                $format->addOutput($output);
                $added = true;
            }
        }

        if (false == $added) {
            $format = new Format($formatName);
            $format->addOutput($output);
            $this->formats[] = $format;
        }
    }

    public function getSuffix($formatName, $outputName)
    {
        foreach ($this->formats as $format) {
            if ($format->getName() == $formatName) {
                foreach ($format->getOutputs() as $output) {
                    if ($output->getName() == $outputName) {
                        return $output->getSuffix();
                    }
                }
            }
        }

        return null;
    }
}
