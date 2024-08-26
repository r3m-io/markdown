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
        $record = [];
        foreach($data as $nr => $char){
            $previous = $data[$nr - 1] ?? null;
            $next = $data[$nr + 1] ?? null;
            if($char == '<'){
                $is_tag = $nr;
            }
            elseif(
                $char == '>' &&
                $is_tag !== false
            ){
                $is_tag = false;
                $is_value = false;
                $value = '';
                $key = '';
                $is_single_quote = false;
                $is_double_quote = false;
                foreach($collect as $collect_nr => $collect_value){
                    if($collect_value === '"'){
                        $is_double_quote = true;
                    }
                    elseif($collect_value === '\''){
                        $is_single_quote = true;
                    }
                    elseif($collect_value === '='){
                        $is_value = true;
                    }
                    elseif(
                        $collect_value === ' ' &&
                        $is_single_quote === false &&
                        $is_double_quote === false
                    ){
                        $is_value = false;
                        if($key !== ''){
                            $record[$key] = $value;
                        }
                        $key = '';
                        $value = '';
                    }
                    if(
                        $is_value === false &&
                        $is_single_quote === false &&
                        $is_double_quote === false
                    ){
                        if($collect_value !== ' '){
                            $key .= $collect_value;
                        }
                    } else {
                        if($collect_value === '='){
                            //nothing
                        }
                        elseif($collect_value === '"'){
                            //nothing
                        }
                        elseif($collect_value === '\''){
                          //nothing
                        } else {
                            $value .= $collect_value;
                        }
                    }
                }
                if($is_value === true){
                    if($key !== ''){
                        $record[$key] = $value;
                    }
                }
            }
            elseif(
                $char == 'a' &&
                $is_tag === true &&
                $anchor === false
            ){
                $anchor = true;
                continue;
            }
            if($anchor){
                $collect[] = $char;
            }
        }
        ddd($record);
        return $string;
    }

    public static function apply_anchor($object, $string=''): string
    {
        return $string;
    }

}
