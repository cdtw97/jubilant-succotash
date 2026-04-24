<?php
declare(strict_types=1);

namespace MyFrancis\Core;

use MyFrancis\Config\AppConfig;
use MyFrancis\Core\Exceptions\ViewException;
use MyFrancis\Security\Escaper;

final class View
{
    public function __construct(
        private readonly string $basePath,
        private readonly Escaper $escaper,
        private readonly AppConfig $appConfig,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $view, array $data = []): string
    {
        $filePath = $this->resolveViewPath($view);
        $app = $this->appConfig;
        $escaper = $this->escaper;

        extract($data, EXTR_SKIP);

        ob_start();

        try {
            require $filePath;
        } catch (\Throwable $exception) {
            ob_end_clean();

            throw $exception;
        }

        $content = ob_get_clean();

        if (! is_string($content)) {
            throw new ViewException('The view buffer could not be rendered.');
        }

        return $content;
    }

    private function resolveViewPath(string $view): string
    {
        if ($view === '' || str_contains($view, '..') || str_contains($view, "\0") || str_starts_with($view, '/') || str_contains($view, '\\')) {
            throw new ViewException('Invalid view name supplied.');
        }

        $normalizedView = str_replace('.', DIRECTORY_SEPARATOR, trim($view));
        $candidatePath = $this->basePath . DIRECTORY_SEPARATOR . $normalizedView . '.php';
        $realBasePath = realpath($this->basePath);
        $realCandidatePath = realpath($candidatePath);

        if ($realBasePath === false || $realCandidatePath === false || ! str_starts_with($realCandidatePath, $realBasePath)) {
            throw new ViewException(sprintf('View [%s] could not be resolved.', $view));
        }

        return $realCandidatePath;
    }
}
