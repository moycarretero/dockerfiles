<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

/**
 * Comando para volcar los ficheros de migas desde un entorno origien
 * a un etorno destino cuyo acceso sea menos restringido que el de
 * origen.
 *
 * Cambia la ruta del include de las secciones en caso de que haga falta
 * y vuelca solo el arbol de ficheros de migas de una seccion si se especifica
 * como parametro del comando.
 *
 * @author Jose Serrano
 */
class DumpBreadcrumbsCommand extends Command
{
    /**
     * Interfaz de salida del componente de consola
     */
    private $output;

    /**
     * Entorno desde el que se vuelcan las migas
     */
    private $toEnv;

    /**
     * Entorno al que se vuelcan las migas
     */
    private $fromEnv;

    /**
     * Seccion cuyas migas se quieren exportar
     */
    private $section;

    /**
     * Path de migas del entorno destino
     */
    private $toPath;

    /**
     * Path del include de secciones del entorno actual
     */
    private $includeSeccionesPath;

    /**
     * Path del include de secciones de staging
     */
    private $includeSeccionesStagingPath;

    private string $breadcrumbIncludePath;

    private string $breadcrumbSectionsInclude;

    /**
     * Metodo de configuracion del comando.
     *
     * @author Jose Serrano
     */

    public function __construct(?string $name = null, $breadcrumbIncludePath, $breadcrumbSectionsInclude)
    {
        parent::__construct($name);

        $this->breadcrumbIncludePath = $breadcrumbIncludePath;
        $this->breadcrumbSectionsInclude = $breadcrumbSectionsInclude;
    }

    protected function configure(): void
    {
        $this
            ->setName(
                'breadcrumbs:dump'
            )
            ->setDescription(
                'Vuelca las migas de un entorno a otro'
            )
            ->addArgument(
                'to',
                InputArgument::REQUIRED, 'Entorno al que se quieren volcar las migas'
            )
            ->addArgument(
                'section',
                InputArgument::OPTIONAL, 'Seccion cuyas migas se quieren volcar'
            )
            ->setHelp(
                "Entornos conocidos: \n1) produccion\n2) staging\n3) desarrollo
                \n\nEl proposito de este comando es volcar todos los ficheros de migas de un entorno a otro.
                \rDebido a los problemas de permisos de escrita que existen, solo se puede volcar desde un entorno
                \rcon mayor importancia a un entorno con menor importancia.
                \nEj:
                \r   - Produccion -> Staging / Desarollo
                \r   - Staging -> Desarrollo
                \nNo se puede volcar desde desarrollo a ningun otro entorno por motivos obvios.
                \nEl comando permite volcar el directorio de migas entero o solo el de una seccion. El comando, en este
                \rcaso, comprueba primero que la seccion existe en el directorio de migas origen. Ademas de volcar los ficheros
                \rtambien modifica la ruta del include de las secciones dentro de un fichero de miga para que apunte al
                \rdebido entorno. Por el momento, este include solo existe en las rutas de produccion o staging, por lo que
                \rlas migas del entorno de desarrollo deben apuntar al include de staging."
            );
    }

    /**
     * Metodo principal del comando. Recoge los parametros de entrada,
     * las rutas de los entornos, modifica las rutas segun el entorno,
     * manda hacer comprobaciones y manda volcar los ficheros desde
     * el entorno de origen al entorno de destino.
     *
     * @author Jose Serrano
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output;

        $this->section = $input->getArgument('section');

        //Recuperamos el entorno en el que estamos actualmente usando
        //la ruta de las migas de la configuracion del proyecto desde el que
        //se lanza el comando.
        preg_match('/html\/(\w+)\/datos/', $this->breadcrumbIncludePath, $matches);

        if (!isset($matches[1])) {
            $output
                ->writeln(
                    "<error>Error recogiendo la ruta de las migas</error>"
                );
                return Command::FAILURE;
        }


        $this->fromEnv = $matches[1];
        unset($matches);

        $this->toEnv = strtolower($input->getArgument('to'));

        //Comprobacion de entornos.
        $this->checkEnvs();

        //Si hay seccion, la agregamos a la ruta de las migas
        //que hemos sacado de la configuracion.
        if (!is_null($this->section)) {
            $this->breadcrumbIncludePath .= DIRECTORY_SEPARATOR . $this->section;
            $this->checkSectionExists();
        }

        //Creamos el path de destino en base al path del origen cambiando
        //los nombres de los entornos en la ruta.
        $this->toPath = str_replace(
            $this->fromEnv,
            $this->toEnv,
            $this->breadcrumbIncludePath
        );

        //Si el path del include de secciones apunta a produccion,
        //lo cambiamos a staging. Incluso si el destino de volcado
        //es desarrollo.
        if (strpos($this->breadcrumbSectionsInclude, 'produccion')) {
            $this->includeSeccionesStagingPath = str_replace(
                'produccion',
                'staging',
                $this->breadcrumbSectionsInclude
            );
        }

        $output->writeln(
            "Volcando migas desde $this->fromEnv -> $this->toEnv"
        );

        //Volcamos todos los directorios y ficheros
        //desde/a las rutas especificadas.
        $this->dumpFiles();

        $output->writeln(
            "\nThe End."
        );

        return Command::SUCCESS;
    }

    /**
     * Volcado de ficheros del entorno origen al entorno destino.
     * Si el entorno origen es produccion, se cambia el path del include
     * de las secciones que va dentro del fichero de la propia miga.
     *
     * @author Jose Serrano
     */
    private function dumpFiles()
    {
        if (!is_dir($this->toPath)) {
            try{
                mkdir($this->toPath);
            } catch (\Exception $e) {
                $this
                    ->output
                    ->writeln(
                        "<error>Error al crear el directorio $this->toPath."
                        ." Compruebe que la ruta de las migas existe.</error>"
                    );
                exit;
            }
        }

        //Recorremos el arbol de directorios del directorio
        //origen y vamos creando los directorios / ficheros
        //en destino.
        foreach (
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    $this->breadcrumbIncludePath,
                    \RecursiveDirectoryIterator::SKIP_DOTS
                ),
                \RecursiveIteratorIterator::SELF_FIRST
            ) as $item
        ) {
            //el item es un directorio
            if ($item->isDir()) {
                $path = $this->toPath
                    . DIRECTORY_SEPARATOR .
                    $iterator->getSubPathName();
                $this
                    ->output
                    ->write(
                        "<info>Creating </info>directory:<info> $path.</info>"
                    );
                if (!is_dir($path)) {
                    mkdir($path);
                    $this
                        ->output
                        ->write(
                            " New!\n"
                        );
                } else {
                    $this
                        ->output
                        ->write(
                            " Already exists!\n"
                        );
                }
            //el item es un fichero
            } else {
                $file = $this->toPath
                    . DIRECTORY_SEPARATOR
                    . $iterator->getSubPathName();
                $this
                    ->output
                    ->write(
                        "<info>Creating</info> file: <info>$file</info>"
                    );

                if (is_file($file)) {
                    $this
                        ->output
                        ->write(
                            " Overwriting!\n"
                        );

                } else {
                    $this
                        ->output
                        ->write(
                            " New!\n"
                        );
                }

                //Copia o crea el fichero.
                copy($item, $file);

                //Abrimos el fichero recien copiado.
                $fileContents = file_get_contents($file);

                //Hacemos un replace de la ruta del fichero
                //actual con el nombre de la miga movil
                //y la guardamos.
                $oldMobileBreadPath = str_replace(
                    'miga.html',
                    'miga_m.html',
                    $file
                );

                //Reemplazamos el entorno de la
                //ruta de la miga movil del entorno
                //origen al entorno destino
                $newMobileBreadPath = str_replace(
                    $this->fromEnv,
                    $this->toEnv,
                    $oldMobileBreadPath
                );

                //Reemplazamos la ruta del fichero
                //de la miga movil dentro del fichero de la miga
                //web con la ruta nueva del entorno de destino
                $replacedFileContents = str_replace(
                    $oldMobileBreadPath,
                    $newMobileBreadPath,
                    $fileContents
                 );

                //Si el entorno es produccion,
                //hay que cambiar la ruta del include de secciones
                //en los ficheros creados.
                if (!is_null($this->includeSeccionesStagingPath)) {
                    $replacedFileContents = str_replace(
                        $this->breadcrumbSectionsInclude,
                        $this->includeSeccionesStagingPath,
                        $replacedFileContents
                    );
                }

                //Escribimos el fichero con los cambios realizados
                file_put_contents($file, $replacedFileContents);
            }
        }
    }

    /**
     * Hace comprobaciones para verificar que los entornos
     * origen y destino son compatibles a la hora de hacer
     * el volcado (problemas de permisos, que sean el mismo,
     * que no exista el entorno, etc)
     *
     * @author Jose Serrano
     */
    private function checkEnvs()
    {
        //Entornos existentes y sus
        //permisos de escritura
        $envs = array(
            'produccion' => array(
                'staging',
                'desarrollo'
            ),
            'staging' => array(
                'desarrollo'
            ),
            'desarrollo' => array()
        );

        //Error: Entornos iguales
        if ($this->fromEnv === $this->toEnv) {
            $this
                ->output
                ->writeln(
                    "\n<error>El entorno actual es</error><info> $this->fromEnv</info>"
                    ." <error>no puedes volcar migas a este mismo entorno.</error>"
                );
            exit;
        }

        if ($this->toEnv === 'help') {
            $this
                ->output
                ->writeln(
                    "<info>HELP! I need somebooody, HELP! Not just "
                    ."anybooody... (No querras decir '--help'?)</info>"
                );
            exit;

        }
        //Error: Entorno $fromEnv no reconocido
        if (!array_key_exists($this->fromEnv, $envs)) {
            $this
                ->output
                ->writeln(
                    "\n<error>Entorno no reconocido: "
                    . $this->fromEnv.".</error>"
                );
            exit;
        }

        //Error: Entorno $toEnv no reconocido
        if (!array_key_exists($this->toEnv, $envs)) {
            $this
                ->output
                ->writeln(
                    "\n<error>Entorno no reconocido: "
                    . $this->toEnv.".</error>"
                );
            exit;
        }

        //Error: $fromEnv no tiene permiso para volcar
        //a en $toEnv
        if (!in_array($this->toEnv, $envs[$this->fromEnv])) {
            $this
                ->output
                ->writeln(
                    "\n<error>No se puede volcar desde "
                    . "$this->fromEnv a $this->toEnv.</error>"
                );
            exit;
        }
    }

    /**
     * Comprueba que la seccion existe en la ruta de migas
     * del entorno origen.
     *
     * @author Jose Serrano
     */
    private function checkSectionExists()
    {
        //Si no hay un subdirectorio inmediato del path de migas
        //origen con el nombre de la seccion es que no existe
        //existe esa seccion
        if (!is_dir($this->breadcrumbIncludePath)) {
            $this
                ->output
                ->writeln(
                    "<error>No existe directorio de migas "
                    ."para la seccion: $this->section</error>"
                );
            exit;
        }
    }

}
