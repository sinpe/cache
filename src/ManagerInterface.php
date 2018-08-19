<?php 

namespace Sinpe\Cache;

/**
 * Interface ManagerInterface
 *
 * @link   http://www.sinpe.com/
 * @author Sinpe, Inc.
 * @author 18222544@qq.com
 */
interface ManagerInterface
{
    /**
     * 设置调试状态
     *
     * @param boolean $debug 是否调试
     * 
     * @return boolean
     */
    public function debug($debug=null);

    /**
     * Return an instance with the tags.
     *
     * @param string $tags 键
     * 
     * @return static
     */
    public function withTags($tags);

}
