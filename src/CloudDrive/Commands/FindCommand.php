<?php
/**
 * @author Alex Phillips <ahp118@gmail.com>
 * Date: 8/6/15
 * Time: 1:30 PM
 */

namespace CloudDrive\Commands;

use CloudDrive\Node;
use Symfony\Component\Console\Input\InputArgument;

class FindCommand extends Command
{
    protected $onlineCommand = false;

    protected function configure()
    {
        $this->setName('find')
            ->setDescription('Find nodes by name or MD5 checksum')
            ->addArgument('query', InputArgument::REQUIRED, 'Query string to search for')
            ->addOption('md5', 'm', null, 'Search for nodes by MD5 rather than name')
            ->addOption('time', 't', null, 'Order output by date modified');
    }

    protected function main()
    {
        $this->init();

        $query = $this->input->getArgument('query');
        if ($this->input->getOption('md5')) {
            $nodes = Node::loadByMd5($query);
        } else {
            $nodes = Node::searchNodesByName($query);
        }

        $sort = Command::SORT_BY_NAME;
        if ($this->input->getOption('time')) {
            $sort = Command::SORT_BY_TIME;
        }

        $this->listNodes($nodes, $sort);
    }
}
