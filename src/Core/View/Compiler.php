<?php

namespace Core\View;

use Exception;

/**
 * Compile .kita file.
 *
 * @class Compiler
 * @package \Core\View
 */
class Compiler
{
    private const pairingTag = [
        'if',
        'for',
        'foreach',
        'isset',
        'empty',
        'section',
        'auth',
        'guest',
        'error',
        'original',
        'php'
    ];

    private const selfClosing = [
        'extend',
        'include',
        'content',
        'elseif',
        'else',
        'continue',
        'break',
        'csrf',
        'method'
    ];

    private const echoTag = [
        '{{' => '}}',
        '{!!' => '!!}'
    ];

    private const commentTag = [
        '{{--' => '--}}',
        '<!--' => '-->'
    ];

    /**
     * Content .kita parsing
     * 
     * @var string $content
     */
    private $content;

    /**
     * Export hasilnya.
     * 
     * @return string
     */
    public function export()
    {
        return $this->content;
    }

    /**
     * Compile file .kita
     * 
     * @param string $file
     * @return void
     */
    public function compile(string $file): void
    {
        $this->content = file_get_contents(sprintf('%s/resources/views/%s.kita.php', basepath(), $file), true);

        if (!(bool)$this->content) {
            throw new Exception(sprintf('Can\'t open file [%s.kita.php]', $file));
        }

        foreach (self::commentTag as $key => $value) {
            $this->content = strval(preg_replace(sprintf('/%s(.*)%s/s', $key, $value), '', strval($this->content)));
        }

        foreach (self::selfClosing as $value) {
            $this->content = $this->{$value . 'Tag'}($this->content);
        }

        foreach (self::pairingTag as $value) {
            $this->content = $this->{$value . 'CloseTag'}($this->{$value . 'OpenTag'}($this->content));
        }

        foreach (self::echoTag as $key => $value) {
            $this->content = $this->echoTag($this->content, $key, $value);
        }
    }

    private function echoTag(string $content, string $key, string $value): string
    {
        return strval(preg_replace_callback(
            sprintf('/%s\s*(.+?)\s*%s/s', $key, $value),
            function (array $matches) use ($key, $value): string {
                if ($key == '{{' && $value == '}}') {
                    $matches[1] = sprintf('e(%s)', strval($matches[1]));
                }

                return sprintf('<?php echo %s ?>', strval($matches[1]));
            },
            $content
        ));
    }

    private function ifOpenTag(string $content): string
    {
        return strval(preg_replace_callback(
            '/(?<!@)@if\s*\((.*)\)(?(?=\w|)(?!\w)|)/m',
            function (array $matches): string {
                return sprintf('<?php if (%s) : ?>', strval($matches[1]));
            },
            $content
        ));
    }

    private function forOpenTag(string $content): string
    {
        return strval(preg_replace_callback(
            '/(?<!@)@for\s*\((.*)\)(?(?=\w|)(?!\w)|)/m',
            function (array $matches): string {
                return sprintf('<?php for (%s) : ?>', strval($matches[1]));
            },
            $content
        ));
    }

    private function foreachOpenTag(string $content): string
    {
        return preg_replace_callback(
            '/(?<!@)@foreach\s*\((.*)\)(?(?=\w|)(?!\w)|)/m',
            function (array $matches): string {
                return sprintf('<?php foreach(%s) : ?>', strval($matches[1]));
            },
            $content
        );
    }

    private function sectionOpenTag(string $content): string
    {
        return strval(preg_replace_callback(
            '/(?<!@)@section\((.*?)\)(?(?=\w|)(?!\w)|)/s',
            function (array $matches): string {
                return sprintf('<?php section(%s) ?>', strval($matches[1]));
            },
            $content
        ));
    }

    private function authOpenTag(string $content): string
    {
        return strval(preg_replace_callback(
            '/(?<!@)@auth(?(?=\w|)(?!\w)|)/s',
            function (): string {
                return '<?php if (auth()->check()) : ?>';
            },
            $content
        ));
    }

    private function guestOpenTag(string $content): string
    {
        return strval(preg_replace_callback(
            '/(?<!@)@guest(?(?=\w|)(?!\w)|)/s',
            function (): string {
                return '<?php if (!auth()->check()) : ?>';
            },
            $content
        ));
    }

    private function phpOpenTag(string $content): string
    {
        return strval(preg_replace_callback(
            '/(?<!@)@php(?(?=\w|)(?!\w)|)/s',
            function (): string {
                return '<?php';
            },
            $content
        ));
    }

    private function ifCloseTag(string $content): string
    {
        return strval(preg_replace_callback(
            '/(?<!@)@endif(?(?=\w|)(?!\w)|)/s',
            function (): string {
                return '<?php endif ?>';
            },
            $content
        ));
    }

    private function forCloseTag(string $content): string
    {
        return strval(preg_replace_callback(
            '/(?<!@)@endfor(?(?=\w|)(?!\w)|)/s',
            function (): string {
                return '<?php endfor ?>';
            },
            $content
        ));
    }

    private function foreachCloseTag(string $content): string
    {
        return strval(preg_replace_callback(
            '/(?<!@)@endforeach(?(?=\w|)(?!\w)|)/s',
            function (): string {
                return '<?php endforeach ?>';
            },
            $content
        ));
    }

    private function sectionCloseTag(string $content): string
    {
        return strval(preg_replace_callback(
            '/(?<!@)@endsection\((.*?)\)(?(?=\w|)(?!\w)|)/s',
            function (array $matches): string {
                return sprintf('<?php endsection(%s) ?>', strval($matches[1]));
            },
            $content
        ));
    }

    private function authCloseTag(string $content): string
    {
        return strval(preg_replace_callback(
            '/(?<!@)@endauth(?(?=\w|)(?!\w)|)/s',
            function (): string {
                return '<?php endif ?>';
            },
            $content
        ));
    }

    private function guestCloseTag(string $content): string
    {
        return strval(preg_replace_callback(
            '/(?<!@)@endguest(?(?=\w|)(?!\w)|)/s',
            function (): string {
                return '<?php endif ?>';
            },
            $content
        ));
    }

    private function phpCloseTag(string $content): string
    {
        return strval(preg_replace_callback(
            '/(?<!@)@endphp(?(?=\w|)(?!\w)|)/s',
            function (): string {
                return '?>';
            },
            $content
        ));
    }

    private function extendTag(string $content): string
    {
        return strval(preg_replace_callback(
            '/(?<!@)@extend\((.*?)\)(?(?=\w|)(?!\w)|)/s',
            function (array $matches): string {
                return sprintf('<?php parents(%s) ?>', strval($matches[1]));
            },
            $content
        ));
    }

    private function includeTag(string $content): string
    {
        return strval(preg_replace_callback(
            '/(?<!@)@include\((.*?)\)(?(?=\w|)(?!\w)|)/s',
            function (array $matches): string {
                return sprintf('<?php including(%s) ?>', strval($matches[1]));
            },
            $content
        ));
    }

    private function contentTag(string $content): string
    {
        return strval(preg_replace_callback(
            '/(?<!@)@content\((.*?)\)(?(?=\w|)(?!\w)|)/s',
            function (array $matches): string {
                return sprintf('<?php echo content(%s) ?>', strval($matches[1]));
            },
            $content
        ));
    }

    private function elseifTag(string $content): string
    {
        return strval(preg_replace_callback(
            '/(?<!@)@elseif\s*\((.*)\)(?(?=\w|)(?!\w)|)/m',
            function (array $matches): string {
                return sprintf('<?php elseif (%s) : ?>', strval($matches[1]));
            },
            $content
        ));
    }

    private function elseTag(string $content): string
    {
        return strval(preg_replace_callback(
            '/(?<!@)@else(?(?=\w|)(?!\w)|)/s',
            function (): string {
                return '<?php else : ?>';
            },
            $content
        ));
    }
}
