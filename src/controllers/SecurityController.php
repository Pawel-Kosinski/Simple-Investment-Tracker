<?php

require_once __DIR__ . '/../repository/UserRepository.php';
require_once 'AppController.php';

class SecurityController extends AppController {

    private UserRepository $userRepository;

    public function __construct()
    {
        $this->userRepository = new UserRepository();
    }

    public function login(): void
    {
        if ($this->getCurrentUserId() !== null) {
            $this->redirect('/dashboard');
        }

        if (!$this->isPost()) {
            $this->render('login');
            return;
        }

        $email = trim(strtolower($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $this->render('login', ['message' => 'Wypełnij wszystkie pola']);
            return;
        }

        $user = $this->userRepository->findByEmail($email);

        if (!$user) {
            $this->render('login', ['message' => 'Nieprawidłowy email lub hasło']);
            return;
        }

        // DEBUG: log hash and verification result (remove in production)
        error_log('Login attempt for: ' . $email);
        error_log('Provided password length: ' . strlen($password));
        error_log('Stored hash (first 10 chars): ' . substr($user->getPassword(), 0, 10));
        error_log('password_verify result: ' . (password_verify($password, $user->getPassword()) ? 'true' : 'false'));

        if (!password_verify($password, $user->getPassword())) {
            $this->render('login', ['message' => 'Nieprawidłowy email lub hasło']);
            return;
        }

        if (!$user->isEnabled()) {
            $this->render('login', ['message' => 'Konto jest nieaktywne']);
            return;
        }

        // Zaloguj użytkownika
        $_SESSION['user_id'] = $user->getId();
        $_SESSION['user_email'] = $user->getEmail();
        $_SESSION['user_name'] = $user->getFullName();

        $this->redirect('/dashboard');
    }

    public function register(): void
    {
        if ($this->getCurrentUserId() !== null) {
            $this->redirect('/dashboard');
        }

        if (!$this->isPost()) {
            $this->render('register');
            return;
        }

        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $password2 = $_POST['password2'] ?? '';
        $firstname = trim($_POST['firstname'] ?? '');
        $lastname = trim($_POST['lastname'] ?? '');

        // Walidacja
        $errors = [];

        if (empty($email)) {
            $errors[] = 'Email jest wymagany';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Nieprawidłowy format email';
        } elseif ($this->userRepository->emailExists($email)) {
            $errors[] = 'Email jest już zajęty';
        }

        if (empty($password)) {
            $errors[] = 'Hasło jest wymagane';
        } elseif (strlen($password) < 6) {
            $errors[] = 'Hasło musi mieć minimum 6 znaków';
        }

        if ($password !== $password2) {
            $errors[] = 'Hasła nie są identyczne';
        }

        if (empty($firstname)) {
            $errors[] = 'Imię jest wymagane';
        }

        if (empty($lastname)) {
            $errors[] = 'Nazwisko jest wymagane';
        }

        if (!empty($errors)) {
            $this->render('register', [
                'message' => implode('<br>', $errors),
                'email' => $email,
                'firstname' => $firstname,
                'lastname' => $lastname
            ]);
            return;
        }

        // Utwórz użytkownika
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $user = new User($email, $hashedPassword, $firstname, $lastname);
        
        $this->userRepository->create($user);

        $this->render('login', ['message' => 'Rejestracja zakończona! Możesz się zalogować.', 'success' => true]);
    }

    public function logout(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        session_unset();
        session_destroy();
        
        $this->redirect('/login');
    }
}