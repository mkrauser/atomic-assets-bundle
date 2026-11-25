const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { createRequire } = require('node:module');
const { execFileSync } = require('node:child_process');

const dependencies = createRequire(path.resolve(process.argv[2], 'package.json'));
const repository = path.resolve(__dirname, '../../..');
const vitePlugin = require('../../../Resources/vite/TwigPlugin');
const { imports } = require('../../../Resources/AtomicAssets');
const assetFilename = require('../../../Resources/AssetFilename');
const root = fs.realpathSync(fs.mkdtempSync(path.join(os.tmpdir(), 'atomic-bundlers-')));
let expectedContent = { header: 'header', footer: 'footer' };
fs.writeFileSync(path.join(root, 'package.json'), JSON.stringify({ sideEffects: false }));

for (const component of ['header', 'footer']) {
    const directory = path.join(root, 'templates', component);
    fs.mkdirSync(directory, { recursive: true });
    fs.writeFileSync(path.join(directory, 'logo.svg'), `<svg>${component}</svg>`);
    fs.writeFileSync(path.join(directory, 'view.twig'), "{{ atomic_asset(path: 'logo.svg') }}");
}
fs.writeFileSync(path.join(root, 'app.js'), "import './templates/header/view.twig'; import './templates/footer/view.twig';");

function check(output, header = expectedContent.header) {
    const manifest = JSON.parse(fs.readFileSync(path.join(output, 'manifest.json')));
    if (header !== expectedContent.footer) {
        assert.notEqual(manifest['build/templates/header/logo.svg'], manifest['build/templates/footer/logo.svg']);
    }
    for (const component of ['header', 'footer']) {
        const url = manifest[`build/templates/${component}/logo.svg`];
        assert.ok(url.startsWith('/build/'));
        assert.ok(!/\/templates?\//.test(url), url);
        assert.equal(fs.readFileSync(path.join(output, url.slice('/build/'.length)), 'utf8'), `<svg>${component === 'header' ? header : expectedContent.footer}</svg>`);
    }
    const rendered = JSON.parse(execFileSync('php', [path.join(__dirname, 'render.php'), root, output], { encoding: 'utf8' }));
    assert.deepEqual(rendered, ['header', 'footer'].map((component) => manifest[`build/templates/${component}/logo.svg`]));
    assert.ok(!fs.existsSync(path.join(output, 'atomic_assets/manifest.json')));
    return manifest;
}

async function compile(name, bundler) {
    const { WebpackManifestPlugin } = await import(dependencies.resolve('webpack-manifest-plugin'));
    const output = path.join(root, name);
    const compiler = bundler({
        mode: 'production',
        context: root,
        entry: './app.js',
        output: { path: output, publicPath: '/build/', assetModuleFilename: assetFilename() },
        module: { rules: [
            { test: /\.twig$/, loader: path.join(repository, 'Resources/webpack/TwigLoader.js'), sideEffects: true },
            { test: /\.svg$/, type: 'asset/resource', sideEffects: true },
        ] },
        plugins: [new WebpackManifestPlugin({ basePath: 'build/' })],
    });
    try {
        await new Promise((resolve, reject) => compiler.run((error, stats) => {
            if (error || stats.hasErrors()) {
                reject(error || new Error(stats.toString({ all: false, errors: true })));
            } else {
                resolve();
            }
        }));
        check(output);
        console.log(`${name}: duplicate filenames emitted and resolved`);
    } finally {
        await new Promise((resolve, reject) => compiler.close((error) => error ? reject(error) : resolve()));
    }
}

async function checkEncore() {
    const Encore = dependencies('@symfony/webpack-encore');
    Encore.configureRuntimeEnvironment('production', { context: root });
    Encore.setOutputPath(path.join(root, 'encore'))
        .setPublicPath('/build')
        .setManifestKeyPrefix('build/')
        .addEntry('app', path.join(root, 'app.js'))
        .disableSingleRuntimeChunk()
        .enableVersioning()
        .configureImageRule({ filename: assetFilename() })
        .configureLoaderRule('images', (rule) => { rule.sideEffects = true; })
        .addLoader({ test: /\.twig$/, sideEffects: true, loader: path.join(repository, 'Resources/webpack/TwigLoader.js') });
    const config = Encore.getWebpackConfig();
    config.context = root;
    const compiler = dependencies('webpack')(config);
    try {
        await new Promise((resolve, reject) => compiler.run((error, stats) => {
            if (error || stats.hasErrors()) {
                reject(error || new Error(stats.toString({ all: false, errors: true })));
            } else {
                resolve();
            }
        }));
        check(path.join(root, 'encore'));
        console.log('encore: native manifest resolves images through Symfony');
    } finally {
        await new Promise((resolve, reject) => compiler.close((error) => error ? reject(error) : resolve()));
    }
}

async function checkRsbuild() {
    const { createRsbuild } = await import(dependencies.resolve('@rsbuild/core'));
    const { default: Symfony } = await import(dependencies.resolve('@symfony/reprise/rsbuild'));
    const output = path.join(root, 'rsbuild');
    const rsbuild = await createRsbuild({
        cwd: root,
        rsbuildConfig: {
            source: { entry: { app: { import: './app.js', html: false } } },
            output: { dataUriLimit: 0, filename: { svg: assetFilename() } },
            plugins: [Symfony({ publicPath: '/build/', outputPath: output })],
            tools: {
                rspack: (_config, { appendRules }) => {
                    appendRules({ test: /\.twig$/, sideEffects: true, loader: path.join(repository, 'Resources/webpack/TwigLoader.js') });
                    appendRules({ test: /\.svg$/, sideEffects: true });
                },
            },
        },
    });
    await rsbuild.build();
    check(output);
    console.log('rsbuild: Reprise native manifest resolves images through Symfony');
}

async function main() {
    const template = path.join(root, 'templates/header/view.twig');
    assert.equal(imports("{# {{ atomic_asset('missing.svg') }} #}", template, root), '');
    assert.equal(imports("{{ atomic_asset(path: 'logo.svg') }}", template, root), 'import "./logo.svg";');
    assert.equal(imports("{{ scope(['one', 'two'], 'button.module.scss') }}", template, root), 'import "button.module.scss";');
    assert.equal(imports("{# {{ scope('one', 'ignored.scss') }} #} {{ scope(className, module) }}", template, root), '');
    assert.throws(() => imports("{{ atomic_asset('../footer/logo.svg') }}", template, root), /escapes/);

    await compile('webpack', dependencies('webpack'));
    await compile('rspack', dependencies('@rspack/core').rspack);
    await checkEncore();
    await checkRsbuild();
    const { build } = await import(dependencies.resolve('vite'));
    const { default: Symfony } = await import(dependencies.resolve('@symfony/reprise/vite'));
    const output = path.join(root, 'vite');
    await build({
        root,
        configFile: false,
        base: '/build/',
        logLevel: 'error',
        plugins: [vitePlugin(), Symfony({ publicPath: '/build/', outputPath: output })],
        build: { outDir: output, rollupOptions: { input: path.join(root, 'app.js') } },
    });
    check(output);
    console.log('vite: duplicate filenames emitted and resolved');

    const watcher = await build({
        root,
        configFile: false,
        base: '/build/',
        logLevel: 'silent',
        plugins: [vitePlugin(), Symfony({ publicPath: '/build/', outputPath: output })],
        build: { outDir: output, watch: {}, rollupOptions: { input: path.join(root, 'app.js') } },
    });
    try {
        await new Promise((resolve, reject) => {
            let previous;
            const timeout = setTimeout(() => reject(new Error('Vite watch rebuild timed out')), 15000);
            watcher.on('event', (event) => {
                console.log(`vite watch: ${event.code}`);
                if (event.code === 'ERROR') {
                    clearTimeout(timeout);
                    reject(event.error);
                }
                if (event.code !== 'END') {
                    return;
                }
                try {
                    if (!previous) {
                        previous = check(output);
                        fs.writeFileSync(path.join(root, 'templates/header/logo.svg'), '<svg>updated</svg>');
                    } else {
                        const updated = check(output, 'updated');
                        assert.notEqual(updated['build/templates/header/logo.svg'], previous['build/templates/header/logo.svg']);
                        assert.equal(updated['build/templates/footer/logo.svg'], previous['build/templates/footer/logo.svg']);
                        clearTimeout(timeout);
                        resolve();
                    }
                } catch (error) {
                    clearTimeout(timeout);
                    reject(error);
                }
            });
        });
        console.log('vite: watch rebuild updates changed asset and preserves unchanged asset');
    } finally {
        await watcher.close();
    }

    expectedContent = { header: 'identical', footer: 'identical' };
    for (const component of ['header', 'footer']) {
        fs.writeFileSync(path.join(root, 'templates', component, 'logo.svg'), '<svg>identical</svg>');
    }
    await compile('webpack', dependencies('webpack'));
    await compile('rspack', dependencies('@rspack/core').rspack);
    await checkEncore();
    await checkRsbuild();
    await build({
        root,
        configFile: false,
        logLevel: 'error',
        plugins: [vitePlugin(), Symfony({ publicPath: '/build/', outputPath: output })],
        build: { rollupOptions: { input: path.join(root, 'app.js') } },
    });
    check(output);
    console.log('all bundlers: identical image contents retain both manifest paths');
}

main().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});
