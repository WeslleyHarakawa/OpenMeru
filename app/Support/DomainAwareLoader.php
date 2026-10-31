<?php

namespace App\Support;

use Illuminate\Translation\FileLoader;

/**
 * The texts in lang/*.json ship with "openmeru.com" in them (examples, legal
 * pages, notices). Every install shows its own MAIL_DOMAIN instead, swapped
 * at load time, so the files on disk stay exactly as in git and updates
 * never conflict with them.
 */
class DomainAwareLoader extends FileLoader
{
    public const SHIPPED_DOMAIN = 'openmeru.com';

    public static function fromExisting(FileLoader $loader, array $paths): self
    {
        $new = new self(app('files'), $paths);
        foreach ($loader->jsonPaths() as $path) {
            $new->addJsonPath($path);
        }
        foreach ($loader->namespaces() as $namespace => $hint) {
            $new->addNamespace($namespace, $hint);
        }

        return $new;
    }

    public function load($locale, $group, $namespace = null)
    {
        $lines = parent::load($locale, $group, $namespace);
        $domain = (string) config('inbound.domain');
        if ($group === '*' && $namespace === '*' && $domain !== '' && $domain !== self::SHIPPED_DOMAIN) {
            $lines = str_replace(self::SHIPPED_DOMAIN, $domain, $lines);
        }

        return $lines;
    }
}
