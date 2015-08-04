<?php
/**
 * @author Alex Phillips <ahp118@gmail.com>
 * Date: 8/4/15
 * Time: 11:42 AM
 */

namespace CloudDrive\Commands;

use CloudDrive\Node;
use Symfony\Component\Console\Input\InputArgument;

class TrashCommand extends BaseCommand
{
    protected function configure()
    {
        $this->setName('rm')
            ->setDescription('Move a remote Node to the trash')
            ->addArgument('remote_path', InputArgument::REQUIRED, 'The remote path of the node');
    }

    protected function main()
    {
        $this->init();

        $remotePath = $this->input->getArgument('remote_path');
        $node = Node::loadByPath($remotePath);

        if (!($node instanceof Node)) {
            throw new \Exception("No node exists at remote path '$remotePath'.");
        }

        $result = $node->trash();
        if ($result['success']) {
            $this->output->writeln("Successfully trashed node at '$remotePath': " . json_encode($result['data']));
        } else {
            $this->output->writeln("Failed to trash node at '$remotePath': " . json_encode($result['data']));
        }
    }
}
