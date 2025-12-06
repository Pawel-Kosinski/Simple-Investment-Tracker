<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repository/UserRepository.php';
require_once __DIR__ . '/../models/User.php';


class SecurityController extends AppController {

    private $userRepository;

    public function __construct() {
        parent::__construct();
        $this->userRepository = new UserRepository();
    }

    // TODO dekarator, który definiuje, jakie metody HTTP są dostępne
    public function login() {

        if($this->isGet()) {
            return $this->render("login");
        } 

        $email = $_POST["email"] ?? '';
        $password = $_POST["password"] ?? '';

        // Log login attempt without sending output to client (prevents "headers already sent" errors)
        error_log('Login attempt: ' . $email);
        // TODO get data from database

        // return $this->render("dashboard", ['cards' => []]);

        $url = "http://$_SERVER[HTTP_HOST]";
        header("Location: {$url}/dashboard");
        exit();
    }

    public function register() {
        // TODO pobranie z formularza email i hasła
        // TODO insert do bazy danych
        // TODO zwrocenie informajci o pomyslnym zarejstrowaniu

        if ($this->isGet()) {
            return $this->render("register");
        }

        $email = $_POST["email"] ?? '';
        $password1 = $_POST["password1"] ?? '';
        $password2 = $_POST["password2"] ?? '';
        $firstname = $_POST["firstname"] ?? '';
        $lastname = $_POST["lastname"] ?? '';

        if ($password1 !== $password2) {
            return $this->render("register", ["message" => "Podane hasła nie są identyczne"]);
        }

        $hashedPassword = password_hash($password1, PASSWORD_BCRYPT);

        // Create User model and populate fields expected by UserRepository
        $user = new \App\Models\User();
        $user->firstname = $firstname;
        $user->lastname = $lastname;
        $user->email = $email;
        $user->password_hash = $hashedPassword;
        $user->bio = '';

        $this->userRepository->addUser($user);
        // TODO insert to database user

        return $this->render("login", ["message" => "Zarejestrowano uytkownika ".$email]);
    }
}