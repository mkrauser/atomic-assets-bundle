# Symfony Reprise with Rsbuild/Rspack

Symfony Reprise replaces Encore's Symfony integration. Rsbuild uses Rspack underneath and can reuse this bundle's
Webpack-compatible Twig loader.

## Install

```bash
composer require symfony/reprise
npm install --save-dev @rsbuild/core @symfony/reprise glob
```

Install the appropriate Rsbuild plugin when using a preprocessor, for example `@rsbuild/plugin-sass`.

## Configure Rsbuild

```ts
// rsbuild.config.ts
import { resolve } from 'node:path';
import { defineConfig } from '@rsbuild/core';
import Symfony from '@symfony/reprise/rsbuild';
import { globSync } from 'glob';

export default defineConfig({
    source: {
        entry: {
            app: {
                import: [
                    resolve('./assets/app.js'),
                    ...globSync('./templates/**/*.html.twig').map((file) => resolve(file)),
                ],
                html: false,
            },
        },
    },
    output: {
        dataUriLimit: 0,
        filename: {
            image: '[path][name].[contenthash:8][ext]',
            svg: '[path][name].[contenthash:8][ext]',
        },
        cssModules: {
            localIdentName: '[hash:base64:5]',
        },
    },
    plugins: [Symfony()],
    tools: {
        rspack: (_config, { appendRules }) => {
            appendRules({
                test: /\.twig$/,
                sideEffects: true,
                loader: resolve(
                    './vendor/mak/atomic-asset-bundle/Resources/webpack/TwigLoader.js',
                ),
            });
            appendRules({
                test: /\.(?:png|jpe?g|gif|svg|webp|avif|ico)$/i,
                sideEffects: true,
            });
        },
    },
});
```

The Twig loader turns every `{% importModule '...' %}` statement into an asset import. Adding the Twig files to
the entry makes Rspack process all templates.
References to `atomic_asset('...')` become native asset imports. Reprise includes their project-relative
source paths in its normal `manifest.json`; `dataUriLimit: 0` keeps small images as emitted files.
For other static file types, add the corresponding asset rule with `sideEffects: true` too.

## Configure Symfony

```yaml
# config/packages/mak_atomic_assets.yaml
mak_atomic_assets:
    localIdentName: '[hash:base64:5]'
    localIdentContext: '%kernel.project_dir%'
    localIdentHashSalt: null
```

Reprise defaults to `public/build`. Add its explicit configuration only when changing that output path:

```yaml
# config/packages/reprise.yaml
reprise:
    output_path: '%kernel.project_dir%/public/build'
```

Render the Reprise entry in your base template:

```twig
{{ reprise_entry_link_tags('app') }}
{{ reprise_entry_script_tags('app') }}
```

## PurgeCSS

Prefix `localIdentName` in both configurations, for example `_[hash:base64:5]`, and safelist `/^_/` in your
PurgeCSS configuration.

## Stimulus

Use Reprise's Stimulus integration rather than Encore's Stimulus bridge:

```ts
Symfony({
    stimulus: './assets/controllers.json',
})
```

Then start it from your application entry:

```js
import { startStimulusApp } from '@symfony/reprise/stimulus';

startStimulusApp();
```
