# Symfony AssetMapper

Without a bundler, the bundle does the CSS Modules work itself. While AssetMapper serves the files, it:

- renames the classes of every `*.module.css` file to the same hashes `scope()` puts in your templates;
- fills one stylesheet of your choice with an `@import` of every module, so a single `<link>` loads them all.

There is nothing to build. `asset-map:compile` writes the renamed modules for production, like any other asset.

## Install

```bash
composer require symfony/asset-mapper
```

## Configure the bundle

```yaml
# config/packages/mak_atomic_assets.yaml
mak_atomic_assets:
    localIdentName: '[hash:base64:5]'
    localIdentContext: '%kernel.project_dir%'
    # Paths in atomic_asset() are the mapped paths, see below
    assetPathPrefix: ''
    asset_mapper:
        enabled: true
        # An empty stylesheet in a mapped path, filled with an @import of every module
        stylesheet: '%kernel.project_dir%/assets/styles/atomic-assets.css'
        # Where the modules are looked for (this is the default)
        module_paths: ['%kernel.project_dir%/templates']
```

Create the stylesheet. Its content is kept, after the generated imports:

```css
/* assets/styles/atomic-assets.css */
/* Filled with an @import of every CSS module by the AtomicAssetsBundle */
```

`localIdentContext` must be the directory the module paths are relative to, normally the project root. The hashes
depend on that path, as they do with a bundler.

## Map the components

Map the component directory under its own path, so the modules and colocated assets keep their project-relative
path:

```yaml
# config/packages/asset_mapper.yaml
framework:
    asset_mapper:
        paths:
            - assets/
            templates/components/: templates/components
```

The bundle adds `*.twig` to `framework.asset_mapper.excluded_patterns`, so templates are never published.
Exclude anything else in that directory you don't want public, such as fixtures or notes.

With `assetPathPrefix: ''`, `atomic_asset('logo.svg')` in `templates/components/header/header.html.twig` asks
the asset service for `templates/components/header/logo.svg`, which AssetMapper resolves to its versioned URL.
Don't set `framework.assets.json_manifest_path`; AssetMapper provides the URLs.

## Load the stylesheet

Link it from your layout:

```twig
<link rel="stylesheet" href="{{ asset('styles/atomic-assets.css') }}">
```

Or import it from your entrypoint, which ImportMap turns into a `<link>`:

```js
// assets/app.js
import './styles/atomic-assets.css';
```

`{% importModule %}` and `scope()` work as described in the README. `importModule` only records the module for
`scope()`; the stylesheet already loads every module.

## Stimulus controllers in templates

Add the component directory to StimulusBundle's controller paths:

```yaml
# config/packages/stimulus.yaml
stimulus:
    controller_paths:
        - '%kernel.project_dir%/assets/controllers'
        - '%kernel.project_dir%/templates/components'
```

Only files ending in `_controller.js` or `-controller.js` (or `.ts`) are registered. Identifiers are relative to
the controller path: `templates/components/atoms/sample-atom/sample_atom_controller.js` becomes
`atoms--sample-atom--sample-atom`.

## PurgeCSS

Not needed: there is no bundle to purge. If you post-process the compiled CSS, prefix the hashes, for example
`localIdentName: '_[hash:base64:5]'`, and safelist the prefix.

## Limitations

- Modules are plain CSS: `.module.css` only, with no Sass or Less. Native CSS nesting and custom properties cover
  most of what they were used for. Shared variables belong in a global stylesheet as custom properties.
- Classes are renamed in selectors, including nested rules, `:is()`, `:not()` and `:has()`, and `:global` /
  `:local` work as in css-loader. `composes` is not supported. `@keyframes` names and ids stay global.
- Classes in at-rule preludes, such as `@supports selector(.x)`, are not renamed.
- `importModule` only works with CSS. Import colocated JavaScript other than Stimulus controllers from your
  entrypoint.
- The generated stylesheet uses `@import`, so the modules only start loading once it has. With HTTP/2 that is
  usually fine; with many components, consider a bundler.
