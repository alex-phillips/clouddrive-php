<?php
/**
 * @author Alex Phillips <ahp118@gmail.com>
 * Date: 8/5/15
 * Time: 3:42 PM
 */

namespace CloudDrive\Commands;

use CloudDrive\Node;

class ListTrashCommand extends Command
{
    protected $onlineCommand = false;

    protected function configure()
    {
        $this->setName('trash')
            ->setDescription('List the nodes that are in trash')
            ->addOption('time', 't', null, 'Order output by date modified');
    }

    protected function main()
    {
        $this->init();

        $sort = Command::SORT_BY_NAME;
        if ($this->input->getOption('time')) {
            $sort = Command::SORT_BY_TIME;
        }

        $this->listNodes(
            Node::filter([
                ['status' => 'TRASH'],
            ]),
            $sort
        );
    }
}
