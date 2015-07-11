<?php
/**
 * @author Alex Phillips <ahp118@gmail.com>
 * Date: 7/11/15
 * Time: 11:06 AM
 */

namespace CloudDrive;

interface Cache
{
    public function deleteAllNodes();

    public function deleteNodeById($id);

    public function findNodeById($id);

    public function findNodeByMd5($md5);

    public function findNodesByName($name);

    public function getNodeChildren(Node $node);

    public function loadAccountConfig($email);

    public function saveAccountConfig(Account $account);

    public function saveNode(Node $node);
}
