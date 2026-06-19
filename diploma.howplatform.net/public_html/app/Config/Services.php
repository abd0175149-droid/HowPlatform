<?php

namespace Config;

use CodeIgniter\Config\BaseService;

class Services extends BaseService
{
    public static function crypto(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('crypto');
        }
        return new \App\Services\CryptoService();
    }

    public static function subscriptionService(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('subscriptionService');
        }
        return new \App\Services\SubscriptionService(db_connect());
    }

    /**
     * سياسة الوصول للملفات (عرض/تنزيل + السقوف)
     */
public static function fileAccessPolicy(bool $getShared = true): \App\Services\FileAccessPolicy
{
    if ($getShared) {
        return static::getSharedInstance('fileAccessPolicy');
    }
    $db   = \Config\Database::connect();
    $subs = new \App\Services\SubscriptionService($db);
    return new \App\Services\FileAccessPolicy($db, $subs);
}


    public static function featureGate(bool $getShared = true): \App\Libraries\FeatureGate
    {
        if ($getShared) {
            return static::getSharedInstance('featureGate');
        }
        return new \App\Libraries\FeatureGate();
    }
}
