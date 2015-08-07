<?php
/**
 * @author Alex Phillips <ahp118@gmail.com>
 * Date: 8/5/15
 * Time: 3:42 PM
 */

namespace CloudDrive\Commands;

use CloudDrive\Node;

class ListTrashCommand extends BaseCommand
{
    protected function configure()
    {
        $this->setName('trash')
            ->setDescription('List the nodes that are in trash');
    }

    protected function main()
    {
        $this->init();

        $this->listNodes(Node::filter([
            ['status' => 'TRASH'],
        ]));
    }
}
