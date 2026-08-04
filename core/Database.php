<?php

namespace NovaSysCore;

use PDO;

class Database
{

    protected static ?PDO $connection = null;


    public static function connection(): PDO
    {

        if (self::$connection === null) {

            $config = Config::get('database');


            self::$connection = new PDO(

                "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",

                $config['username'],

                $config['password']

            );


            self::$connection->setAttribute(
                PDO::ATTR_ERRMODE,
                PDO::ERRMODE_EXCEPTION
            );


            self::$connection->setAttribute(
                PDO::ATTR_DEFAULT_FETCH_MODE,
                PDO::FETCH_ASSOC
            );

        }


        return self::$connection;

    }

}