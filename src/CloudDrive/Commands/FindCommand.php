<?php
/**
 * @author Alex Phillips <ahp118@gmail.com>
 * Date: 8/6/15
 * Time: 1:30 PM
 */

namespace CloudDrive\Commands;

use CloudDrive\Node;

class FindCommand extends BaseCommand
{
    protected function configure()
    {
        $this->setName('find')
            ->setDescription('Find nodes by name or MD5 checksum')
            ->addArgument('query')
            ->addOption('md5', null, null, 'Search for nodes by MD5 rather than name');
    }

    protected function main()
    {
        $this->init();

        $query = $this->input->getArgument('query');
        if ($this->input->getOption('md5')) {
            $nodes = [Node::findNodeByMd5($query)];
        } else {
            $nodes = Node::searchNodesByName($query);
        }

        $this->listNodes($nodes);
    }
}
