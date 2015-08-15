<?php
/**
 * @author Alex Phillips <ahp118@gmail.com>
 * Date: 8/13/15
 * Time: 12:55 PM
 */

namespace CloudDrive\Commands;

use CloudDrive\Node;
use Symfony\Component\Console\Input\InputArgument;

class CatCommand extends Command
{
    protected function configure()
    {
        $this->setName('cat')
            ->setDescription('Output a file to the standard output stream')
            ->addArgument('remote_path', InputArgument::REQUIRED, 'The remote file path to download')
            ->addOption('id', 'i', null, 'Designate the remote node by its ID instead of its remote path');
    }

    protected function main()
    {
        $this->init();

        $remotePath = $this->input->getArgument('remote_path');

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

        $node->download($this->output->getStream());
    }
}
