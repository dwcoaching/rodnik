<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\DatabaseTransactionsManager;
use Illuminate\Support\Uri;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /** @param Uri|string $uri */
    protected function prepareUrlForRequest($uri): string
    {
        $uri = $uri instanceof Uri ? $uri->value() : $uri;

        return str_starts_with($uri, 'http://') || str_starts_with($uri, 'https://')
            ? $uri
            : mb_rtrim(url('/'), '/').'/'.mb_ltrim($uri, '/');
    }
    
    /**
     * Use transactions for faster tests
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Wrap each test in a transaction for performance
        if (app('db')->connection()->transactionLevel() === 0) {
            $this->beginDatabaseTransaction();
        }
    }
    
    /**
     * Start a database transaction for the test
     */
    protected function beginDatabaseTransaction()
    {
        $database = app()->make('db');
        $this->app->instance('db.transactions', $transactionsManager = new DatabaseTransactionsManager($this->connectionsToTransact()));
        
        foreach ($this->connectionsToTransact() as $name) {
            $connection = $database->connection($name);
            $connection->setTransactionManager($transactionsManager);
            $connection->beginTransaction();
            
            $this->beforeApplicationDestroyed(function () use ($connection) {
                $connection->rollBack();
            });
        }
    }
    
    /**
     * The database connections that should be transacted
     */
    protected function connectionsToTransact()
    {
        return [null];
    }
}
