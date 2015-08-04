<?php
/**
 * @author Alex Phillips <ahp118@gmail.com>
 * Date: 7/11/15
 * Time: 1:02 PM
 */

namespace CloudDrive\Commands;

use Symfony\Component\Console\Input\InputArgument;

class UploadCommand extends BaseCommand
{
    protected function configure()
    {
        $this->setName('upload')
            ->setDescription('Upload local file or folder to remote directory')
            ->addArgument('local_path', InputArgument::REQUIRED, 'The location of the local file')
            ->addArgument('remote_path', InputArgument::OPTIONAL, 'The remote folder to upload to')
            ->addOption('overwrite', 'o', null, 'Overwrite remote file if file exists and does not match local copy');
    }

    protected function main()
    {
        $this->init();
        $this->clouddrive->getAccount()->authorize();

        $overwrite = $this->input->getOption('overwrite') ?: false;

        $source = realpath($this->input->getArgument('local_path'));
        $remote = $this->input->getArgument('remote_path') ?: '';

        if (is_dir($source)) {
            $this->clouddrive->uploadDirectory($source, $remote, $overwrite, true);
        } else {
            $response = $this->clouddrive->uploadFile($source, $remote, $overwrite);
            if ($response['success']) {
                $this->output->writeln("Successfully uploaded file $source: " . json_encode($response['data']));
            } else {
                $this->output->writeln("Failed to upload file $source: " . json_encode($response['data']));
            }
        }
    }
}
