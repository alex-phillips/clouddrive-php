<?php
/**
 * @author Alex Phillips <ahp118@gmail.com>
 * Date: 7/10/15
 * Time: 5:36 PM
 */

namespace CloudDrive\Commands;

use Cilex\Command\Command;
use CloudDrive\Cache\SQLite;
use CloudDrive\CloudDrive;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Utility\ParameterBag;

abstract class BaseCommand extends Command
{
    /**
     * @var \CloudDrive\CloudDrive
     */
    protected $clouddrive;

    protected $config;

    protected $configPath;

    /**
     * @var \Symfony\Component\Console\Input\InputInterface
     */
    protected $input;

    /**
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    protected $output;

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->configPath = CLI_ROOT;
        $this->input = $input;
        $this->output = $output;
        $this->_execute();
    }

    abstract protected function _execute();

    protected function readConfig()
    {
        if (file_exists("{$this->configPath}cli.json") && $data = json_decode(file_get_contents("{$this->configPath}cli.json"), true)) {
            $this->config = new ParameterBag($data);
        } else {
            $this->config = new ParameterBag();
        }
    }

    protected function saveConfig()
    {
        file_put_contents("{$this->configPath}cli.json", json_encode($this->config));
    }

    protected function init()
    {
        $this->readConfig();

        if ($this->config['email'] && $this->config['client-id'] && $this->config['client-secret']) {
            $clouddrive = new CloudDrive($this->config['email'], $this->config['client-id'], $this->config['client-secret'], new SQLite($this->config['email'], $this->configPath));
            if ($clouddrive->account->authorize()['success']) {
                $this->clouddrive = $clouddrive;
            } else {
                $this->output->writeln('Account has not been authorized. Please do so using the `init` command.');

            }
        }
    }
}
