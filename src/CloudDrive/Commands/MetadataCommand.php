<?php
/**
 * @author Alex Phillips <ahp118@gmail.com>
 * Date: 7/10/15
 * Time: 5:35 PM
 */

namespace CloudDrive\Commands;

use Symfony\Component\Console\Input\InputArgument;

class MetadataCommand extends BaseCommand
{
    protected function configure()
    {
        $this->setName('metadata')
            ->setDescription('Retrieve the metadata (JSON) of a node by its path')
            ->addArgument('path', InputArgument::REQUIRED, 'The remote path of the node');
    }

    protected function _execute()
    {
        $this->init();
        $this->clouddrive->getAccount()->authorize();

        $path = $this->input->getArgument('path');
        $node = $this->clouddrive->findNodeByPath($path);

        if (is_null($node)) {
            $this->output->writeln('Remote file path does not exist.');
        } else {
            $this->output->writeln(json_encode($node));
        }
    }
}
