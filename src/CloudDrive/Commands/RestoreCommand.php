<?php
/**
 * @author Alex Phillips <ahp118@gmail.com>
 * Date: 8/4/15
 * Time: 11:42 AM
 */

namespace CloudDrive\Commands;

use CloudDrive\Node;
use Symfony\Component\Console\Input\InputArgument;

class RestoreCommand extends BaseCommand
{
    protected function configure()
    {
        $this->setName('restore')
            ->setDescription('Restore a remote node from the trash')
            ->addArgument('remote_path', InputArgument::REQUIRED, 'The remote path of the node')
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

        $result = $node->restore();
        if ($result['success']) {
            $this->output->writeln("Successfully restored node at '$remotePath': " . json_encode($result['data']));
        } else {
            $this->output->writeln("Failed to restore node at '$remotePath': " . json_encode($result['data']));
        }
    }
}
