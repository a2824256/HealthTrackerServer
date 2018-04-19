<?php

/**
 * Created by PhpStorm.
 * User: alexleung
 * Date: 2018/4/19
 * Time: 下午9:04
 */
class RedisHandle
{
    private static $instance;

    public static function getInstance()
    {
        if (!is_object(self::$instance)) {
            try {
                self::$instance = new Redis();
                self::$instance->connect('127.0.0.1', 6379);
            } catch (Exception $exception) {
                echo $exception->getMessage() . "\n";
            }
        }
        return self::$instance;
    }
}