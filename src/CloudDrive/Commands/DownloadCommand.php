<?php
/**
 * @author Alex Phillips <ahp118@gmail.com>
 * Date: 7/17/15
 * Time: 9:30 AM
 */

namespace CloudDrive\Commands;

use Symfony\Component\Console\Input\InputArgument;

class DownloadCommand extends BaseCommand
{
    protected function configure()
    {
        $this->setName('download')
            ->setDescription('Download remote file to specified local path (currently only files are supported)')
            ->addArgument('remote_path', InputArgument::REQUIRED, 'The remote file path to download')
            ->addArgument('local_path', InputArgument::OPTIONAL, 'The path to save the file');
    }

    protected function _execute()
    {
        $this->init();
        $this->clouddrive->getAccount()->authorize();

        $remotePath = $this->input->getArgument('remote_path');
        $localPath = $this->input->getArgument('local_path');

        $response = $this->clouddrive->downloadFile($remotePath, $localPath);
        if ($response['success'] === false) {
            $this->output->writeln("Failed to download file: " . json_encode($response['data']));
        } else {
            $this->output->writeln("Successfully downloaded file to $localPath");
        }
    }
}
