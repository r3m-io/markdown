<?php
namespace Package\R3m\Io\Markdown\Service;

use R3m\Io\App;

use R3m\Io\Module\Core;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Attributes\AttributesExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

use League\CommonMark\Exception\CommonMarkException;

class Markdown {

    /**
     * @throws CommonMarkException
     */
    public static function parse(App $object, $string=''): string
    {
        //options: App::options($object)
        //flags: App::flags($object)

        $config = [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ];
        $environment = new Environment($config);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());
        $environment->addExtension(new AttributesExtension());
        $converter = new MarkdownConverter($environment);
        $comment_start = Core::uuid();
        $comment_end = Core::uuid();
        $string = str_replace(['<!--', '-->'], [$comment_start, $comment_end], $string);
        $string = $converter->convert($string);
        $string =  str_replace([$comment_start, $comment_end], ['<!--', '-->'], $string);
        d($string);
        return $string;
    }
}
