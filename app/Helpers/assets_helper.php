<?php
function asset_tags(): string
{
    $file = FCPATH.'assets/.vite/manifest.json';
    if (!is_file($file)) { return ''; }
    $manifest = json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
    $entry = $manifest['resources/js/app.js'];
    $tags = '';
    foreach ($entry['css'] ?? [] as $css) { $tags .= '<link rel="stylesheet" href="'.esc(base_url('assets/'.$css), 'attr').'">'; }
    return $tags.'<script type="module" src="'.esc(base_url('assets/'.$entry['file']), 'attr').'"></script>';
}
