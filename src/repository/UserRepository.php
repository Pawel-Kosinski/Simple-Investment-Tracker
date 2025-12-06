<?php

require_once 'Repository.php';
require_once __DIR__.'/../models/User.php';

class UserRepository extends Repository
{

    public function getUserByEmail(string $email): ?array
    {
        $query = $this->database->connect()->prepare('
            SELECT * FROM public.users WHERE email = :email
        ');
        $query->bindParam(':email', $email);
        $query->execute();

        $data = $query->fetch(PDO::FETCH_ASSOC);
        return $data;
    }

    public function addUser(\App\Models\User $user)
    {
        $query = $this->database->connect()->prepare('
            INSERT INTO public.users (firstname, lastname, email, password, bio) 
            VALUES (?, ?, ?, ?, ?);
        ');
        
        $query->execute([
            $user->firstname,
            $user->lastname,
            $user->email,
            $user->password_hash,
            $user->bio
        ]);
    }

    public function getUserDetailsId(\App\Models\User $user): int
    {
        $stmt = $this->database->connect()->prepare('
            SELECT * FROM public.users_details WHERE name = :name AND surname = :surname AND phone = :phone
        ');
        $name = $user->getName();
        $surname = $user->getSurname();
        $phone = $user->getPhone();
        $stmt->bindParam(':name', $name, PDO::PARAM_STR);
        $stmt->bindParam(':surname', $surname, PDO::PARAM_STR);
        $stmt->bindParam(':phone', $phone, PDO::PARAM_STR);
        $stmt->execute();

        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return $data['id'];
    }
}