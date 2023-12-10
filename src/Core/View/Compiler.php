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
     * Pairing Tag kita syntax.
     *
     * @var array<int, string> pairingTag
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
        'flash',
        'php'
    ];

    /**
     * Self Close Tag kita syntax.
     *
     * @var array<int, string> selfClosing
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
     * Echo tag kita syntax.
     *
     * @var array<string, string> echoTag
     */
    private const echoTag = [
        '{{' => '}}',
        '{!!' => '!!}'
    ];

    /**
     * Comment tag kita syntax.
     *
     * @var array<string, string> commentTag
     */
    private const commentTag = [
        '{{--' => '--}}',
        // '<!--' => '-->' // If enable, inconsistencies line
    ];

    /**
     * Temporary original blocks.
     *
     * @var array<int, string> $originalBlocks
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
     * Original cache path.
     *
     * @var string $originCachePath
     */
    private $originCachePath;

    /**
     * Origin view folder.
     *
     * @var string|null $originView
     */
    public static $originView;

    /**
     * Init object.
     *
     * @param string|null $path
     * @return void
     */
    public function __construct(string|null $path = null)
    {
        $this->orignalBlocks = [];
        $this->originCachePath = $path ? $path : env('VIEW_COMPILED_PATH', '/cache/views');
        $this->uid = md5(random_bytes(5));
    }

    /**
     * Get origin view folder.
     *
     * @return string|null
     */
    public static function getOriginView(): string|null
    {
        return static::$originView;
    }

    /**
     * Set origin view folder.
     *
     * @param string $originView
     */
    public static function setOriginView(string $originView): void
    {
        static::$originView = $originView;
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
        $this->cachePath = $this->originCachePath . '/' . $file;

        if (!debug()) {
            return $this;
        }

        $content = $this->getContent($file);
        $content = $this->orignalBlocks($content);

        // Comment Tag
        foreach (self::commentTag as $first => $second) {
            $content = strval(preg_replace(
                sprintf('/%s(.*?)%s/s', $first, $second),
                '',
                strval($content)
            ));
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
     * Check if same parentheses
     *
     * @param string $exp
     * @return bool
     */
    private function same(string $exp): bool
    {
        $opening = 0;
        $closing = 0;

        foreach (token_get_all('<?php ' . $exp) as $token) {
            if ($token === '(') {
                $opening++;
            }

            if ($token === ')') {
                $closing++;
            }
        }

        return $opening == $closing;
    }

    /**
     * Override function and extract index 1 of matches.
     *
     * @param string $pattern
     * @param Closure $callback
     * @param string $subject
     * @return string
     */
    private function pregReplaceCallback(string $pattern, Closure $callback, string $subject): string
    {
        $matches = [];
        preg_match_all($pattern, $subject, $matches);

        for ($i = 0; isset($matches[0][$i]); $i++) {

            $match = [
                $matches[0][$i] ?? null,
                $matches[1][$i] ?? null,
                $matches[0][$i] ?? null,
            ];

            if (!$match[0]) {
                return $subject;
            }

            list($before, $after) = explode($match[0],  $subject, 2);

            while (str_ends_with($match[2], ')') && !$this->same($match[2])) {

                $first = strstr($after, ')', true);
                $first = $first === false ? $after : $first . ')';

                $match[2] .= $first;
                $after = explode($first, $after, 2)[1];
            }

            if ($match[0] != $match[2]) {
                $match[1] .= explode($match[1], $match[2], 2)[1];
            }

            $subject = $before . $callback($match[1] ?? $match[2]) . $after;
        }

        return $subject;
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
        $content = @file_get_contents(sprintf(base_path('%s/%s.kita.php'), static::$originView ?? '/resources/views', $file));

        if (!(bool) $content) {
            throw new Exception(sprintf('Can\'t open file [%s.kita.php]', $file));
        }

        return strval($content);
    }

    /**
     * Save a file. If folder not exist, create it.
     *
     * @param string $content
     * @return void
     */
    private function putContent(string $content): void
    {
        $file = base_path($this->cachePath);
        $arr = explode('/', $file);
        $depth = count($arr) - 1;

        $folder = implode('/', array_splice($arr, 0, -1));
        if (!is_dir($folder) && $depth > 3) {
            @mkdir($folder, 0777, true);
        } else if (!is_dir(base_path($this->originCachePath))) {
            @mkdir(base_path($this->originCachePath), 0777, true);
        }

        if (!(bool) @file_put_contents($file . '.php', $content)) {
            throw new Exception(sprintf('Can\'t save file [%s.php]', $this->cachePath));
        }
    }

    /**
     * Save temporary orignal blocks.
     *
     * @param string $content
     * @return string
     */
    private function orignalBlocks(string $content): string
    {
        return $this->pregReplaceCallback(
            '/(?<!@)@original(.*?)@endoriginal/s',
            function (string $matches): string {
                return sprintf(
                    '@__%s__%s__@',
                    $this->uid,
                    array_push(
                        $this->orignalBlocks,
                        $matches
                    ) - 1
                );
            },
            $content
        );
    }

    /**
     * Tag untuk menampilkan hasil.
     *
     * @param string $content
     * @return string
     */
    private function echoTag(string $content): string
    {
        foreach (self::echoTag as $first => $second) {
            $content = $this->pregReplaceCallback(
                sprintf('/%s\s*(.+?)\s*%s/s', $first, $second),
                function (string $matches) use ($first, $second): string {

                    // Safe echo tag
                    if ($first == '{{' && $second == '}}') {
                        $matches = sprintf('e(%s)', $matches);
                    }

                    return sprintf('<?php echo %s ?>', $matches);
                },
                $content
            );
        }

        return $content;
    }

    /**
     * Kembalikan lagi hasil yang original.
     *
     * @param string $content
     * @return string
     */
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

    /**
     * Tag kondisi if.
     *
     * @param string $content
     * @return string
     */
    private function ifOpenTag(string $content): string
    {
        return $this->pregReplaceCallback(
            '/(?<!@)@if\s*(\(.*?\))(?(?=\w|)(?!\w)|)/m',
            function (string $matches): string {
                return sprintf('<?php if %s : ?>', $matches);
            },
            $content
        );
    }

    /**
     * Tag kondisi jika ada.
     *
     * @param string $content
     * @return string
     */
    private function issetOpenTag(string $content): string
    {
        return $this->pregReplaceCallback(
            '/(?<!@)@isset\s*(\(.*?\))(?(?=\w|)(?!\w)|)/m',
            function (string $matches): string {
                return sprintf('<?php if (isset%s) : ?>', $matches);
            },
            $content
        );
    }

    /**
     * Tag kondisi jika kosong.
     *
     * @param string $content
     * @return string
     */
    private function emptyOpenTag(string $content): string
    {
        return $this->pregReplaceCallback(
            '/(?<!@)@empty\s*(\(.*?\))(?(?=\w|)(?!\w)|)/m',
            function (string $matches): string {
                return sprintf('<?php if (empty%s) : ?>', $matches);
            },
            $content
        );
    }

    /**
     * Tag untuk menampilkan pesan error.
     *
     * @param string $content
     * @return string
     */
    private function errorOpenTag(string $content): string
    {
        return $this->pregReplaceCallback(
            '/(?<!@)@error\s*(\(.*?\))(?(?=\w|)(?!\w)|)/m',
            function (string $matches): string {
                return sprintf('<?php $pesan = error%s; if ($pesan) : ?>', $matches);
            },
            $content
        );
    }

    /**
     * Tag untuk menampilkan flash.
     *
     * @param string $content
     * @return string
     */
    private function flashOpenTag(string $content): string
    {
        return $this->pregReplaceCallback(
            '/(?<!@)@flash\s*(\(.*?\))(?(?=\w|)(?!\w)|)/m',
            function (string $matches): string {
                return sprintf('<?php $pesan = flash%s; if ($pesan) : ?>', $matches);
            },
            $content
        );
    }

    /**
     * Tag untuk perulangan.
     *
     * @param string $content
     * @return string
     */
    private function forOpenTag(string $content): string
    {
        return $this->pregReplaceCallback(
            '/(?<!@)@for\s*(\(.*?\))(?(?=\w|)(?!\w)|)/m',
            function (string $matches): string {
                return sprintf('<?php for %s : ?>', $matches);
            },
            $content
        );
    }

    /**
     * Tag untuk iterasi.
     *
     * @param string $content
     * @return string
     */
    private function foreachOpenTag(string $content): string
    {
        return $this->pregReplaceCallback(
            '/(?<!@)@foreach\s*(\(.*?\))(?(?=\w|)(?!\w)|)/m',
            function (string $matches): string {
                return sprintf('<?php foreach %s : ?>', $matches);
            },
            $content
        );
    }

    /**
     * Tag section beberapa part.
     *
     * @param string $content
     * @return string
     */
    private function sectionOpenTag(string $content): string
    {
        return $this->pregReplaceCallback(
            '/(?<!@)@section\s*(\(.*?\))(?(?=\w|)(?!\w)|)/s',
            function (string $matches): string {
                return sprintf('<?php \Core\Facades\App::get()->singleton(\Core\View\View::class)->section%s ?>', $matches);
            },
            $content
        );
    }

    /**
     * Tag kondisi sudah login.
     *
     * @param string $content
     * @return string
     */
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

    /**
     * Tag kondisi belum login.
     *
     * @param string $content
     * @return string
     */
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

    /**
     * Tag raw php syntax.
     *
     * @param string $content
     * @return string
     */
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

    /**
     * Tag penutup percabangan.
     *
     * @param string $content
     * @return string
     */
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

    /**
     * Tag penutup jika ada.
     *
     * @param string $content
     * @return string
     */
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

    /**
     * Tag penutup jika kosong.
     *
     * @param string $content
     * @return string
     */
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

    /**
     * Tag penutup dari menampilkan error.
     *
     * @param string $content
     * @return string
     */
    private function errorCloseTag(string $content): string
    {
        return $this->pregReplaceCallback(
            '/(?<!@)@enderror(?(?=\w|)(?!\w)|)/s',
            function (): string {
                return '<?php unset($pesan); endif; ?>';
            },
            $content
        );
    }

    /**
     * Tag penutup dari flash.
     *
     * @param string $content
     * @return string
     */
    private function flashCloseTag(string $content): string
    {
        return $this->pregReplaceCallback(
            '/(?<!@)@endflash(?(?=\w|)(?!\w)|)/s',
            function (): string {
                return '<?php unset($pesan); endif; ?>';
            },
            $content
        );
    }

    /**
     * Tag penutup dari perulangan.
     *
     * @param string $content
     * @return string
     */
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

    /**
     * Tag penutup dari iterasi.
     *
     * @param string $content
     * @return string
     */
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

    /**
     * Tag penutup dari section beberapa part.
     *
     * @param string $content
     * @return string
     */
    private function sectionCloseTag(string $content): string
    {
        return $this->pregReplaceCallback(
            '/(?<!@)@endsection(?(?=\w|)(?!\w)|)/s',
            function (): string {
                return '<?php \Core\Facades\App::get()->singleton(\Core\View\View::class)->endsection() ?>';
            },
            $content
        );
    }

    /**
     * Tag penutup dari sudah login.
     *
     * @param string $content
     * @return string
     */
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

    /**
     * Tag penutup dari belum login.
     *
     * @param string $content
     * @return string
     */
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

    /**
     * Tag penutup raw php syntax.
     *
     * @param string $content
     * @return string
     */
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

    /**
     * Tag untuk menambahkan parent.
     *
     * @param string $content
     * @return string
     */
    private function extendTag(string $content): string
    {
        return $this->pregReplaceCallback(
            '/(?<!@)@extend\s*(\(.*?\))(?(?=\w|)(?!\w)|)/s',
            function (string $matches): string {
                return sprintf('<?php \Core\Facades\App::get()->singleton(\Core\View\View::class)->parents%s; ?>', $matches);
            },
            $content
        );
    }

    /**
     * Tag import dari file lain.
     *
     * @param string $content
     * @return string
     */
    private function includeTag(string $content): string
    {
        return $this->pregReplaceCallback(
            '/(?<!@)@include\s*(\(.*?\))(?(?=\w|)(?!\w)|)/s',
            function (string $matches): string {
                return sprintf('<?php echo \Core\Facades\App::get()->singleton(\Core\View\View::class)->including%s ?>', $matches);
            },
            $content
        );
    }

    /**
     * Tag isi dari section.
     *
     * @param string $content
     * @return string
     */
    private function contentTag(string $content): string
    {
        return $this->pregReplaceCallback(
            '/(?<!@)@content\s*(\(.*?\))(?(?=\w|)(?!\w)|)/s',
            function (string $matches): string {
                return sprintf('<?php echo \Core\Facades\App::get()->singleton(\Core\View\View::class)->content%s ?>', $matches);
            },
            $content
        );
    }

    /**
     * Tag percabangan.
     *
     * @param string $content
     * @return string
     */
    private function elseifTag(string $content): string
    {
        return $this->pregReplaceCallback(
            '/(?<!@)@elseif\s*(\(.*?\))(?(?=\w|)(?!\w)|)/m',
            function (string $matches): string {
                return sprintf('<?php elseif %s : ?>', $matches);
            },
            $content
        );
    }

    /**
     * Tag akhir percabangan.
     *
     * @param string $content
     * @return string
     */
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

    /**
     * Tag continue dari loop.
     *
     * @param string $content
     * @return string
     */
    private function continueTag(string $content): string
    {
        return $this->pregReplaceCallback(
            '/(?<!@)@continue(\s*\((\d+?)\))?(?(?=\w|)(?!\w)|)/s',
            function (string $num): string {
                $result = [];
                preg_match('/(\d)/s', $num, $result);

                if ($result) {
                    return sprintf('<?php continue %s ?>', is_numeric($result[1]) ? $result[1] : 1);
                }

                return '<?php continue ?>';
            },
            $content
        );
    }

    /**
     * Tag break dari loop.
     *
     * @param string $content
     * @return string
     */
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

    /**
     * Tag csrf token.
     *
     * @param string $content
     * @return string
     */
    private function csrfTag(string $content): string
    {
        return $this->pregReplaceCallback(
            '/(?<!@)@csrf(?(?=\w|)(?!\w)|)/s',
            function (): string {
                return '<?php echo \'<input type="hidden" name="\' . \Core\Http\Session::TOKEN . \'" value="\' . csrf_token() . \'">\' . PHP_EOL ?>';
            },
            $content
        );
    }

    /**
     * Tag optional method http.
     *
     * @param string $content
     * @return string
     */
    private function methodTag(string $content): string
    {
        return $this->pregReplaceCallback(
            '/(?<!@)@method\s*(\(.*?\))(?(?=\w|)(?!\w)|)/m',
            function (string $matches): string {
                return sprintf('<?php echo \'<input type="hidden" name="\' . \Core\Http\Request::METHOD . \'" value="\' . strtoupper%s . \'">\' . PHP_EOL ?>', $matches);
            },
            $content
        );
    }
}
