<?php

require_once 'Repository.php';
require_once __DIR__ . '/../models/User.php';

class UserRepository extends Repository
{
    public function findAll(): array
    {
        $stmt = $this->database->prepare('SELECT * FROM users ORDER BY id');
        $stmt->execute();

        $users = [];
        while ($row = $stmt->fetch()) {
            $users[] = User::fromArray($row);
        }

        return $users;
    }

    public function findById(int $id): ?User
    {
        $stmt = $this->database->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch();
        return $row ? User::fromArray($row) : null;
    }

    public function findByEmail(string $email): ?User
    {
        $stmt = $this->database->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        $row = $stmt->fetch();
        return $row ? User::fromArray($row) : null;
    }

    public function create(User $user): int
    {
        $stmt = $this->database->prepare('
            INSERT INTO users (email, password, firstname, lastname, enabled)
            VALUES (:email, :password, :firstname, :lastname, :enabled)
        ');

        $stmt->execute([
            'email' => $user->getEmail(),
            'password' => $user->getPassword(),
            'firstname' => $user->getFirstname(),
            'lastname' => $user->getLastname(),
            'enabled' => $user->isEnabled() ? 't' : 'f'
        ]);

        return (int) $this->database->lastInsertId();
    }

    public function update(User $user): bool
    {
        $stmt = $this->database->prepare('
            UPDATE users 
            SET email = :email, 
                firstname = :firstname, 
                lastname = :lastname, 
                enabled = :enabled
            WHERE id = :id
        ');

        return $stmt->execute([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'firstname' => $user->getFirstname(),
            'lastname' => $user->getLastname(),
            'enabled' => $user->isEnabled() ? 't' : 'f'
        ]);
    }

    public function updatePassword(int $userId, string $hashedPassword): bool
    {
        $stmt = $this->database->prepare('
            UPDATE users SET password = :password WHERE id = :id
        ');

        return $stmt->execute([
            'id' => $userId,
            'password' => $hashedPassword
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->database->prepare('DELETE FROM users WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE email = :email';
        $params = ['email' => $email];

        if ($excludeId !== null) {
            $sql .= ' AND id != :id';
            $params['id'] = $excludeId;
        }

        $stmt = $this->database->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchColumn() > 0;
    }
}
