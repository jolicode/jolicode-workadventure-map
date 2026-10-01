<?php

use Castor\Attribute\AsArgument;
use Castor\Attribute\AsTask;

use function Castor\fs;
use function Castor\io;

defined('CASTOR_USE_CHDIR') || define('CASTOR_USE_CHDIR', true);

const THEME_PREFIX = 'theme-';

#[AsTask(description: 'Build the site into build/, with a seasonal theme baked into the maps')]
function build(
    #[AsArgument(description: '"auto" (picked from the current month), "neutral", or the name of a "theme-<name>" layer group')]
    string $theme = 'auto',
): void {
    theme_selfcheck();

    if ('auto' === $theme) {
        $theme = theme_pick((int) date('n'));
    }

    $build = __DIR__ . '/build';
    fs()->remove($build);
    fs()->copy(__DIR__ . '/index.html', "{$build}/index.html");

    $found = false;
    foreach (glob(__DIR__ . '/*/map.json') as $path) {
        $target = $build . '/' . basename(\dirname($path));
        fs()->mirror(\dirname($path), $target);

        $map = json_decode(file_get_contents($path), flags: \JSON_THROW_ON_ERROR);
        $found = theme_bake($map, $theme) || $found;
        file_put_contents("{$target}/map.json", json_encode($map, \JSON_THROW_ON_ERROR));
    }

    if ('neutral' !== $theme && !$found) {
        throw new RuntimeException(\sprintf('No "%s%s" group in any map.', THEME_PREFIX, $theme));
    }

    io()->success("Site built in build/ with theme: {$theme}");
}

function theme_pick(int $month): string
{
    return match ($month) {
        10 => 'halloween',
        12 => 'christmas',
        default => 'neutral',
    };
}

/**
 * Merges the layers of the "theme-<name>" group into the base layers of the
 * same name (non-empty tiles win), then removes every theme group.
 *
 * @return bool Whether the map has a group for this theme
 */
function theme_bake(stdClass $map, string $theme): bool
{
    $base = array_column($map->layers, null, 'name');
    $found = false;

    foreach ($map->layers as $key => $group) {
        if ('group' !== $group->type || !str_starts_with($group->name, THEME_PREFIX)) {
            continue;
        }

        unset($map->layers[$key]);

        if (THEME_PREFIX . $theme !== $group->name) {
            continue;
        }

        $found = true;
        foreach ($group->layers as $layer) {
            foreach ($layer->data as $i => $gid) {
                if ($gid) {
                    $base[$layer->name]->data[$i] = $gid;
                }
            }
        }
    }

    $map->layers = array_values($map->layers);

    return $found;
}

function theme_selfcheck(): void
{
    $map = json_decode('{"layers":[
        {"name":"a","type":"tilelayer","data":[1,2]},
        {"name":"theme-x","type":"group","layers":[{"name":"a","data":[0,9]}]},
        {"name":"theme-y","type":"group","layers":[{"name":"a","data":[8,0]}]}
    ]}');

    if (!theme_bake($map, 'x') || '[{"name":"a","type":"tilelayer","data":[1,9]}]' !== json_encode($map->layers)) {
        throw new LogicException('theme_bake() is broken.');
    }
    if ('halloween' !== theme_pick(10) || 'neutral' !== theme_pick(3)) {
        throw new LogicException('theme_pick() is broken.');
    }
}
