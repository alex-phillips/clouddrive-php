<?php
/**
 * @author Alex Phillips <ahp118@gmail.com>
 * Date: 7/10/15
 * Time: 5:36 PM
 */

namespace CloudDrive\Commands;

use Cilex\Command\Command as CilexCommand;
use CloudDrive\Cache\MySQL;
use CloudDrive\Cache\SQLite;
use CloudDrive\CloudDrive;
use CloudDrive\Node;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Utility\ParameterBag;

abstract class Command extends CilexCommand
{
    /**
     * @var \CloudDrive\Cache
     */
    protected $cacheStore;

    /**
     * @var \CloudDrive\CloudDrive
     */
    protected $clouddrive;

    /**
     * @var \Utility\ParameterBag
     */
    protected $config;

    /**
     * @var string
     */
    protected $configFile;

    /**
     * @var string
     */
    protected $configPath;

    /**
     * Default and accepted values for the CLI config
     *
     * @var array
     */
    protected $configValues = [
        'email'             => [
            'type'    => 'string',
            'default' => '',
        ],
        'client-id'         => [
            'type'    => 'string',
            'default' => '',
        ],
        'client-secret'     => [
            'type'    => 'string',
            'default' => '',
        ],
        'json.pretty'       => [
            'type'    => 'bool',
            'default' => false,
        ],
        'upload.duplicates' => [
            'type'    => 'bool',
            'default' => false,
        ],
        'database.driver'   => [
            'type'    => 'string',
            'default' => 'sqlite',
        ],
        'database.database' => [
            'type'    => 'string',
            'default' => 'clouddrive_php',
        ],
        'database.host'     => [
            'type'    => 'string',
            'default' => '127.0.0.1',
        ],
        'database.username' => [
            'type'    => 'string',
            'default' => 'root',
        ],
        'database.password' => [
            'type'    => 'string',
            'default' => '',
        ],
        'display.trash'     => [
            'type'    => 'bool',
            'default' => false,
        ],
    ];

    /**
     * @var \Symfony\Component\Console\Input\InputInterface
     */
    protected $input;

    /**
     * @var bool
     */
    protected $onlineCommand = true;

    /**
     * @var \Symfony\Component\Console\Output\ConsoleOutput
     */
    protected $output;

    const SORT_BY_NAME = 0;
    const SORT_BY_TIME = 1;

    protected function convertFilesize($bytes, $decimals = 2)
    {
        $bytes = $bytes ?: 0;
        $size = ['B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        $factor = floor((strlen($bytes) - 1) / 3);

        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$size[$factor];
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $home = getenv('HOME');
        if (!$home) {
            throw new \RuntimeException("'HOME' environment variable must be set for Cloud Drive to properly run.");
        }

        $this->configPath = rtrim($home, '/') . '/.cache/clouddrive-php/';
        if (!file_exists($this->configPath)) {
            mkdir($this->configPath, 0777, true);
        }

        $this->configFile = "{$this->configPath}config.json";

        $this->input = $input;
        $this->output = $output;

        // Set up basic styling
        $this->output->getFormatter()->setStyle('blue', new OutputFormatterStyle('blue'));

        $this->readConfig();
        $this->main();
    }

    protected function generateCacheStore()
    {
        switch ($this->config->get('database.driver')) {
            case 'sqlite':
                return new SQLite($this->config['email'], $this->configPath);
                break;
            case 'mysql':
                return new MySQL(
                    $this->config['database.host'],
                    $this->config['database.database'],
                    $this->config['database.username'],
                    $this->config['database.password']
                );
                break;
        }
    }

    protected function init()
    {
        if ($this->onlineCommand === true) {
            $this->initOnlineCommand();
        } else {
            $this->initOfflineCommand();
        }
    }

    protected function initOfflineCommand()
    {
        if (count($this->config) === 0) {
            throw new \Exception('Account has not been authorized. Please do so using the `init` command.');
        }

        $this->cacheStore = $this->generateCacheStore();

        if ($this->config['email'] && $this->config['client-id'] && $this->config['client-secret']) {
            $clouddrive = new CloudDrive(
                $this->config['email'],
                $this->config['client-id'],
                $this->config['client-secret'],
                $this->cacheStore
            );

            $this->clouddrive = $clouddrive;
            Node::init($this->clouddrive->getAccount(), $this->cacheStore);
        }
    }

    /**
     * @throws \Exception
     */
    protected function initOnlineCommand()
    {
        if (count($this->config) === 0) {
            throw new \Exception('Account has not been authorized. Please do so using the `init` command.');
        }

        $this->cacheStore = $this->generateCacheStore();

        if ($this->config['email'] && $this->config['client-id'] && $this->config['client-secret']) {
            $clouddrive = new CloudDrive(
                $this->config['email'],
                $this->config['client-id'],
                $this->config['client-secret'],
                $this->cacheStore
            );

            if ($this->output->getVerbosity() === 2) {
                $this->output->writeln("Authorizing...", OutputInterface::VERBOSITY_VERBOSE);
            }
            if ($clouddrive->getAccount()->authorize()['success']) {
                if ($this->output->getVerbosity() === 2) {
                    $this->output->writeln("Done.");
                }
                $this->clouddrive = $clouddrive;
                Node::init($this->clouddrive->getAccount(), $this->cacheStore);
            } else {
                throw new \Exception('Account has not been authorized. Please do so using the `init` command.');
            }
        }
    }

    protected function listNodes(array $nodes, $sortBy = self::SORT_BY_NAME)
    {
        switch ($sortBy) {
            case self::SORT_BY_NAME:
                usort($nodes, function ($a, $b) {
                    return strcasecmp($a['name'], $b['name']);
                });
                break;
            case self::SORT_BY_TIME:
                usort($nodes, function ($a, $b) {
                    return strtotime($a['modifiedDate']) < strtotime($b['modifiedDate']);
                });
                break;
        }

        foreach ($nodes as $node) {
            if ($node->inTrash() && !$this->config['display.trash']) {
                continue;
            }

            $modified = new \DateTime($node['modifiedDate']);
            if ($modified->format('Y') === date('Y')) {
                $date = $modified->format('M d H:m');
            } else {
                $date = $modified->format('M d  Y');
            }

            $name = $node['kind'] === 'FOLDER' ? "<blue>{$node['name']}</blue>" : $node['name'];
            $this->output->writeln(
                sprintf(
                    "%s  %s  %s %s %s %s",
                    $node['id'],
                    $date,
                    str_pad($node['status'], 10),
                    str_pad($node['kind'], 7),
                    str_pad($this->convertFilesize($node['contentProperties']['size'], 0), 6),
                    $name
                )
            );
        }
    }

    abstract protected function main();

    protected function readConfig()
    {
        $this->config = new ParameterBag();
        if (!file_exists($this->configFile) || !($data = json_decode(file_get_contents($this->configFile), true))) {
            $data = [];
        }

        $this->setConfig($data);
    }

    protected function removeConfigValue($key)
    {
        $this->config[$key] = $this->configValues[$key]['default'];
    }

    protected function setConfig(array $data)
    {
        $data = (new ParameterBag($data))->flatten();
        foreach ($this->configValues as $option => $config) {
            if (isset($data[$option])) {
                $this->setConfigValue($option, $data[$option]);
            } else {
                $this->setConfigValue($option, $config['default']);
            }
        }
    }

    protected function setConfigValue($key, $value = null)
    {
        if (array_key_exists($key, $this->configValues)) {
            switch ($this->configValues[$key]['type']) {
                case 'bool':
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    break;
            }

            settype($value, $this->configValues[$key]['type']);

            $this->config[$key] = $value;
        }
    }

    protected function saveConfig()
    {
        file_put_contents("{$this->configPath}config.json", json_encode($this->config));
    }
}
