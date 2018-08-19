<?php

namespace Sinpe\Cache;

use Psr\Cache\InvalidArgumentException as Psr6Interface;
use Psr\SimpleCache\InvalidArgumentException as Psr16Interface;

/**
 * Class InvalidArgumentException.
 *
 * @author    wupinglong <18222544@qq.com>
 * @copyright 2018 Sinpe, Inc.
 *
 * @see      http://www.sinpe.com/
 */
class InvalidArgumentException extends \InvalidArgumentException implements Psr6Interface, Psr16Interface
{
}
