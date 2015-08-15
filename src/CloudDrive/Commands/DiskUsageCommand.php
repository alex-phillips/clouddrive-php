<?php
/**
 * @author Alex Phillips <ahp118@gmail.com>
 * Date: 8/15/15
 * Time: 10:14 AM
 */

namespace CloudDrive\Commands;

use CloudDrive\Node;
use Symfony\Component\Console\Input\InputArgument;

class DiskUsageCommand extends BaseCommand
{
    protected function configure()
    {
        $this->setName('du')
            ->setDescription('Display disk usage for the given node')
            ->addArgument('path', InputArgument::OPTIONAL, 'The remote path of the node')
            ->addOption('id', 'i', null, 'Designate the remote node by its ID instead of its remote path')
            ->addOption('assets', 'a', null, 'Include assets in output');
    }

    protected function main()
    {
        $this->init();

        $path = $this->input->getArgument('path') ?: '';

        if ($this->input->getOption('id')) {
            if (!($node = Node::loadById($path))) {
                throw new \Exception("No node exists with ID '$path'.");
            }
        } else {
            if (!($node = Node::loadByPath($path))) {
                throw new \Exception("No node exists at remote path '$path'.");
            }
        }

        $this->output->writeln($this->convertFilesize($this->calculateTotalSize($node)));
    }

    protected function calculateTotalSize(Node $node)
    {
        $size = $node['contentProperties']['size'] ?: 0;

        if ($node->isFolder() || $this->input->getOption('assets')) {
            foreach ($node->getChildren() as $child) {
                $size += $this->calculateTotalSize($child);
            }
        }

        return $size;
    }
}
