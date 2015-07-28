<?php
/**
 * @author Alex Phillips <ahp118@gmail.com>
 * Date: 7/11/15
 * Time: 10:04 AM
 */

namespace CloudDrive\Commands;

use CloudDrive\Cache\SQLite;
use CloudDrive\CloudDrive;
use Symfony\Component\Console\Input\InputArgument;

class InitCommand extends BaseCommand
{
    protected function configure()
    {
        $this->setName('init')
            ->setDescription('Initialize the command line application for use with an Amazon account')
            ->addOption('email', 'e', InputArgument::OPTIONAL, 'The Amazon account email')
            ->addOption('client-id', 'i', InputArgument::OPTIONAL, 'The CloudDrive API client ID')
            ->addOption('client-secret', 's', InputArgument::OPTIONAL, 'The CloudDrive API client secret')
            ->addOption('auth-url', 'a', InputArgument::OPTIONAL, 'The redirect URL used during initial authorization');
    }

    protected function _execute()
    {
        if (!file_exists($this->configPath)) {
            mkdir($this->configPath, 0777, true);
        }

        $this->readConfig();

        if (!$this->config['email']) {
            $this->config['email'] = $this->input->getOption('email');
        }
        if (!$this->config['client-id']) {
            $this->config['client-id'] = $this->input->getOption('client-id');
        }
        if (!$this->config['client-secret']) {
            $this->config['client-secret'] = $this->input->getOption('client-secret');
        }

        if (!$this->config['email']) {
            throw new \Exception('Email is required for initialization.');
        }

        if (!$this->config['client-id'] || !$this->config['client-secret']) {
            throw new \Exception('Amazon CloudDrive API credentials are required for initialization.');
        }

        $this->saveConfig();

        $this->cacheStore = new SQLite($this->config['email'], $this->configPath);
        $this->clouddrive = new CloudDrive($this->config['email'], $this->config['client-id'], $this->config['client-secret'], $this->cacheStore);

        $response = $this->clouddrive->getAccount()->authorize();
        if (!$response['success']) {
            $this->output->writeln($response['data']['message']);
            if (isset($response['data']['auth_url'])) {
                $this->output->writeln('Navigate to the following URL and paste in the redirect URL here.');
                $this->output->writeln($response['data']['auth_url']);

                $redirectUrl = readline();

                $response = $this->clouddrive->getAccount()->authorize($redirectUrl);
                if ($response['success']) {
                    $this->output->writeln('Successfully authenticated with Amazon CloudDrive.');
                    return;
                }

                $this->output->writeln('Failed to authenticate with Amazon Clouddrive: ' . json_encode($response['data']));
            }
        } else {
            $this->output->writeln('That user is already authenticated with Amazon CloudDrive.');
        }
    }
}
