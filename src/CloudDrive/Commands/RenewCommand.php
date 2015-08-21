<?php
/**
 * @author Alex Phillips <ahp118@gmail.com>
 * Date: 8/17/15
 * Time: 11:39 AM
 */

namespace CloudDrive\Commands;

class RenewCommand extends Command
{
    protected function configure()
    {
        $this->setName('renew')
            ->setDescription('Renew authorization');
    }

    protected function main()
    {
        $this->init();

        $result = $this->clouddrive->getAccount()->renewAuthorization();
        if ($result['success']) {
            $this->output->writeln("<info>Successfully renewed authorization.</info>");
        } else {
            $this->output->getErrorOutput()
                ->writeln("<error>Failed to renew authorization.</error>");
        }
    }
}
