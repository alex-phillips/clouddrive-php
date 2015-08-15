<?php
/**
 * @author Alex Phillips <aphillips@cbcnewmedia.com>
 * Date: 8/9/15
 * Time: 8:04 PM
 */

namespace CloudDrive\Commands;

class UsageCommand extends Command
{
    protected function configure()
    {
        $this->setName('usage')
            ->setDescription('Show Cloud Drive usage');
    }

    protected function main()
    {
        $this->init();

        $result = $this->clouddrive->getAccount()->getUsage();
        if ($result['success']) {
            if ($this->config['json.pretty']) {
                $this->output->writeln(json_encode($result['data'], JSON_PRETTY_PRINT));
            } else {
                $this->output->writeln(json_encode($result['data']));
            }
        } else {
            $this->output->writeln("Failed to retrieve accoutn quota: " . json_encode($result['data']));
        }
    }
}
