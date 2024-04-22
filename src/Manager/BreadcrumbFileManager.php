<?php

namespace App\Manager;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOException;
use Psr\Log\LoggerInterface;


class BreadcrumbFileManager
{
    private $breadcrumbsPath;
    private $baseBreadCrumbFileName;
    private $fs;
    private $logger;

    public function __construct($breadcrumbsPath, $baseBreadCrumbFileName, Filesystem $fs, LoggerInterface $logger)
    {
        $this->breadcrumbsPath = $breadcrumbsPath;
        $this->baseBreadCrumbFileName = $baseBreadCrumbFileName;
        $this->fs = $fs;
        $this->logger = $logger;
    }

    /**
     * Elimina de disco el fichero html generado. Se guarda una copia del fichero
     * en el mismo directorio donde estaba. La copia del fichero se llamará igual
     * que la miga pero con .old.
     *
     * @author Jose Maria Casado
     *
     * @param  string $texto Cadena de texto a normalizar
     * @param  array $formats Lista de formatos configurados en el editor.
     * @param  array $outputs Lista de outputs que ha seleccionado el usuario
     *
     * @return boolean True si se ha borrado correctamente, false si hubo algún error.
     */
    public function removeBreadcrumb($listaNodos, $formats, $outputs = null)
    {
        $procesoCorrecto =true;

        $routeInclude = $this->breadcrumbsPath . $this->createPath($listaNodos);

        if ($this->fs->exists($routeInclude)) {
            if (null == $outputs) {
                foreach (new \DirectoryIterator($routeInclude) as $fileInfo) {
                    if ($fileinfo->isFile() && $fileinfo->getExtension() == 'html') {
                        try {
                            $this->fs->rename($fileinfo->getPathname(), $fileinfo->getPathname() . ".old", true);
                        } catch (IOException $e) {
                            $procesoCorrecto = false;
                        }
                        try {
                            $this->fs->remove($fileInfo->getPathname());
                        } catch (IOException $e) {
                            $this->logger->error(__METHOD__." - ".$e->getMessage());
                        }
                    }
                }
            } else {
                foreach ($formats as $format) {
                    foreach ($format->getOutputs() as $output) {
                        $breadcrumbName = $this->generateFilename($this->baseBreadCrumbFileName, $output->getSuffix());
                        $file = $routeInclude.$breadcrumbName;
                        if ($this->fs->exists($file) && in_array($format->getName()."-".$output->getName(), $outputs)) {
                            try {
                                $this->fs->rename($file, $file . ".old", true);
                            } catch (IOException $e) {
                                $this->logger->error(__METHOD__." - ".$e->getMessage());
                                $procesoCorrecto = false;
                            }
                        }
                    }
                }
            }
        }

        return $procesoCorrecto;
    }

    /**
     * Generate the breadcrumb's filename based in a suffix.
     *
     * @param string $baseFilename The base filename (eg: miga.html).
     * @param string $suffix The suffix for the generated name.
     *
     * @return string The breadcrumb's filename.
     **/
    public function generateFilename($baseFilename, $suffix)
    {
        if ('' == $suffix) {
            return $baseFilename;
        }

        $chunks = explode('.', $baseFilename);

        return $chunks[0]."_{$suffix}.".$chunks[1];
    }

    /**
     * Crea en base a un listado de nombres un path normalizado con una ruta en
     * disco. Componiendo la ruta con el campo nombre (normalizado) de los nodos.
     *
     * @author Jose Maria Casado
     *
     * @param  array  $listaNodos Listado de nodos.
     *
     * @return string Cadena de texto que compone el path en disco.
     */
    public function createPath($listaNodos)
    {
        $path = "/";
        foreach ($listaNodos as $nodo) {
            $path .= $this->normaliza($nodo['NOMBRE'])."/";
        }

        return $path;
    }

    /**
     * Elimina de una cadena de texto todos los carácteres raros.
     *
     * @author Jose Maria Casado
     *
     * @param  string $texto Cadena de texto a normalizar
     * @return string La cadena de texto sin carácteres raros.
     */
    public function normaliza($texto)
    {
        $texto = str_replace("/", "-", $texto);
        $texto = str_replace(", ", "_", $texto);
        $texto = str_replace(" ", "_", $texto);
        $texto = str_replace("__", "_", $texto);
        $texto = str_replace("_-_", "-", $texto);
        $texto = str_replace("º", "", $texto);
        $texto = str_replace("ª", "", $texto);

        $originales = 'ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèéêëìíîïðñòóôõöøùúûýýþÿŔŕ';
        $modificadas = 'aaaaaaaceeeeiiiidnoooooouuuuybsaaaaaaaceeeeiiiidnoooooouuuyybyRr';
        $texto = utf8_decode($texto);
        $texto = strtr($texto, utf8_decode($originales), $modificadas);
        $texto = strtolower($texto);

        return utf8_encode($texto);
    }

    /**
     * Escribe en disco un fichero HTML en la ruta que le hayamos pasado en el
     * fichero que le pasamos
     *
     * @param type String $content
     * @param type String $path
     * @param type String $fileName
     *
     * @return boolean Devuelve true si todo ha salido bien
     */
    public function writeBreadcrumb($content, $path, $fileName)
    {
        if (!$this->fs->exists($path)) {
            try {
                $this->fs->mkdir($path, 0775);
            } catch (IOException $e) {
                $this->logger->error(__METHOD__." - ".$e->getMessage());
                return false;
            }
        }

        try {
            $this->fs->dumpFile($path . $fileName, $content);
            return true;
        } catch (\Exception $e){
            return false;
        }
    }
}
