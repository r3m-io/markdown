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
        $string = Markdown::allow_anchor($object, $string);
        $string = $converter->convert($string);
        $string =  str_replace([$comment_start, $comment_end], ['<!--', '-->'], $string);
        $string = Markdown::apply_anchor($object, $string);
        return str_replace(['<p><!--', '--></p>'], ['<!--', '-->'], $string);
    }

    public static function allow_anchor($object, $string=''): string
    {
        $data = mb_str_split($string, 1);
        $anchor = false;
        $is_tag = false;
        $collect = [];
        foreach($data as $nr => $char){
            if(
                $char == '<' &&
                $is_tag === false &&
                $anchor === false
            ){
                $is_tag = true;
            }
            elseif(
                $char == '>' &&
                $is_tag === true
            ){
                $is_tag = false;
                ddd($collect);
            }
            elseif(
                $char == 'a' &&
                $is_tag === true &&
                $anchor === false
            ){
                $anchor = true;
            }
            if($anchor){
                $collect[] = $char;
            }
        }
        return $string;
    }

    public static function apply_anchor($object, $string=''): string
    {
        return $string;
    }

}
