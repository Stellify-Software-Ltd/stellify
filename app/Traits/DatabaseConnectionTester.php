<?php

namespace App\Traits;

trait DatabaseConnectionTester
{
    protected function testConnection()
    {
        $connection = false;
        try {
            if (!config('database.connections.mysql2.host') && !config('database.connections.pgsql2.host')) {
                return $connection;
            }
            if (config('database.connections.mysql2.host')) {
                \DB::connection('mysql2')->getPdo();
                $connection = 'mysql2';
            }
            if (config('database.connections.pgsql2.host')) {
                \DB::connection('pgsql2')->getPdo();
                $connection = 'pgsql2';
            }
            return $connection;
        } catch (\Exception $e) {
            return $connection;
        }
    }
}
