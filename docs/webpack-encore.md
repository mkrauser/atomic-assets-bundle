# Webpack Encore

Webpack Encore remains supported, but new projects should prefer Symfony Reprise with
[Rsbuild/Rspack](symfony-reprise-rspack.md) or [Vite](symfony-reprise-vite.md).

## Install

```bash
npm install --save-dev glob-all
```

## Configure Encore

```js
// webpack.config.js
const path = require('node:path');
const glob = require('glob-all');
const Encore = require('@symfony/webpack-encore');
const assetFilename = require('./vendor/mak/atomic-assets-bundle/Resources/AssetFilename');

Encore
    // ...
    .enableVersioning()
    .configureImageRule({ filename: assetFilename() })
    .configureLoaderRule('images', (rule) => {
        rule.sideEffects = true;
    })
    .addEntry('app', [
        './assets/app.js',
        ...glob.sync(['./templates/**/*.html.twig']),
    ])
    .addLoader({
        test: /\.twig$/,
        sideEffects: true,
        use: [{
            loader: path.resolve(
                __dirname,
                'vendor/mak/atomic-assets-bundle/Resources/webpack/TwigLoader.js',
            ),
        }],
    })
    .configureCssLoader((options) => {
        options.modules = {
            auto: (resourcePath) => /\.module\.\w+$/i.test(resourcePath),
            localIdentName: '[hash:base64:5]',
        };
    });
```

The Twig loader turns every `{% importModule '...' %}` statement into an asset import. Adding the Twig files to
the entry makes Webpack process all templates.
References to `atomic_asset('...')` become native image imports. `assetFilename()` omits the leading `templates/` (or `template/`) directory from emitted filenames.
Preserving the remaining component path
keeps identically named files from different components distinct in Encore's normal `manifest.json`.
The `sideEffects` settings retain these imports even in projects declaring `"sideEffects": false`.
Apply the same filename and side-effect settings to other static asset rules when needed.

## Configure Symfony

The PHP and CSS loader patterns must match:

```yaml
# config/packages/mak_atomic_assets.yaml
mak_atomic_assets:
    localIdentName: '[hash:base64:5]'
    localIdentContext: '%kernel.project_dir%'
    localIdentHashSalt: null
```

## Stimulus controllers in templates

To discover colocated Stimulus controllers too:

```js
// assets/bootstrap.js
import { definitionsFromContext } from '@hotwired/stimulus-webpack-helpers';

app.load(
    definitionsFromContext(
        require.context(
            '@symfony/stimulus-bridge/lazy-controller-loader!../templates',
            true,
            /\.[jt]sx?$/,
        ),
    ),
);
```

## PurgeCSS

Prefix hashes in both configurations, for example `_[hash:base64:5]`, then safelist the prefix:

```js
new PurgeCSSPlugin({
    paths: glob.sync([
        `${PATHS.templates}/**/*.html.twig`,
        `${PATHS.assets}/**/*.{js,ts}`,
    ], { nodir: true }),
    safelist: {
        deep: [/^_/],
    },
    extractors: [{
        extractor: purgeHtml,
        extensions: ['html', 'twig'],
    }],
});
```

## Sass resources

Use [`sass-resources-loader`](https://github.com/shakacode/sass-resources-loader) when every module needs shared
variables or mixins:

```js
Encore.configureLoaderRule('scss', (loaderRule) => {
    loaderRule.oneOf.forEach((rule) => {
        rule.use.push({
            loader: 'sass-resources-loader',
            options: {
                resources: [
                    path.resolve(__dirname, './node_modules/bootstrap5/scss/_mixins.scss'),
                    path.resolve(__dirname, './assets/scss/global.scss'),
                ],
            },
        });
    });
});
```
