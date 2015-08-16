<?php
/**
 * @author Alex Phillips <ahp118@gmail.com>
 * Date: 8/6/15
 * Time: 9:06 AM
 */

namespace CloudDrive\Commands;

use CloudDrive\Node;

class ResolveCommand extends Command
{
    protected $onlineCommand = false;

    protected function configure()
    {
        $this->setName('resolve')
            ->setDescription("Return a node's remote path by its ID")
            ->addArgument('id');
    }

    protected function main()
    {
        $this->init();

        $id = $this->input->getArgument('id');
        if (!($node = Node::loadById($id))) {
            throw new \Exception("No node exists with ID '$id'.");
        }

        $this->output->writeln($node->getPath());
    }
}
