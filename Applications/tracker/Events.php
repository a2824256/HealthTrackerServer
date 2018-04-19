<?php

//declare(ticks=1);

use \GatewayWorker\Lib\Gateway;

include "RedisHandle.php";

class Events
{
    /**
     * 当客户端连接时触发
     * 如果业务不需此回调可以删除onConnect
     *
     * @param int $client_id 连接id
     */
    public static function onConnect($client_id)
    {
        echo $client_id . " connect success \n";
    }

    /**
     * @param int $client_id connect id
     * @param mixed $message
     */
    public static function onMessage($client_id, $message)
    {
        $res = json_decode($message, true);
        switch ($res["type"]) {
            case "socket":
                $redis = RedisHandle::getInstance();
                $cid = $redis->hMGet("did:" . $res['dev_id'], ["client_id"])['client_id'];
                if ($cid != null) {
                    $send_data["data"]["ac"] = [$res["ac"]["x"], $res["ac"]["y"], $res["ac"]["z"]];
                    $send_data["data"]["tm"] = $res["t1"] . "." . $res["t2"];
                    $send_data["data"]["hr"] = $res["h"];
                    $send_data["data"]["spo2"] = $res["s"];
                    $send_data["state"] = 4;
                    echo json_encode($send_data) . "\n";
                    Gateway::sendToClient($cid, json_encode($send_data));
                } else {
                    echo "die\n";
                }
                break;
            case "websocket":
                switch ($res["action"]) {
                    case "connect":
                        $rt_data["state"] = 2;
                        Gateway::sendToClient($client_id, json_encode($rt_data));
                        break;
                    case "login":
                        $rt_data = self::checkLogin($client_id, $res["account"], $res["password"]);
                        Gateway::sendToClient($client_id, json_encode($rt_data));
                        break;
                    case "register":
                        $rt_data = self::register($client_id, $res["account"], $res["password"], $res["dev_id"]);
                        Gateway::sendToClient($client_id, json_encode($rt_data));
                        break;
                    case "sign_out":
                        echo $client_id . " sign out\n";
                        $rt_data["state"] = 2;
                        self::signOut($client_id);
                        Gateway::sendToClient($client_id, json_encode($rt_data));
                        break;
                    default:
                        $rt_data["reason"] = "Undefine action!";
                        Gateway::sendToClient($client_id, json_encode($rt_data));
                }
                break;
            default:
                $rt_data["reason"] = "Undefine type!";
                Gateway::sendToClient($client_id, json_encode($rt_data));
                Gateway::closeCurrentClient();
                break;
        }
    }

    /**
     * @param int $client_id 连接id
     */
    public static function onClose($client_id)
    {
        echo "$client_id logout\n";
        self::signOut($client_id);
    }

    private static function signOut($client_id)
    {
        $redis = RedisHandle::getInstance();
        $info = $redis->hMGet("cid:" . $client_id, ['account', 'dev_id']);
        $redis->hdel($info['account'], $info['dev_id'], $client_id);
    }


    private static function checkLogin($client_id, $account, $password)
    {
        $redis = RedisHandle::getInstance();
        $rt_data = [];
        //1 Does user sign in
        if ($redis->exists("aid:" . $account)) {
            //2. If password is correct
            $rpass = $redis->hGetAll("aid:" . $account)['password'];
            if ($rpass == $password) {
                //3. login success
                $dev_id = $redis->hMGet("aid:" . $account, ["dev_id"])['dev_id'];
                self::matchUser($account, $client_id, $dev_id);
                $rt_data["reason"] = "Login success.";
                $rt_data["dev_id"] = $dev_id;
                $rt_data["account"] = $account;
                $rt_data["state"] = 3;
            } else {
                $rt_data["reason"] = "Password is not correct.";
            }
        } else {
            $rt_data["reason"] = "Account or password error.";
        }
        return $rt_data;
    }

    private static function matchUser($account, $client_id, $dev_id)
    {
        $redis = RedisHandle::getInstance();
        $redis->hMset("did:" . $dev_id, array("account" => (string)$account, "client_id" => (string)$client_id));
//        $arr2 = ["account" => $account, "dev_id" => $dev_id];
        $redis->hMset("cid:" . $client_id, array("account" => (string)$account, "dev_id" => (string)$dev_id));
    }

    private static function register($client_id, $account, $password, $dev_id)
    {
        $redis = RedisHandle::getInstance();
        if ($redis->exists("aid:" . $account)) {
            $rt_data["reason"] = "This account already exist.";
            return $rt_data;
        } else {
            $redis->hMset("aid:" . $account, ["password" => $password, "client_id" => $client_id, "dev_id" => $dev_id]);
            self::matchUser($account, $client_id, $dev_id);
            $rt_data["reason"] = "Register success.";
            $rt_data["dev_id"] = $dev_id;
            $rt_data["account"] = $account;
            $rt_data["state"] = 3;
            return $rt_data;
        }
    }
}
