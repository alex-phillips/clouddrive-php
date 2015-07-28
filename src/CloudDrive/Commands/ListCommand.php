<?php
/**
 * @author Alex Phillips <ahp118@gmail.com>
 * Date: 7/11/15
 * Time: 3:22 PM
 */

namespace CloudDrive\Commands;

use Symfony\Component\Console\Input\InputArgument;

class ListCommand extends BaseCommand
{
    protected function configure()
    {
        $this->setName('ls')
            ->setDescription('List all remote nodes inside of a specified directory')
            ->addArgument('remote_path', InputArgument::OPTIONAL, 'The remote directory to list');
    }

    protected function _execute()
    {
        $this->init();
        $this->clouddrive->getAccount()->authorize();

        $remotePath = $this->input->getArgument('remote_path') ?: '';

        $node = $this->clouddrive->findNodeByPath($remotePath);

        if (!$node) {
            $this->output->writeln("Remote path '$remotePath' does not exist.");

            return;
        }

        $nodes = $this->clouddrive->getChildren($node);

        foreach ($nodes as $node) {
            $modified = new \DateTime($node['modifiedDate']);
            $this->output->writeln(sprintf("%s  %s  %s\t %s", $node['id'], $modified->format("M d y H:m"), $node['kind'], $node['name']));
        }
    }
}
