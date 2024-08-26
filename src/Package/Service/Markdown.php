<?php
namespace Package\R3m\Io\Markdown\Service;

use R3m\Io\App;

use League\CommonMark\GithubFlavoredMarkdownConverter;

use League\CommonMark\Exception\CommonMarkException;

class Markdown {

    /**
     * @throws CommonMarkException
     */
    public static function parse(App $object, $string=''): string
    {
        //options: App::options($object)
        //flags: App::flags($object)

        $converter = new GithubFlavoredMarkdownConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
        return $converter->convert($string);
    }
}
