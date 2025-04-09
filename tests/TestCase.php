<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    
    /**
     * Use transactions for faster tests
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Wrap each test in a transaction for performance
        $this->beginDatabaseTransaction();
    }
    
    /**
     * Start a database transaction for the test
     */
    protected function beginDatabaseTransaction()
    {
        $database = app()->make('db');
        
        foreach ($this->connectionsToTransact() as $name) {
            $connection = $database->connection($name);
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
