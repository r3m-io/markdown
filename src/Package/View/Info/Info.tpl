{{R3M}}
{{$request = request()}}
Package: {{$request.package}}

Module: {{$request.module|uppercase.first}}

{{if(!is.empty($request.submodule))}}
Submodule: {{$request.submodule|uppercase.first}}
{{/if}}
{{if($request.module === 'info')}}
{{$files = dir.read(config('controller.dir.view'))}}
{{$files = data.sort($files, ['url' => 'ASC'])}}
Commands:
{{for.each($files as $file)}}
{{$file.basename = file.basename($file.name, config('extension.tpl'))}}
{{binary()}} {{$request.package}} object {{$file.basename|lowercase}}

{{/for.each}}
{{/if}}