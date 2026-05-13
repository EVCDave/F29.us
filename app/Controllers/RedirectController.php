<?php
declare(strict_types=1);

class RedirectController
{
    public function handle(array $params = []): void
    {
        RedirectService::handleSlug($params['slug'] ?? '');
    }
}
