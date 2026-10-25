# Symfony Reprise with Vite

Symfony Reprise replaces Encore's Symfony integration. Vite needs a small plugin to translate Twig
`importModule` statements into JavaScript imports; AtomicAssetsBundle ships that adapter.

## Install

```bash
composer require symfony/reprise
npm install --save-dev @symfony/reprise vite
```

Install the relevant preprocessor package when needed, for example `sass` for `.module.scss` files.

## Configure Vite

```ts
// vite.config.ts
import { defineConfig } from 'vite';
import Symfony from '@symfony/reprise/vite';
import AtomicAssets from './vendor/mak/atomic-asset-bundle/Resources/vite/TwigPlugin.js';

export default defineConfig({
    base: '/build/',
    input: {
        app: './assets/app.js',
    },
    css: {
        modules: {
            generateScopedName: '[hash:base64:5]',
        },
    },
    plugins: [
        AtomicAssets(),
        Symfony(),
    ],
});
```

On Vite 8.1 and older, place `input` under `build.rollupOptions.input` instead.

Add the templates to Vite's module graph from your entry:

```js
// assets/app.js
import.meta.glob('../templates/**/*.html.twig', { eager: true });
```

The plugin turns every `{% importModule '...' %}` statement in those templates into an asset import.
It also converts `atomic_asset('...')` references into native `?url&no-inline` imports, so even small
images become emitted files. Reprise writes their URLs into its standard Symfony-compatible
`manifest.json`. Use `vite build --watch` to update these files during development.
The adapter preserves all source-path aliases for identical images deduplicated by Vite.

## Configure Symfony

The PHP and Vite CSS Modules patterns must match:

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

Prefix both patterns, for example `_[hash:base64:5]`, and safelist `/^_/` in your PurgeCSS configuration.

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
