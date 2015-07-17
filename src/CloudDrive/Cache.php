<?php
/**
 * @author Alex Phillips <ahp118@gmail.com>
 * Date: 7/11/15
 * Time: 11:06 AM
 */

namespace CloudDrive;

/**
 * Class that handles local storage of remote node information.
 *
 * @package CloudDrive
 */
interface Cache
{
    /**
     * Delete all nodes from the cache.
     *
     * @return mixed
     */
    public function deleteAllNodes();

    /**
     * Delete node with the given ID from the cache.
     *
     * @param string $id
     *
     * @return mixed
     */
    public function deleteNodeById($id);

    /**
     * Find the node by the given ID in the cache.
     *
     * @param string $id
     *
     * @return mixed
     */
    public function findNodeById($id);

    /**
     * Find the node by the given MD5 in the cache.
     *
     * @param string $md5
     *
     * @return mixed
     */
    public function findNodeByMd5($md5);

    /**
     * Retrieve all node matching the given name in the cache.
     *
     * @param string $name
     *
     * @return mixed
     */
    public function findNodesByName($name);

    /**
     * Retrieve all nodes who have the given node as their parent.
     *
     * @param Node $node
     *
     * @return mixed
     */
    public function getNodeChildren(Node $node);

    /**
     * Retrieve the config for the account matched with the given email.
     *
     * @param string $email
     *
     * @return mixed
     */
    public function loadAccountConfig($email);

    /**
     * Save the config for the provided account.
     *
     * @param Account $account
     *
     * @return mixed
     */
    public function saveAccountConfig(Account $account);

    /**
     * Save the given node into the cache.
     *
     * @param Node $node
     *
     * @return mixed
     */
    public function saveNode(Node $node);
}
