<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

/**
 * Comando para regenerar las migas que hay en la base de datos
 * de migas.
 *
 * @author Jose Serrano
 */
class RegenerateBreadcrumbsCommand extends Command
{
    private $dryRun;
    private $remove;
    private $output;

    /**
     * Metodo de configuracion del comando.
     *
     * @author Jose Serrano
     */
    protected function configure(): void
    {
        $this
            ->setName(
                'breadcrumbs:regenerate'
            )
            ->setDescription(
                'Regenera las migas que existen en base de datos y las escribe a disco'
            )
            ->addOption(
                'dryrun',
                null,
                InputOption::VALUE_NONE,
                'Simula las acciones'
            )
            ->addOption(
                'remove',
                null,
                InputOption::VALUE_NONE,
                'Elimina las migas obsoletas antes de generar las migas nuevas'
            )
            ->setHelp(
                "\nEl comando instancia el servicio de Doctrine, se conecta a ORACLE y busca las migas
                \rpara renegerarlas en disco."
            );
    }

    /**
     * Metodo principal del comando.
     *
     * @author Jose Serrano
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output;

        if ($input->getOption('dryrun')) {
            $this->dryRun = true;
        }
        if ($input->getOption('remove')) {
            $this->remove = true;
        }

        return Command::SUCCESS;
    }

    private function info($str)
    {
        $this->output->writeln("<info>$str</info>");
    }

    private function error($str)
    {
        $this->output->writeln("<error>$str</error>");
    }

}
