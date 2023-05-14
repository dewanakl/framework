<?php

namespace Core\View;

use Closure;
use Exception;

/**
 * Compile .kita file.
 *
 * @class Compiler
 * @package \Core\View
 */
class Compiler
{
    /**
     * Pairing Tag kita syntax
     * 
     * @var array
     */
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
        'php'
    ];

    /**
     * Self Close Tag kita syntax
     * 
     * @var array
     */
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

    /**
     * Echo tag kita syntax
     * 
     * @var array
     */
    private const echoTag = [
        '{{' => '}}',
        '{!!' => '!!}'
    ];

    /**
     * Comment tag kita syntax
     * 
     * @var array
     */
    private const commentTag = [
        '{{--' => '--}}',
        '<!--' => '-->'
    ];

    /**
     * Tmp original blocks.
     * 
     * @var array $originalBlocks
     */
    private $orignalBlocks;

    /**
     * UID from blocks.
     * 
     * @var string $uid
     */
    private $uid;

    /**
     * Path file cache.
     * 
     * @var string $cachePath
     */
    private $cachePath;

    /**
     * Init object.
     * 
     * @return void
     */
    public function __construct()
    {
        $this->uid = md5(random_bytes(5));
        $this->orignalBlocks = [];
        $this->cachePath = '/cache/views/';
    }

    /**
     * Dapatkan path file cache.
     * 
     * @return string
     */
    public function getPathFileCache(): string
    {
        return $this->cachePath;
    }

    /**
     * Compile file .kita
     * 
     * @param string $file
     * @return Compiler
     */
    public function compile(string $file): Compiler
    {
        $this->cachePath .= $file;

        if (!debug()) {
            return $this;
        }

        $content = $this->getContent($file);
        $content = $this->orignalBlocks($content);

        // Comment Tag
        foreach (self::commentTag as $key => $value) {
            $content = strval(preg_replace(sprintf('/%s(.*?)%s/s', $key, $value), '', strval($content)));
        }

        // Self Close Tag
        foreach (self::selfClosing as $value) {
            $content = $this->{$value . 'Tag'}($content);
        }

        // Pairing Tag
        foreach (self::pairingTag as $value) {
            $content = $this->{$value . 'CloseTag'}($this->{$value . 'OpenTag'}($content));
        }

        $content = $this->echoTag($content);
        $content = $this->storeOriginalBlocks($content);

        $this->putContent($content);
        return $this;
    }

    /**
     * Override function and extract index 1 of matches.
     * 
     * @param string $pattern
     * @param Closure $callback
     * @param string $subject
     * 
     * @return string
     */
    private function pregReplaceCallback(string $pattern, Closure $callback, string $subject): string
    {
        return strval(preg_replace_callback(
            $pattern,
            function (array $matches) use ($callback): string {
                return $callback(strval($matches[1]));
            },
            $subject
        ));
    }

    /**
     * Open a file.
     * 
     * @param string $file
     * @return string
     * 
     * @throws Exception
     */
    private function getContent(string $file): string
    {
        $content = file_get_contents(sprintf(basepath() . '/resources/views/%s.kita.php', $file), true);

        if (!(bool)$content) {
            throw new Exception(sprintf('Can\'t open file [%s.kita.php]', $file));
        }

        return $content;
    }

    /**
     * Save a file. If folder not exist, create it.
     * 
     * @param string $content
     * @return void
     */
    private function putContent(string $content): void
    {
        $file = basepath() . $this->cachePath;
        $arr = explode('/', $file);
        $folder = implode('/', array_splice($arr, 0, -1));

        if (!is_dir($folder)) {
            mkdir($folder, 0777, true);
        }

        if (!(bool) file_put_contents($file . '.php', $content)) {
            throw new Exception(sprintf('Can\'t save file [%s.kita.php]', $this->cachePath));
        }
    }

    /**
     * Save temporary orignal blocks.
     * 
     * @param string $content
     * 
     * @return string
     */
    private function orignalBlocks(string $content): string
    {
        return $this->pregReplaceCallback(
            '/(?<!@)@original(.*?)@endoriginal/s',
            function (string $matches): string {
                return sprintf('@__%s__%s__@', $this->uid, array_push($this->orignalBlocks, strval($matches[1])) - 1);
            },
            $content
        );
    }

    private function echoTag(string $content): string
    {
        foreach (self::echoTag as $key => $value) {
            $content = $this->pregReplaceCallback(
                sprintf('/%s\s*(.+?)\s*%s/s', $key, $value),
                function (string $matches) use ($key, $value): string {
                    if ($key == '{{' && $value == '}}') {
                        $matches = sprintf('e(%s)', $matches);
                    }

                    return sprintf('<?php echo %s ?>', $matches);
                },
                $content
            );
        }

        return $content;
    }

    private function storeOriginalBlocks(string $content): string
    {
        if (!empty($this->orignalBlocks)) {
            $content = $this->pregReplaceCallback(
                sprintf('/@__%s__%s__@/s', $this->uid, '(\d+)'),
                function (string $matches): string {
                    return $this->orignalBlocks[intval($matches)];
                },
                $content
            );

            $this->orignalBlocks = [];
        }

        return $content;
    }

    private function ifOpenTag(string $content): string
    {
        return $this->pregReplaceCallback(
            '/(?<!@)@if\s*\((.*)\)(?(?=\w|)(?!\w)|)/m',
            function (string $matches): string {
                return sprintf('<?php if (%s) : ?>', $matches);
            },
            $content
        );
    }

    private function issetOpenTag(string $content): string
    {
        return $this->pregReplaceCallback(
            '/(?<!@)@isset\s*\((.*)\)(?(?=\w|)(?!\w)|)/m',
            function (string $matches): string {
                return sprintf('<?php if (isset(%s)) : ?>', $matches);
            },
            $content
        );
    }

    private function emptyOpenTag(string $content): string
    {
        return $this->pregReplaceCallback(
            '/(?<!@)@empty\s*\((.*)\)(?(?=\w|)(?!\w)|)/m',
            function (string $matches): string {
                return sprintf('<?php if (empty(%s)) : ?>', $matches);
            },
            $content
        );
    }

    private function errorOpenTag(string $content): string
    {
        return $this->pregReplaceCallback(
            '/(?<!@)@error\s*\((.*)\)(?(?=\w|)(?!\w)|)/m',
            function (string $matches): string {
                return sprintf('<?php if ($pesan = error(%s)) : ?>', $matches);
            },
            $content
        );
    }

    private function forOpenTag(string $content): string
    {
        return $this->pregReplaceCallback(
            '/(?<!@)@for\s*\((.*)\)(?(?=\w|)(?!\w)|)/m',
            function (string $matches): string {
                return sprintf('<?php for (%s) : ?>', $matches);
            },
            $content
        );
    }

    private function foreachOpenTag(string $content): string
    {
        return $this->pregReplaceCallback(
            '/(?<!@)@foreach\s*\((.*)\)(?(?=\w|)(?!\w)|)/m',
            function (string $matches): string {
                return sprintf('<?php foreach(%s) : ?>', $matches);
            },
            $content
        );
    }

    private function sectionOpenTag(string $content): string
    {
        return $this->pregReplaceCallback(
            '/(?<!@)@section\((.*?)\)(?(?=\w|)(?!\w)|)/s',
            function (string $matches): string {
                return sprintf('<?php section(%s) ?>', $matches);
            },
            $content
        );
    }

    private function authOpenTag(string $content): string
    {
        return $this->pregReplaceCallback(
            '/(?<!@)@auth(?(?=\w|)(?!\w)|)/s',
            function (): string {
                return '<?php if (auth()->check()) : ?>';
            },
            $content
        );
    }

    private function guestOpenTag(string $content): string
    {
        return $this->pregReplaceCallback(
            '/(?<!@)@guest(?(?=\w|)(?!\w)|)/s',
            function (): string {
                return '<?php if (!auth()->check()) : ?>';
            },
            $content
        );
    }

    private function phpOpenTag(string $content): string
    {
        return $this->pregReplaceCallback(
            '/(?<!@)@php(?(?=\w|)(?!\w)|)/s',
            function (): string {
                return '<?php';
            },
            $content
        );
    }

    private function ifCloseTag(string $content): string
    {
        return $this->pregReplaceCallback(
            '/(?<!@)@endif(?(?=\w|)(?!\w)|)/s',
            function (): string {
                return '<?php endif ?>';
            },
            $content
        );
    }

    private function issetCloseTag(string $content): string
    {
        return $this->pregReplaceCallback(
            '/(?<!@)@endisset(?(?=\w|)(?!\w)|)/s',
            function (): string {
                return '<?php endif ?>';
            },
            $content
        );
    }

    private function emptyCloseTag(string $content): string
    {
        return $this->pregReplaceCallback(
            '/(?<!@)@endempty(?(?=\w|)(?!\w)|)/s',
            function (): string {
                return '<?php endif ?>';
            },
            $content
        );
    }
    private function errorCloseTag(string $content): string
    {
        return $this->pregReplaceCallback(
            '/(?<!@)@enderror(?(?=\w|)(?!\w)|)/s',
            function (): string {
                return '<?php endif ?>';
            },
            $content
        );
    }

    private function forCloseTag(string $content): string
    {
        return $this->pregReplaceCallback(
            '/(?<!@)@endfor(?(?=\w|)(?!\w)|)/s',
            function (): string {
                return '<?php endfor ?>';
            },
            $content
        );
    }

    private function foreachCloseTag(string $content): string
    {
        return $this->pregReplaceCallback(
            '/(?<!@)@endforeach(?(?=\w|)(?!\w)|)/s',
            function (): string {
                return '<?php endforeach ?>';
            },
            $content
        );
    }

    private function sectionCloseTag(string $content): string
    {
        return $this->pregReplaceCallback(
            '/(?<!@)@endsection(?(?=\w|)(?!\w)|)/s',
            function (): string {
                return '<?php endsection() ?>';
            },
            $content
        );
    }

    private function authCloseTag(string $content): string
    {
        return $this->pregReplaceCallback(
            '/(?<!@)@endauth(?(?=\w|)(?!\w)|)/s',
            function (): string {
                return '<?php endif ?>';
            },
            $content
        );
    }

    private function guestCloseTag(string $content): string
    {
        return $this->pregReplaceCallback(
            '/(?<!@)@endguest(?(?=\w|)(?!\w)|)/s',
            function (): string {
                return '<?php endif ?>';
            },
            $content
        );
    }

    private function phpCloseTag(string $content): string
    {
        return $this->pregReplaceCallback(
            '/(?<!@)@endphp(?(?=\w|)(?!\w)|)/s',
            function (): string {
                return '?>';
            },
            $content
        );
    }

    private function extendTag(string $content): string
    {
        return $this->pregReplaceCallback(
            '/(?<!@)@extend\((.*?)\)(?(?=\w|)(?!\w)|)/s',
            function (string $matches): string {
                return sprintf('<?php parents(%s) ?>', $matches);
            },
            $content
        );
    }

    private function includeTag(string $content): string
    {
        return $this->pregReplaceCallback(
            '/(?<!@)@include\((.*?)\)(?(?=\w|)(?!\w)|)/s',
            function (string $matches): string {
                return sprintf('<?php including(%s) ?>', $matches);
            },
            $content
        );
    }

    private function contentTag(string $content): string
    {
        return $this->pregReplaceCallback(
            '/(?<!@)@content\((.*?)\)(?(?=\w|)(?!\w)|)/s',
            function (string $matches): string {
                return sprintf('<?php echo content(%s) ?>', $matches);
            },
            $content
        );
    }

    private function elseifTag(string $content): string
    {
        return $this->pregReplaceCallback(
            '/(?<!@)@elseif\s*\((.*)\)(?(?=\w|)(?!\w)|)/m',
            function (string $matches): string {
                return sprintf('<?php elseif (%s) : ?>', $matches);
            },
            $content
        );
    }

    private function elseTag(string $content): string
    {
        return $this->pregReplaceCallback(
            '/(?<!@)@else(?(?=\w|)(?!\w)|)/s',
            function (): string {
                return '<?php else : ?>';
            },
            $content
        );
    }

    private function continueTag(string $content): string
    {
        return $this->pregReplaceCallback(
            '/(?<!@)@continue(?(?=\w|)(?!\w)|)/s',
            function (): string {
                return '<?php continue ?>';
            },
            $content
        );
    }

    private function breakTag(string $content): string
    {
        return $this->pregReplaceCallback(
            '/(?<!@)@break(?(?=\w|)(?!\w)|)/s',
            function (): string {
                return '<?php break ?>';
            },
            $content
        );
    }

    private function csrfTag(string $content): string
    {
        return $this->pregReplaceCallback(
            '/(?<!@)@csrf(?(?=\w|)(?!\w)|)/s',
            function (): string {
                return '<?php echo csrf() ?>';
            },
            $content
        );
    }

    private function methodTag(string $content): string
    {
        return $this->pregReplaceCallback(
            '/(?<!@)@method\s*\((.*)\)(?(?=\w|)(?!\w)|)/m',
            function (string $matches): string {
                return sprintf('<?php echo method(%s) ?>', $matches);
            },
            $content
        );
    }
}
