<?php
/**
 * @author Alex Phillips <ahp118@gmail.com>
 * Date: 8/6/15
 * Time: 12:35 PM
 */

namespace CloudDrive\Commands;

use CloudDrive\Node;
use Symfony\Component\Console\Input\InputArgument;

class MoveCommand extends Command
{
    protected function configure()
    {
        $this->setName('mv')
            ->setDescription('Move a node to a new remote folder')
            ->addArgument('node', InputArgument::REQUIRED, 'Remote node path to move')
            ->addArgument('new_path', InputArgument::REQUIRED, 'Remote folder to move node into');
    }

    protected function main()
    {
        $this->init();

        $nodePath = $this->input->getArgument('node');
        $newPath = $this->input->getArgument('new_path');

        if (!($node = Node::loadByPath($nodePath))) {
            throw new \Exception("No node exists at remote path '$nodePath'.");
        }

        if (!($newParent = Node::loadByPath($newPath))) {
            throw new \Exception("No node exists at remote path '$newPath'.");
        }

        $result = $node->move($newParent);
        if ($result['success']) {
            $this->output->writeln(
                "<info>Successfully moved node '{$node['name']}' to '$newPath'</info>"
            );
            if ($this->output->isVerbose()) {
                $this->output->writeln(json_encode($result['data']));
            }
        } else {
            $this->output->getErrorOutput()->writeln(
                "<error>Failed to move node '{$node['name']}' to '$newPath'</error>"
            );
            if ($this->output->isVerbose()) {
                $this->output->getErrorOutput()->writeln(json_encode($result['data']));
            }
        }
    }
}
