<?php
/**
 * @author Alex Phillips <ahp118@gmail.com>
 * Date: 7/10/15
 * Time: 3:36 PM
 */

namespace CloudDrive;

use ArrayAccess;
use IteratorAggregate;
use JsonSerializable;
use Countable;
use Utility\Traits\Bag;

class Node implements ArrayAccess, IteratorAggregate, JsonSerializable, Countable
{
    use Bag;
}
