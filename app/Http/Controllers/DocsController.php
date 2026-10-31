<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * openmeru.com/docs: the README rendered as a documentation page with a
 * section menu, so the guide lives in one place (the repository) and the
 * site never drifts from it.
 */
class DocsController extends Controller
{
    public function show(): View
    {
        $markdown = (string) file_get_contents(base_path('README.md'));
        // The logo banner and the screenshots belong to the GitHub page, not the docs.
        $markdown = preg_replace('/^<p align="center">.*?<\/p>\s*/s', '', $markdown);
        $markdown = preg_replace('/^## Screenshots\n.*?(?=^## )/ms', '', $markdown);
        $markdown = str_replace('](docs/', ']('.SeoController::REPO.'/blob/main/docs/', $markdown);

        $html = (string) Str::markdown($markdown, ['html_input' => 'allow', 'allow_unsafe_links' => false]);

        $sections = [];
        $html = preg_replace_callback('/<h2>(.*?)<\/h2>/', function ($m) use (&$sections) {
            $title = trim(strip_tags($m[1]));
            $id = Str::slug($title) ?: 'section-'.(count($sections) + 1);
            $sections[] = ['id' => $id, 'title' => $title];

            return '<h2 id="'.$id.'">'.$m[1].'</h2>';
        }, $html);

        return view('docs', ['html' => $html, 'sections' => $sections, 'repo' => SeoController::REPO]);
    }
}
