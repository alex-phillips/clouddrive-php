<?php
/**
 * @author Alex Phillips <ahp118@gmail.com>
 * Date: 7/11/15
 * Time: 3:22 PM
 */

namespace CloudDrive\Commands;

use CloudDrive\Node;
use Symfony\Component\Console\Input\InputArgument;

class ListCommand extends BaseCommand
{
    protected function configure()
    {
        $this->setName('ls')
            ->setDescription('List all remote nodes inside of a specified directory')
            ->addArgument('remote_path', InputArgument::OPTIONAL, 'The remote directory to list');
    }

    protected function main()
    {
        $this->init();
        $this->clouddrive->getAccount()->authorize();

        $remotePath = $this->input->getArgument('remote_path') ?: '';

        $node = Node::loadByPath($remotePath);

        if (!$node) {
            throw new \Exception("Remote path '$remotePath' does not exist.");
        }

        $nodes = $node->getChildren();

        foreach ($nodes as $node) {
            $modified = new \DateTime($node['modifiedDate']);
            $this->output->writeln(
                sprintf("%s  %s  %s %s\t %s", $node['id'], $modified->format("M d y H:m"), $node['status'], $node['kind'], $node['name'])
            );
        }
    }
}
