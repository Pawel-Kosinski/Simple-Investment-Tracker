<?php
declare(strict_types=1);

namespace App\Models;

class User
{
    public string $firstname = '';
    public string $lastname = '';
    public string $email = '';
    // repository can set this directly before inserting
    public string $password_hash = '';
    public string $bio = '';
    public string $phone = '';
    public string $created_at = '';
    public string $updated_at = '';

    public function __construct()
    {
        $this->created_at = $this->updated_at = date('Y-m-d H:i:s');
    }

    public function getName(): string
    {
        return $this->firstname;
    }

    public function getSurname(): string
    {
        return $this->lastname;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }
}
