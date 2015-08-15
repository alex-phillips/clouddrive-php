<?php
/**
 * @author Alex Phillips <aphillips@cbcnewmedia.com>
 * Date: 8/14/15
 * Time: 2:35 PM
 */

namespace CloudDrive\Commands;

use CloudDrive\Node;
use Symfony\Component\Console\Input\InputArgument;

class TreeCommand extends BaseCommand
{
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
            $this->output->write($this->buildMarkdownTree($node->getChildren(), $includeAssets));
        } else {
            $this->output->write($this->buildAsciiTree($node->getChildren(), $includeAssets));
        }
    }

    protected function buildAsciiTree($children, $includeAssets = false, $prefix = '')
    {
        $output = [];

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
                $output[] = $itemPrefix . '<blue>' . (string)$next['name'] . '</blue>';
            } else {
                $output[] = $itemPrefix . (string)$next['name'];
            }

            if ($next->isFolder() || $includeAssets === true) {
                if ($nextChildren = $next->getChildren()) {
                    $output[] = $this->buildAsciiTree(
                        $nextChildren,
                        $includeAssets,
                        $prefix . ($i == $count - 1 ? '  ' : '| ')
                    );
                }
            }
        }

        return implode("\n", $output);
    }

    protected function buildMarkdownTree($children, $includeAssets = false, $prefix = '')
    {
        $output = [];
        foreach ($children as $node) {
            $output[] = "$prefix- {$node['name']}";
            if ($node->isFolder()) {
                $output[] = $this->buildMarkdownTree($node->getChildren(), $includeAssets, "$prefix  ");
            }
        }
        return implode("\n", $output);
    }
}
