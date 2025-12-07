<?php

class AppController {

    protected function isGet(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'GET';
    }

    protected function isPost(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    protected function render(string $template = null, array $variables = [])
    {
        $templatePathPhtml = 'public/views/' . $template . '.phtml';
        $templatePathHtml = 'public/views/' . $template . '.html';
        $templatePath404 = 'public/views/404.html';

        $templatePath = null;
        $output = "";

        if (file_exists($templatePathPhtml)) {
            $templatePath = $templatePathPhtml;
        } elseif (file_exists($templatePathHtml)) {
            $templatePath = $templatePathHtml;
        }

        if ($templatePath && file_exists($templatePath)) {
            extract($variables);

            ob_start();
            include $templatePath;
            $output = ob_get_clean();
        } else {
            http_response_code(404);
            ob_start();
            include $templatePath404;
            $output = ob_get_clean();
        }
        
        echo $output;
    }

    protected function redirect(string $url): void
    {
        header("Location: " . $url);
        exit();
    }

    protected function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit();
    }

    protected function getCurrentUserId(): ?int
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return $_SESSION['user_id'] ?? null;
    }

    protected function requireAuth(): void
    {
        if ($this->getCurrentUserId() === null) {
            $this->redirect('/login');
        }
    }

    protected function getPostData(): array
    {
        return $_POST;
    }

    protected function getQueryParams(): array
    {
        return $_GET;
    }
}
