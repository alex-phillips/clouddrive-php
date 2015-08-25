<?php
/**
 * @author Alex Phillips <ahp118@gmail.com>
 * Date: 8/21/15
 * Time: 3:49 PM
 */

namespace CloudDrive\Commands;

use CloudDrive\Node;
use Symfony\Component\Console\Input\InputArgument;

class TempLinkCommand extends Command
{
    protected function configure()
    {
        $this->setName('link')
            ->setDescription('Generate a temporary, pre-authenticated download link')
            ->addArgument('remote_path', InputArgument::OPTIONAL, 'The remote directory to list')
            ->addOption('id', 'i', null, 'Designate the remote node by its ID instead of its remote path');
    }

    protected function main()
    {
        $this->init();

        $remotePath = $this->input->getArgument('remote_path') ?: '';

        if ($this->input->getOption('id')) {
            if (!($node = Node::loadById($remotePath))) {
                throw new \Exception("No node exists with ID '$remotePath'.");
            }
        } else {
            if (!($node = Node::loadByPath($remotePath))) {
                throw new \Exception("No node exists at remote path '$remotePath'.");
            }
        }

        if ($node->isFolder()) {
            throw new \Exception("Links can only be created for files.");
        }

        $response = $node->getMetadata(true);
        if ($response['success']) {
            if (isset($response['data']['tempLink'])) {
                $this->output->writeln($response['data']['tempLink']);
            } else {
                $this->output->getErrorOutput()
                    ->writeln("<error>Failed retrieving temporary link. Make sure you have permission.</error>");
            }
        } else {
            $this->output->getErrorOutput()
                ->writeln("<error>Failed retrieving metadata for node '$remotePath'</error>");
        }
    }
}
