<?php
/**
 * @author Alex Phillips <ahp118@gmail.com>
 * Date: 7/17/15
 * Time: 9:30 AM
 */

namespace CloudDrive\Commands;

use CloudDrive\Node;
use Symfony\Component\Console\Input\InputArgument;

class DownloadCommand extends Command
{
    protected function configure()
    {
        $this->setName('download')
            ->setDescription('Download remote file or folder to specified local path')
            ->addArgument('remote_path', InputArgument::REQUIRED, 'The remote file path to download')
            ->addArgument('local_path', InputArgument::OPTIONAL, 'The path to save the file / folder to')
            ->addOption('id', 'i', null, 'Designate the remote node by its ID instead of its remote path');
    }

    protected function main()
    {
        $this->init();

        $remotePath = $this->input->getArgument('remote_path');
        $savePath = $this->input->getArgument('local_path') ?: getcwd();

        if ($this->input->getOption('id')) {
            if (!($node = Node::loadById($remotePath))) {
                throw new \Exception("No node exists with ID '$remotePath'.");
            }
        } else {
            if (!($node = Node::loadByPath($remotePath))) {
                throw new \Exception("No node exists at remote path '$remotePath'.");
            }
        }

        $node->download($savePath, function ($result, $dest) {
            if ($result['success']) {
                $this->output->writeln("<info>Successfully downloaded file to '$dest'</info>");
            } else {
                $this->output->getErrorOutput()
                    ->writeln("<error>Failed to download node to '$dest'</error>");
                if ($this->output->isVerbose()) {
                    $this->output->getErrorOutput()->writeln(json_encode($result['data']));
                }
            }
        });
    }
}
