<?php
/**
 * @author Alex Phillips <ahp118@gmail.com>
 * Date: 7/11/15
 * Time: 10:25 AM
 */

namespace CloudDrive\Commands;

class SyncCommand extends BaseCommand
{
    protected function configure()
    {
        $this->setName('sync')
            ->setDescription('Sync the local cache with Amazon CloudDrive');
    }

    protected function main()
    {
        $this->init();

        $this->output->writeln("Syncing...");
        $this->clouddrive->getAccount()->sync();
        $this->output->writeln("Done.");
    }
}
