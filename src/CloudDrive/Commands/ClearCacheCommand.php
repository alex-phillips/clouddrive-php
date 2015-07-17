<?php
/**
 * @author Alex Phillips <ahp118@gmail.com>
 * Date: 7/11/15
 * Time: 2:18 PM
 */

namespace CloudDrive\Commands;

class ClearCacheCommand extends BaseCommand
{
    protected function configure()
    {
        $this->setName('clearcache')
            ->setDescription('Clear the local cache');
    }

    protected function _execute()
    {
        $this->init();
        $this->clouddrive->account->authorize();
        $this->clouddrive->account->clearCache();
    }
}
