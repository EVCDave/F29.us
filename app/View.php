<?php
declare(strict_types=1);

class View
{
    public static function render(string $template, array $data = [], string $layout = 'main'): void
    {
        $viewFile = APP_PATH . '/Views/' . $template . '.php';

        if (!file_exists($viewFile)) {
            throw new RuntimeException("View not found: {$template}");
        }

        // Capture the view's output into $content
        $content = self::capture($viewFile, $data);

        if ($layout !== '') {
            $layoutFile = APP_PATH . '/Views/layouts/' . $layout . '.php';
            if (!file_exists($layoutFile)) {
                throw new RuntimeException("Layout not found: {$layout}");
            }
            // Layout receives $content and all $data vars
            extract($data);
            include $layoutFile;
        } else {
            echo $content;
        }
    }

    private static function capture(string $file, array $data): string
    {
        extract($data);
        ob_start();
        include $file;
        return (string) ob_get_clean();
    }

    public static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
