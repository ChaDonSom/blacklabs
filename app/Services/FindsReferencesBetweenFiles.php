<?php

namespace App\Services;

use Illuminate\Support\Str;

trait FindsReferencesBetweenFiles
{
    use RunsProcesses;

    public function getReferencesToFiles($file, $files): array
    {
        try {
            $fileContents = file_get_contents($file);
        } catch (\Exception $e) {
            $this->error("Could not read the file: $file");
            return [];
        }

        // Get the Vite aliases.
        $aliases = $this->getViteAliases();
        $aliasesSortedByLength = collect($aliases)->sortByDesc(function ($value, $key) {
            return strlen($key);
        });

        // Does the given file reference the other files?
        $pathsInFile = Str::of($fileContents)->matchAll('/(import.*)(?<=["\'])([^"\']*)(?=["\'])/')->flatten();
        $pathsInFile = collect($pathsInFile)->filter()->values()
            ->map(function ($path) {
                // From the first quote to the last quote
                return Str::of($path)->match('/["\'](.*)["\']/');
            })
            ->map(function ($path) use ($file) {
                // Paths that start with ./ are relative to the file
                if (Str::of($path)->startsWith('./')) {
                    return dirname($file) . '/' . $path->after('./');
                }
                return $path;
            })
            ->map(function ($path) use ($aliasesSortedByLength) {
                // Replace the alias with the path
                $matchedAliasKey = collect($aliasesSortedByLength)->keys()->first(function ($alias) use ($path) {
                    return Str::of($path)->startsWith($alias);
                });
                if ($matchedAliasKey) {
                    $path = Str::of($path)->replace($matchedAliasKey, $aliasesSortedByLength[$matchedAliasKey]);
                }
                return $path;
            })
            ->map(function ($path) {
                if (Str::of($path)->startsWith('/')) {
                    return $path->after('/');
                }
                return $path;
            });
        // print "\n" . $pathsInFile->join("\n") . "\n";
        // print "\n" . collect($files)->values()->join("\n") . "\n";
        $pathsInFileThatMatchFiles = $pathsInFile->filter(function ($path) use ($files) {
            return collect($files)->contains($path);
        });

        // Do the other files reference the given file?
        foreach ($files as $changedFile => $contents) {
        }

        return $pathsInFileThatMatchFiles->toArray();
    }

    public function getViteAliases($viteConfig = 'vite.config.ts')
    {

        /**
         * Since our vite config is formatted like this:
         * ```
         * export default defineConfig({
         *  ...
         *  resolve: {
         *      alias: {
         *          '@': '/resources',
         *          ...
         *          '@js': '/resources/js',
         *          ...
         *      }
         *  }
         * ```
         * We have to do something a little more special: we have to parse the file and find the aliases.
         */
        $aliases = $this->runProcess("cat $viteConfig");
        $viteResolve = Str::of($aliases)->match('/resolve: {.*}/s');
        $viteAlias = Str::of($viteResolve)->match('/alias: {[^}]*}/s');
        $viteAlias = Str::of($viteAlias)->after('alias: ')->beforeLast('}') . "}";
        // Remove the last comma
        $viteAlias = Str::of($viteAlias)->replaceLast(',', '');
        // Replace single quotes with double quotes
        $viteAlias = Str::of($viteAlias)->replace("'", '"');
        $viteAliases = json_decode($viteAlias, true);
        return $viteAliases;
    }
}
