<?php
/**
 * @author Alex Phillips <ahp118@gmail.com>
 * Date: 7/11/15
 * Time: 1:02 PM
 */

namespace CloudDrive\Commands;

use Symfony\Component\Console\Input\InputArgument;

class UploadCommand extends Command
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

        $overwrite = $this->input->getOption('overwrite') ?: false;

        $source = realpath($this->input->getArgument('local_path'));
        $remote = $this->input->getArgument('remote_path') ?: '';

        if (is_dir($source)) {
            $this->clouddrive->uploadDirectory($source, $remote, $overwrite, array($this, 'outputResult'));
        } else {
            $response = $this->clouddrive->uploadFile($source, $remote, $overwrite, $this->config['upload.duplicates']);
            $this->outputResult($response, [
                'name' => $source,
            ]);
        }
    }

    public function outputResult($response, $info = null)
    {
        if ($response['success']) {
            $this->output->writeln("<info>Successfully uploaded file '{$info['name']}'</info>");
            if ($this->output->isVerbose()) {
                $this->output->writeln(json_encode($response));
            }
        } else {
            $this->output->getErrorOutput()
                ->writeln("<error>Failed to upload file '{$info['name']}': {$response['data']['message']}</error>");
            if ($this->output->isVerbose()) {
                $this->output->getErrorOutput()->writeln(json_encode($response));
            }
        }
    }
}
