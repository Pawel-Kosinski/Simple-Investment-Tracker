<?php

require_once __DIR__.'/../../Database.php';

if (!class_exists('Repository')) {
    class Repository {
        protected $database;

        public function __construct()
        {
            $this->database = Database::getInstance()->connect();
        }
    }
}
