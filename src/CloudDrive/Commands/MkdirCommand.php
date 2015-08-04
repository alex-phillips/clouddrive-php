<?php
/**
 * @author Alex Phillips <ahp118@gmail.com>
 * Date: 8/3/15
 * Time: 10:14 AM
 */

namespace CloudDrive\Commands;

use CloudDrive\Node;
use Symfony\Component\Console\Input\InputArgument;

class MkdirCommand extends BaseCommand
{
    protected function configure()
    {
        $this->setName('mkdir')
            ->setDescription('Create a new remote directory given a path')
            ->addArgument('remote_path', InputArgument::REQUIRED, 'The remote path to create');
    }

    protected function main()
    {
        $this->init();

        $remotePath = $this->input->getArgument('remote_path');

        if ($node = Node::loadByPath($remotePath)) {
            throw new \Exception("Node already exists at remote path '$remotePath'. Make sure it's not in the trash.");
        }

        $result = $this->clouddrive->createDirectoryPath($remotePath);

        if (!$result['success']) {
            $this->output->writeln("Failed to create remote path '$remotePath': " . json_encode($result['data']));
        } else {
            $this->output->writeln("Successfully created remote path '$remotePath': " . json_encode($result['data']));
        }
    }
}
