# AtomicAssetsBundle [![Tweet](https://img.shields.io/twitter/url/http/shields.io.svg?style=social)](https://twitter.com/intent/tweet?text=Use%20CSS%20Modules%20with%20Symfony&url=https://github.com/mkrauser/atomic-assets-bundle&hashtags=atomic-assets-bundle)

**AtomicAssetsBundle** is a Symfony bundle that enables the use of CSS Modules and JavaScript module imports directly 
within Twig templates.

This bundle was previously named mak/css-module-bundle. Since the scope widened I change the name to mak/atomic-assets-bundle

### Sample:
```css
/* button.module.scss */
.button {
    background-color: blue;
    color: white;
    padding: 10px 20px;
    border-radius: 5px;
    text-decoration: none;
}
```
```twig
{# Import the CSS module defined above #}
{% importModule 'button.module.scss' %}

{# Use a scoped class from the imported module by string #}
<a class="{{ scope('button') }}">Click me</a>

{# ...or by array #}
<a class="{{ scope(['button', 'button2']) }}">Click me</a>
```

## 🎯 Purpose

CSS Modules bring scoped class names to CSS, avoiding naming collisions and promoting modular architecture. 
This bundle brings the same benefits to Symfony + Twig.

If you're familiar with [CSS Modules](https://github.com/css-modules/css-modules), particularly in React, 
you'll appreciate the ability to:
- Use short, unique class names without global conflicts.
- Keep styles encapsulated at the component level.
- Apply the same development patterns in Symfony projects with Twig.

---

## 📦 Installation

```bash
composer require mak/atomic-assets-bundle
```

---

## ⚙️ Configuration

Choose your asset builder:

- [Webpack Encore](docs/webpack-encore.md)
- [Symfony Reprise with Rsbuild/Rspack](docs/symfony-reprise-rspack.md)
- [Symfony Reprise with Vite](docs/symfony-reprise-vite.md)

---

## 🚀 Usage in Twig

```twig
{# Import CSS module into the current template context #}
{% importModule 'button.module.scss' %}

{# Apply a scoped class from the imported module #}
<a class="{{ scope('button') }}">Click me</a>

{# Alternatively, specify the module explicitly #}
<a class="{{ scope('button', 'button.module.scss') }}">Click me</a>
```

> > **⚠️ Note:**  
> Imported modules only apply to the current template to prevent unintended side effects in included templates.

---

## 🧱 Component-First Folder Structure

Inspired by Atomic Design and modern component-based development, you can colocate templates, styles, 
and JavaScript files:

```
templates/
├─ components/
│  ├─ atoms/
│  │  ├─ sample-atom/
│  │  │  ├─ sample-atom.html.twig
│  │  │  ├─ sample-atom.module.css
│  │  │  ├─ sample_atom_controller.js
│  ├─ molecules/
│  ├─ organisms/
├─ pages/
├─ base/
```

With `importModule`, CSS and JS are automatically bundled for each component.

To discover colocated Stimulus controllers with Symfony Reprise 1.4 or later, configure an
additional controller directory in your Vite or Rsbuild `Symfony()` options:

```ts
stimulus: {
    controllersJson: './assets/controllers.json',
    controllersDirs: { components: './templates/components' },
},
```

The default `assets/controllers` directory remains enabled. The `components` key prefixes
identifiers: `components/atoms/sample-atom/sample_atom_controller.js` under `templates/`
becomes `components--atoms--sample-atom--sample-atom`.

---

## 🔍 Internals

### Colocated images and other static assets

```twig
<img src="{{ atomic_asset('logo.svg') }}" alt="Logo">
```

The path must be a string literal relative to the template directory. Like `importModule`, the Twig
adapter generates an import and lets the bundler handle hashing, emission and dependency tracking.
`atomic_asset()` resolves the resulting URL through Symfony's asset service and the normal
`public/build/manifest.json`; no separate manifest or asset-emission plugin is needed.

Manifest keys include the project-relative source path, for example `build/templates/header/logo.svg`
and `build/templates/footer/logo.svg`. The builder guides configure imports to remain in production
builds and prevent small assets from being inlined, ensuring each asset has a manifest entry.
The Vite adapter also fills missing source aliases in Reprise's manifest when Vite deduplicates
identical files. It reuses the emitted URL; file contents and hashes remain managed by Vite.

Configure Symfony to read the standard manifest (Encore/Reprise may already configure this):

```yaml
framework:
    assets:
        json_manifest_path: '%kernel.project_dir%/public/build/manifest.json'
        strict_mode: true
```

Build assets before rendering templates; during development use a build watcher that writes to disk
(`vite build --watch`, for example).

`atomic_asset()` now accepts only the source path; remove any second target-filename argument.
Remove the old `assetTargetPath` setting and `AtomicAssetsPlugin` registration. If your manifest keys
use a prefix other than `build/`, set `mak_atomic_assets.assetPathPrefix` to that prefix (including its
trailing slash, or `''` for no prefix). Adjust Symfony's manifest path when changing the output directory.
The bundler root/context must match `localIdentContext` (normally the project root).

Run the bundler integration check with installed Encore, Webpack, Rspack, Rsbuild, Vite,
`webpack-manifest-plugin` and `@symfony/reprise` dependencies:

```bash
node Tests/Fixtures/Bundlers/check.cjs /path/to/directory/containing/node_modules
```

- The bundle mimics Webpack’s `css-loader` functionality to hash class names.
- `importModule` registers the module within a template.
- `scope()` computes hashes for literal class names at **compile time**; dynamic class names are hashed at render time.
- Included templates are isolated to prevent shared scope.

> **⚠️ Note:**  
> `scope()` accepts raw string input for class names. Input validation is out of scope.

---

## ❓ Frequently Asked Questions

### How to use Twig CSS Modules with PurifyCSS, UnCSS, or PurgeCSS?

These tools remove unused CSS by scanning for class names. Since CSS module hashes aren't explicitly written in 
Twig/JS, you must:

Prefix the configured CSS Module hash and safelist that prefix. See the guide for your asset builder above.

---

### Is there a performance impact?

CSS class hashing is done at Twig **compile time**. `atomic_asset()` reads the bundler manifest at render time. Shorter hashed
class names may slightly improve render efficiency.

---

### How to use SASS, LESS, or other preprocessors?

Use `.module.scss` or `.module.less` and enable the corresponding preprocessor in your asset builder.

---

### Can I use global variables or mixins in CSS Modules?

Yes. Configure the global resources mechanism provided by your Sass integration. Encore users can use
[`sass-resources-loader`](https://github.com/shakacode/sass-resources-loader).

---

## 🙌 Contributing & Feedback

Pull requests are welcome! If you find bugs or have feature suggestions, feel free to open an issue or tweet about it 
using [#atomic-assets-bundle](https://twitter.com/intent/tweet?hashtags=atomic-assets-bundle).
