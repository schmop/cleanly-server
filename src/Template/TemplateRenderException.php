<?php

declare(strict_types=1);

namespace App\Template;

use Twig\Environment;
use Twig\Error\Error as TwigError;

class TemplateRenderException extends \RuntimeException
{
    /**
     * Run a closure and translate Twig render failures into a domain-specific
     * TemplateRenderException.
     *
     * @template T
     * @param \Closure(): T $fn
     * @return T
     * @throws TemplateRenderException
     */
    public static function wrap(\Closure $fn): mixed
    {
        try {
            return $fn();
        } catch (TwigError $e) {
            throw new self($e->getMessage(), 0, $e);
        }
    }

    /**
     * @param array<string, mixed> $context
     * @throws TemplateRenderException
     */
    public static function render(Environment $twig, string $template, array $context = []): string
    {
        return self::wrap(static fn() => $twig->render($template, $context));
    }
}
