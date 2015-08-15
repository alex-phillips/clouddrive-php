<?php
/**
 * @author Alex Phillips <ahp118@gmail.com>
 * Date: 8/5/15
 * Time: 2:47 PM
 */

namespace CloudDrive\Commands;

use CloudDrive\Node;
use Symfony\Component\Console\Input\InputArgument;

class RenameCommand extends Command
{
    protected function configure()
    {
        $this->setName('rename')
            ->setDescription('Rename remote node')
            ->addArgument('remote_path', InputArgument::REQUIRED, 'Path to remote node')
            ->addArgument('name', InputArgument::REQUIRED, 'New name for the node')
            ->addOption('id', 'i', null, 'Designate the remote node by its ID instead of its remote path');
    }

    protected function main()
    {
        $this->init();

        $remotePath = $this->input->getArgument('remote_path');
        $name = $this->input->getArgument('name');

        if ($this->input->getOption('id')) {
            if (!($node = Node::loadById($remotePath))) {
                throw new \Exception("No node exists with ID '$remotePath'.");
            }
        } else {
            if (!($node = Node::loadByPath($remotePath))) {
                throw new \Exception("No node exists at remote path '$remotePath'.");
            }
        }

        $result = $node->rename($name);
        if ($result['success']) {
            $this->output->writeln("Successfully renamed '$remotePath' to '$name': " . json_encode($result['data']));
        } else {
            $this->output->writeln("Failed to rename '$remotePath' to '$name': " . json_encode($result['data']));
        }
    }
}
