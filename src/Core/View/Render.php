<?php

namespace Core\View;

use Stringable;

/**
 * Tampilkan html dan juga injek variabel.
 *
 * @class Render
 * @package \Core\View
 */
class Render implements Stringable
{
    /**
     * Path file html.
     *
     * @var string|null $path
     */
    private $path;

    /**
     * Isi file html.
     *
     * @var string $path
     */
    private $content;

    /**
     * Injek variabel.
     *
     * @var array $variables
     */
    private $variables;

    /**
     * Init objek.
     *
     * @param string $path
     * @return void
     */
    public function __construct(string $path)
    {
        $this->path = base_path($path . '.php');
    }

    /**
     * Magic to string.
     *
     * @return string
     */
    public function __toString(): string
    {
        $content = $this->content;

        $this->path = null;
        $this->content = null;
        $this->variables = [];

        return $content;
    }

    /**
     * Set variabel ke template html.
     *
     * @param array $variables
     * @return void
     */
    public function setData(array $variables = []): void
    {
        $this->variables = $variables;
    }

    /**
     * Eksekusi template html.
     *
     * @return void
     */
    public function show(): void
    {
        $this->content = (function ($__path, $__data): string {
            ob_start();

            if ($__data) {
                extract($__data, EXTR_SKIP);
            }

            require_once $__path;
            $content = ob_get_contents();

            ob_end_clean();
            unset($__path, $__data);
            return strval($content);
        })($this->path, $this->variables);
    }
}
