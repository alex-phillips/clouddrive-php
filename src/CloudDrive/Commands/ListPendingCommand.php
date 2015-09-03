<?php
/**
 * @author Alex Phillips <ahp118@gmail.com>
 * Date: 9/3/15
 * Time: 5:28 PM
 */

namespace CloudDrive\Commands;

use CloudDrive\Node;

class ListPendingCommand extends Command
{
    protected $onlineCommand = false;

    protected function configure()
    {
        $this->setName('pending')
            ->setDescription("List the nodes that have a status of 'PENDING'")
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
                ['status' => 'PENDING'],
            ]),
            $sort
        );
    }
}
