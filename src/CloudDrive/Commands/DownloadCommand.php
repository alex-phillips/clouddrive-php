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
            ->setDescription('Download remote file to specified local path (currently only files are supported)')
            ->addArgument('remote_path', InputArgument::REQUIRED, 'The remote file path to download')
            ->addArgument('local_path', InputArgument::OPTIONAL, 'The path to save the file')
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

        if ($node->isFolder()) {
            throw new \Exception("Folder downloads are not currently supported.");
        }

        if (file_exists($savePath) && is_dir($savePath)) {
            $savePath = rtrim($savePath, '/') . "/{$node['name']}";
        }

        $handle = @fopen($savePath, 'a');

        if (!$handle) {
            throw new \Exception("Unable to open file at '$savePath'. Make sure the directory exists.");
        }

        $result = $node->download($handle);
        if ($result['success']) {
            $this->output->writeln("Successfully downloaded file to '$savePath'.");
        } else {
            $this->output->writeln("Failed to download node to '$savePath': " . json_encode($result['data']));
        }

        fclose($handle);
    }
}
