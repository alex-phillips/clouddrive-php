<?php
/**
 * @author Alex Phillips <aphillips@cbcnewmedia.com>
 * Date: 8/9/15
 * Time: 7:59 PM
 */

namespace CloudDrive\Commands;

class QuotaCommand extends BaseCommand
{
    protected function configure()
    {
        $this->setName('quota')
            ->setDescription('Show Cloud Drive quota')
            ->addOption('pretty', 'p', null, 'Output the metadata in easy-to-read JSON');
    }

    protected function main()
    {
        $this->init();

        $result = $this->clouddrive->getAccount()->getQuota();
        if ($result['success']) {
            if ($this->input->getOption('pretty')) {
                $this->output->writeln(json_encode($result['data'], JSON_PRETTY_PRINT));
            } else {
                $this->output->writeln(json_encode($result['data']));
            }
        } else {
            $this->output->writeln("Failed to retrieve accoutn quota: " . json_encode($result['data']));
        }
    }
}
