<?php
/**
 * @author Alex Phillips <ahp118@gmail.com>
 * Date: 7/17/15
 * Time: 9:30 AM
 */

namespace CloudDrive\Commands;

use CloudDrive\Node;
use Symfony\Component\Console\Input\InputArgument;

class DownloadCommand extends BaseCommand
{
    protected function configure()
    {
        $this->setName('download')
            ->setDescription('Download remote file to specified local path (currently only files are supported)')
            ->addArgument('remote_path', InputArgument::REQUIRED, 'The remote file path to download')
            ->addArgument('local_path', InputArgument::OPTIONAL, 'The path to save the file')
            ->addOption('id', 'i', null, 'Designate the remote node by its ID instead of its remote path');
    }

    protected function main()
    {
        $this->init();

        $remotePath = $this->input->getArgument('remote_path');
        $localPath = $this->input->getArgument('local_path') ?: '.';

        if ($this->input->getOption('id')) {
            if (!($node = Node::loadById($remotePath))) {
                throw new \Exception("No node exists with ID '$remotePath'.");
            }
        } else {
            if (!($node = Node::loadByPath($remotePath))) {
                throw new \Exception("No node exists at remote path '$remotePath'.");
            }
        }

        if ($node->isFolder()) {
            throw new \Exception("Folder downloads are not currently supported.");
        }

        $result = $node->download($localPath);
        if ($result['success']) {
            $this->output->writeln("Successfully downloaded file to '$localPath'.");
        } else {
            $this->output->writeln("Failed to download node to '$localPath': " . json_encode($result['data']));
        }
    }
}
