<?php
/**
 * @author Alex Phillips <ahp118@gmail.com>
 * Date: 7/10/15
 * Time: 5:35 PM
 */

namespace CloudDrive\Commands;

use CloudDrive\Node;
use Symfony\Component\Console\Input\InputArgument;

class MetadataCommand extends Command
{
    protected $onlineCommand = false;

    protected function configure()
    {
        $this->setName('metadata')
            ->setDescription('Retrieve the metadata (JSON) of a node by its path')
            ->addArgument('path', InputArgument::OPTIONAL, 'The remote path of the node')
            ->addOption('id', 'i', null, 'Designate the remote node by its ID instead of its remote path');
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

        if ($this->config['json.pretty']) {
            $this->output->writeln(json_encode($node, JSON_PRETTY_PRINT));
        } else {
            $this->output->writeln(json_encode($node));
        }
    }
}
