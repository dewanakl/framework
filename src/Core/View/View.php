<?php

namespace Core\View;

use Exception;
use Stringable;

/**
 * Template view dengan parent.
 *
 * @class View
 * @package \Core\View
 */
class View implements Stringable
{
    /**
     * Data dari setiap section.
     * 
     * @var array $section
     */
    private $section;

    /**
     * Variabel yang di inject.
     * 
     * @var array $variables
     */
    private $variables;

    /**
     * Nama parentnya.
     * 
     * @var string|null $parent
     */
    private $parent;

    /**
     * Content final.
     * 
     * @var Render|null $content
     */
    private $content;

    /**
     * Object compiler.
     * 
     * @var Compiler $compiler
     */
    private $compiler;

    /**
     * Pair section start and end.
     * 
     * @var string|null $pairSection
     */
    private $pairSection;

    /**
     * Init object.
     * 
     * @param Compiler $compiler
     * @return void
     */
    public function __construct(Compiler $compiler)
    {
        $this->compiler = $compiler;
    }

    /**
     * Magic to string.
     * 
     * @return string
     */
    public function __toString(): string
    {
        $content = $this->content->__toString();
        @clear_ob();

        $this->parent = null;
        $this->content = null;
        $this->section = [];
        $this->variables = [];

        return $content;
    }

    /**
     * Show html template.
     * 
     * @param string $name
     * @return void
     */
    public function show(string $name): void
    {
        $this->parent = null;
        $this->content = $this->including($name);

        if ($this->parent !== null) {
            $this->content = null;
            $this->show($this->parent);
        }
    }

    /**
     * Insert variabel.
     * 
     * @param array $variables
     * @return void
     */
    public function variables(array $variables = []): void
    {
        $this->variables = [...$this->variables ?? [], ...$variables];
    }

    /**
     * Set parent html.
     * 
     * @param string $name
     * @param array $variables
     * @return void
     */
    public function parents(string $name, array $variables = []): void
    {
        $this->parent = $name;
        $this->variables($variables);
    }

    /**
     * Masukan html opsional.
     * 
     * @param string $name
     * @return Render
     */
    public function including(string $name): Render
    {
        return render($this->compiler->compile($name)->getPathFileCache(), $this->variables ?? []);
    }

    /**
     * Bagian awal dari section.
     * 
     * @param string $name
     * @return void
     * 
     * @throws Exception
     */
    public function section(string $name): void
    {
        if ($this->pairSection !== null) {
            throw new Exception(sprintf('Unclose endsection %s', $this->pairSection));
        }

        $this->pairSection = $name;
        $this->section[$name] = null;
        ob_start();
    }

    /**
     * Tampilkan bagian dari html.
     * 
     * @param string $name
     * @return string|null
     */
    public function content(string $name): string|null
    {
        $content = @$this->section[$name] ?? null;
        $this->section[$name] = null;
        unset($this->section[$name]);

        return $content;
    }

    /**
     * Bagian akhir dari html.
     * 
     * @return void
     */
    public function endsection(): void
    {
        $this->section[$this->pairSection] = strval(ob_get_contents());
        ob_end_clean();
        $this->pairSection = null;
    }
}
