<?php
/**
 * @author Alex Phillips <ahp118@gmail.com>
 * Date: 7/11/15
 * Time: 2:18 PM
 */

namespace CloudDrive\Commands;

class ClearCacheCommand extends Command
{
    protected function configure()
    {
        $this->setName('clearcache')
            ->setAliases([
                'clear-cache',
            ])
            ->setDescription('Clear the local cache');
    }

    protected function main()
    {
        $this->init();
        $this->clouddrive->getAccount()->clearCache();
    }
}
