<?php
/**
 * @author Alex Phillips <ahp118@gmail.com>
 * Date: 8/12/15
 * Time: 2:34 PM
 */

namespace CloudDrive\Commands;

use Symfony\Component\Console\Input\InputArgument;

class ConfigCommand extends BaseCommand
{
    private $configValues = [
        'email',
        'client-id',
        'client-secret',
    ];

    protected function configure()
    {
        $this->setName('config')
            ->setDescription('Read, write, and remove config options')
            ->addArgument('option', InputArgument::OPTIONAL, 'Config option to read, write, or remove')
            ->addArgument('value', InputArgument::OPTIONAL, 'Value to set to config option')
            ->addOption('remove', 'r', null, 'Remove config value');
    }

    protected function main()
    {
        if (!($option = $this->input->getArgument('option'))) {
            $maxLength = max(
                array_map('strlen', array_keys($this->config->raw()))
            );
            foreach ($this->config as $key => $value) {
                $key = str_pad($key, $maxLength);
                $this->output->writeln("$key = <blue>$value</blue>");
            }
        } else {
            if (!in_array($option, $this->configValues)) {
                throw new \Exception("Option '$option' not found.");
            }
            if ($value = $this->input->getArgument('value')) {
                $this->config[$option] = $value;
                $this->saveConfig();
                $this->output->writeln("<blue>$option</blue> saved");
            } else {
                $this->output->writeln($this->config[$option]);
            }
        }
    }
}
