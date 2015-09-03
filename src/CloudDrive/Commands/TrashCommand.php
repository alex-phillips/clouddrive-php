<?php
/**
 * @author Alex Phillips <ahp118@gmail.com>
 * Date: 8/4/15
 * Time: 11:42 AM
 */

namespace CloudDrive\Commands;

use CloudDrive\Node;
use Symfony\Component\Console\Input\InputArgument;

class TrashCommand extends Command
{
    protected function configure()
    {
        $this->setName('rm')
            ->setDescription('Move a remote Node to the trash')
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

        $result = $node->trash();
        if ($result['success']) {
            $this->output->writeln("<info>Successfully trashed node at '$remotePath'</info>");
            if ($this->output->isVerbose()) {
                $this->output->writeln(json_encode($result['data']));
            }
        } else {
            $this->output->getErrorOutput()->writeln("<error>Failed to trash node at '$remotePath'</error>");
            if ($this->output->isVerbose()) {
                $this->output->getErrorOutput()
                    ->writeln(json_encode($result['data']));
            }
        }
    }
}
