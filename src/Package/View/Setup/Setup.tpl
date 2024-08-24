{{R3M}}
{{$register = Package.R3m.Io.Server:Init:register()}}
{{if(!is.empty($register))}}
{{Package.R3m.Io.Server:Import:role.system()}}
{{$options = options()}}
{{if(is.empty($options.public))}}
{{$options.public = config('server.public')}}
{{/if}}
{{Package.R3m.Io.Server:Main:public.create($options)}}
{{/if}}