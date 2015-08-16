<?php
/**
 * @author Alex Phillips <aphillips@cbcnewmedia.com>
 * Date: 8/14/15
 * Time: 2:35 PM
 */

namespace CloudDrive\Commands;

use CloudDrive\Node;
use Symfony\Component\Console\Input\InputArgument;

class TreeCommand extends Command
{
    protected $onlineCommand = false;

    protected function configure()
    {
        $this->setName('tree')
            ->setDescription('Print directory tree of the given node')
            ->addArgument('path', InputArgument::OPTIONAL, 'The remote path of the node')
            ->addOption('assets', 'a', null, 'Include assets in output')
            ->addOption('id', 'i', null, 'Designate the remote node by its ID instead of its remote path')
            ->addOption('markdown', 'm', null, 'Output the tree in Markdown');
    }

    protected function main()
    {
        $this->init();

        $path = $this->input->getArgument('path') ?: '';

        $includeAssets = $this->input->getOption('assets') ? true : false;

        if ($this->input->getOption('id')) {
            if (!($node = Node::loadById($path))) {
                throw new \Exception("No node exists with ID '$path'.");
            }
        } else {
            if (!($node = Node::loadByPath($path))) {
                throw new \Exception("No node exists at remote path '$path'.");
            }
        }

        if ($this->input->getOption('markdown')) {
            $this->output->write($this->buildMarkdownTree($node, $includeAssets));
        } else {
            $this->output->write($this->buildAsciiTree($node, $includeAssets));
        }
    }

    protected function buildAsciiTree($node, $includeAssets = false, $prefix = '')
    {
        $output = [];

        static $first;
        if (is_null($first)) {
            $first = false;

            if ($node->isFolder()) {
                $this->output->writeln("<blue>{$node['name']}</blue>");
            } else {
                $this->output->writeln($node['name']);
            }
        }

        $children = $node->getChildren();
        for ($i = 0, $count = count($children); $i < $count; ++$i) {
            $itemPrefix = $prefix;
            $next = $children[$i];

            if ($i === $count - 1) {
                if ($next->isFolder()) {
                    $itemPrefix .= '└─┬ ';
                } else {
                    if ($next->isFile() || $includeAssets === true) {
                        $itemPrefix .= '└── ';
                    }
                }
            } else {
                if ($next->isFolder()) {
                    $itemPrefix .= '├─┬ ';
                } else {
                    if ($next->isFile() || $includeAssets === true) {
                        $itemPrefix .= '├── ';
                    }
                }
            }

            if ($next->isFolder()) {
                $this->output->writeln(
                    $itemPrefix . '<blue>' . (string)$next['name'] . '</blue>'
                );
            } else {
                $this->output->writeln(
                    $itemPrefix . (string)$next['name']
                );
            }

            if ($next->isFolder() || $includeAssets === true) {
                $this->buildAsciiTree(
                    $next,
                    $includeAssets,
                    $prefix . ($i == $count - 1 ? '  ' : '| ')
                );
            }
        }
    }

    protected function buildMarkdownTree($node, $includeAssets = false, $prefix = '')
    {
        $output = [];

        static $first;
        if (is_null($first)) {
            $first = false;

            if ($node->isFolder()) {
                $this->output->writeln("<blue>{$node['name']}</blue>");
            } else {
                $this->output->writeln($node['name']);
            }
        }

        foreach ($node->getChildren() as $node) {
            $this->output->writeln("$prefix- {$node['name']}");
            if ($node->isFolder() || $includeAssets === true) {
                $this->buildMarkdownTree($node, $includeAssets, "$prefix  ");
            }
        }
    }
}
