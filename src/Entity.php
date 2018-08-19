<?php

namespace Sinpe\Cache;

use Illuminate\Database\Eloquent\Model;

/**
 * Entity class
 * 
 * 缓存标签数据库永久存储
 * 
 * 两个字段：key存储键，items键下的子项
 * 
 * @author    wupinglong <18222544@qq.com>
 * @copyright 2018 Sinpe, Inc.
 * @link      http://www.sinpe.com/
 */
class Entity extends Model
{
    /**
     * 表名
     *
     * @var string
     */
    protected $table = 'cache_tags';

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'items' => 'array'
    ];

}

