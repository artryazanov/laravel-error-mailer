@php
$theme = config('error-mailer.theme', 'light');

$colors = $theme === 'dark' ? [
    'bg' => '#0f172a',
    'container_bg' => '#1e293b',
    'text_main' => '#f8fafc',
    'text_muted' => '#94a3b8',
    'accent_red' => '#ef4444',
    'accent_red_bg' => 'rgba(239, 68, 68, 0.1)',
    'accent_red_border' => 'rgba(239, 68, 68, 0.3)',
    'border' => '#334155',
    'code_bg' => '#0f172a',
    'link' => '#38bdf8',
    'badge_bg' => '#334155',
    'badge_text' => '#e2e8f0',
    'json_text' => '#a78bfa',
    'line_num' => '#cbd5e1',
] : [
    'bg' => '#f3f4f6',          
    'container_bg' => '#ffffff',
    'text_main' => '#111827',   
    'text_muted' => '#6b7280',  
    'accent_red' => '#dc2626',  
    'accent_red_bg' => '#fef2f2',
    'accent_red_border' => '#fca5a5',
    'border' => '#e5e7eb',      
    'code_bg' => '#f9fafb',     
    'link' => '#0284c7',        
    'badge_bg' => '#f3f4f6',    
    'badge_text' => '#374151',  
    'json_text' => '#7c3aed',   
    'line_num' => '#9ca3af',
];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exception Occurred</title>
    <style>
        /* Email client safe reset */
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; }
        img { border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        table { border-collapse: collapse !important; }
        body { height: 100% !important; margin: 0 !important; padding: 0 !important; width: 100% !important; }
        
        .exception-class { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
        .info-value { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
        .json-block { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
        .trace-method { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
        .trace-file { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
        .markdown-content { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
    </style>
</head>
<body style="background-color: {{ $colors['bg'] }}; color: {{ $colors['text_main'] }}; font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.5; margin: 0; padding: 20px; -webkit-font-smoothing: antialiased;">
    <div style="max-width: 800px; margin: 0 auto; background-color: {{ $colors['container_bg'] }}; border: 1px solid {{ $colors['border'] }}; border-radius: 8px; overflow: hidden; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);">
        
        <!-- Header / Exception Info -->
        <div style="background-color: {{ $colors['bg'] }}; border-bottom: 1px solid {{ $colors['border'] }}; padding: 24px;">
            <div style="display: inline-block; background-color: {{ $colors['badge_bg'] }}; color: {{ $colors['badge_text'] }}; padding: 4px 10px; border-radius: 9999px; font-size: 12px; font-weight: 600; text-transform: uppercase; margin-bottom: 12px; letter-spacing: 0.05em;">
                {{ config('app.name', 'Laravel') }} &middot; {{ config('app.env', 'production') }}
            </div>
            <p class="exception-class" style="font-size: 14px; color: {{ $colors['text_muted'] }}; margin: 0 0 8px 0; word-break: break-all;">
                {{ $content['class'] ?? 'Exception' }}
            </p>
            <h1 style="font-size: 24px; font-weight: 700; color: {{ $colors['accent_red'] }}; margin: 0; line-height: 1.3;">
                {{ $content['message'] ?? 'An error occurred' }}
            </h1>
        </div>

        <!-- Request Context -->
        <div style="padding: 24px; border-bottom: 1px solid {{ $colors['border'] }};">
            <h2 style="font-size: 16px; font-weight: 600; color: {{ $colors['text_main'] }}; margin: 0 0 16px 0; text-transform: uppercase; letter-spacing: 0.05em;">Request Context</h2>
            
            @if($content['is_console'] ?? false)
                <!-- Console Context -->
                <div style="margin-bottom: 16px;">
                    <span style="color: {{ $colors['text_muted'] }}; font-size: 14px; font-weight: 500;">Context:</span>
                    <span class="info-value" style="color: {{ $colors['text_main'] }}; font-size: 14px; margin-left: 8px;">CLI</span>
                </div>
                
                @if(!empty($content['command']))
                <div style="margin-bottom: 16px;">
                    <div style="color: {{ $colors['text_muted'] }}; font-size: 14px; font-weight: 500; margin-bottom: 8px;">CONSOLE_COMMAND</div>
                    <pre class="json-block" style="background-color: {{ $colors['code_bg'] }}; border: 1px solid {{ $colors['border'] }}; border-radius: 6px; padding: 16px; font-size: 13px; color: {{ $colors['json_text'] }}; margin: 0; overflow-x: auto;">{{ json_encode($content['command'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                </div>
                @endif
                
                @if(!empty($content['server']))
                <div style="margin-bottom: 16px;">
                    <div style="color: {{ $colors['text_muted'] }}; font-size: 14px; font-weight: 500; margin-bottom: 8px;">$_SERVER</div>
                    <pre class="json-block" style="background-color: {{ $colors['code_bg'] }}; border: 1px solid {{ $colors['border'] }}; border-radius: 6px; padding: 16px; font-size: 13px; color: {{ $colors['json_text'] }}; margin: 0; overflow-x: auto;">{{ json_encode($content['server'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                </div>
                @endif
            @else
                <!-- Web Context -->
                @if(!empty($content['user']))
                <div style="margin-bottom: 16px;">
                    <span style="color: {{ $colors['text_muted'] }}; font-size: 14px; font-weight: 500;">USER</span><br>
                    <span class="info-value" style="color: {{ $colors['text_main'] }}; font-size: 14px; word-break: break-all;">"{{ $content['user'] }}"</span>
                </div>
                @endif

                <div style="margin-bottom: 16px;">
                    <span style="color: {{ $colors['text_muted'] }}; font-size: 14px; font-weight: 500;">URL</span><br>
                    <span class="info-value" style="color: {{ $colors['text_main'] }}; font-size: 14px; word-break: break-all;"><a href="{{ $content['url'] ?? '#' }}" style="color: {{ $colors['link'] }}; text-decoration: none;">"{{ $content['url'] ?? 'N/A' }}"</a></span>
                </div>

                <div style="margin-bottom: 16px;">
                    <span style="color: {{ $colors['text_muted'] }}; font-size: 14px; font-weight: 500;">METHOD</span><br>
                    <span class="info-value" style="color: {{ $colors['text_main'] }}; font-size: 14px;">"{{ $content['method'] ?? 'N/A' }}"</span>
                </div>

                @if(!empty($content['body']))
                <div style="margin-bottom: 16px;">
                    <div style="color: {{ $colors['text_muted'] }}; font-size: 14px; font-weight: 500; margin-bottom: 8px;">POST</div>
                    <pre class="json-block" style="background-color: {{ $colors['code_bg'] }}; border: 1px solid {{ $colors['border'] }}; border-radius: 6px; padding: 16px; font-size: 13px; color: {{ $colors['json_text'] }}; margin: 0; overflow-x: auto;">{{ json_encode($content['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                </div>
                @endif

                @if(!empty($content['headers']))
                <div style="margin-bottom: 16px;">
                    <div style="color: {{ $colors['text_muted'] }}; font-size: 14px; font-weight: 500; margin-bottom: 8px;">HEADER</div>
                    <pre class="json-block" style="background-color: {{ $colors['code_bg'] }}; border: 1px solid {{ $colors['border'] }}; border-radius: 6px; padding: 16px; font-size: 13px; color: {{ $colors['json_text'] }}; margin: 0; overflow-x: auto;">{{ json_encode($content['headers'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                </div>
                @endif

                @if(!empty($content['cookie']))
                <div style="margin-bottom: 16px;">
                    <div style="color: {{ $colors['text_muted'] }}; font-size: 14px; font-weight: 500; margin-bottom: 8px;">COOKIE</div>
                    <pre class="json-block" style="background-color: {{ $colors['code_bg'] }}; border: 1px solid {{ $colors['border'] }}; border-radius: 6px; padding: 16px; font-size: 13px; color: {{ $colors['json_text'] }}; margin: 0; overflow-x: auto;">{{ json_encode($content['cookie'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                </div>
                @endif

                @if(!empty($content['server']))
                <div style="margin-bottom: 16px;">
                    <div style="color: {{ $colors['text_muted'] }}; font-size: 14px; font-weight: 500; margin-bottom: 8px;">$_SERVER</div>
                    <pre class="json-block" style="background-color: {{ $colors['code_bg'] }}; border: 1px solid {{ $colors['border'] }}; border-radius: 6px; padding: 16px; font-size: 13px; color: {{ $colors['json_text'] }}; margin: 0; overflow-x: auto;">{{ json_encode($content['server'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                </div>
                @endif
            @endif
        </div>

        <!-- Stack Trace -->
        <div style="padding: 24px; border-bottom: 1px solid {{ $colors['border'] }};">
            <h2 style="font-size: 16px; font-weight: 600; color: {{ $colors['text_main'] }}; margin: 0 0 16px 0; text-transform: uppercase; letter-spacing: 0.05em;">Stack Trace</h2>
            
            <!-- Exception Location -->
            <div style="background-color: {{ $colors['accent_red_bg'] }}; border: 1px solid {{ $colors['accent_red_border'] }}; margin-bottom: 12px; padding: 12px; border-radius: 6px;">
                <div class="trace-file" style="color: {{ $colors['text_main'] }}; font-size: 13px; word-break: break-all;">
                    {{ $content['file'] ?? '' }}:<span style="color: {{ $colors['accent_red'] }}; font-weight: bold;">{{ $content['line'] ?? '' }}</span>
                </div>
            </div>

            <!-- Trace Frames -->
            @foreach(array_slice($content['trace'] ?? [], 0, 20) as $index => $frame)
            <div style="background-color: {{ $colors['code_bg'] }}; border: 1px solid {{ $colors['border'] }}; margin-bottom: 12px; padding: 12px; border-radius: 6px;">
                <div class="trace-method" style="color: {{ $colors['link'] }}; font-weight: 600; font-size: 14px; margin-bottom: 4px; word-break: break-all;">
                    {{ !empty($frame['class']) ? $frame['class'].'->' : '' }}{{ $frame['function'] ?? '' }}()
                </div>
                <div class="trace-file" style="color: {{ $colors['text_muted'] }}; font-size: 13px; word-break: break-all;">
                    @if(isset($frame['file']))
                        @php
                            $isVendor = str_contains($frame['file'], '/vendor/');
                        @endphp
                        {{ $frame['file'] }}:<span style="color: {{ $colors['line_num'] }}; font-weight: 600;">{{ $frame['line'] ?? '' }}</span>
                        @if($isVendor)
                            <span style="background-color: {{ $colors['badge_bg'] }}; color: {{ $colors['badge_text'] }}; padding: 2px 6px; border-radius: 4px; font-size: 11px; margin-left: 8px; vertical-align: middle;">vendor</span>
                        @endif
                    @else
                        [internal function]
                    @endif
                </div>
            </div>
            @endforeach
        </div>

        <!-- Markdown Copy Block -->
        @if(!empty($content['markdown']))
        <div style="padding: 24px; background-color: {{ $colors['container_bg'] }}; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px;">
            <div style="margin-bottom: 16px;">
                <h2 style="display: inline-block; font-size: 16px; font-weight: 600; color: {{ $colors['text_main'] }}; margin: 0; text-transform: uppercase; letter-spacing: 0.05em;">Markdown Representation</h2>
                <span style="font-size: 12px; color: {{ $colors['text_muted'] }}; margin-left: 12px;">(Manually copy the text below)</span>
            </div>
            <div style="background-color: {{ $colors['code_bg'] }}; border: 1px solid {{ $colors['border'] }}; padding: 16px; border-radius: 6px; overflow-x: auto;">
                <pre class="markdown-content" style="color: {{ $colors['text_muted'] }}; font-size: 12px; white-space: pre-wrap; margin: 0; line-height: 1.5;">{{ $content['markdown'] }}</pre>
            </div>
        </div>
        @endif

    </div>
</body>
</html>
