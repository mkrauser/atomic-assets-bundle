/*
 * This file is part of the AtomicAssetsBundle package.
 * Copyright (c) Matthias Krauser <matthias@krauser.eu>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
const { assets, imports } = require('../AtomicAssets');
const fs = require('node:fs');
const path = require('node:path');

module.exports = function atomicAssets() {
    let config;
    return {
        name: 'atomic-assets-twig',
        enforce: 'pre',
        configResolved(resolved) {
            config = resolved;
        },
        transform(content, id) {
            if (!id.endsWith('.twig')) {
                return null;
            }

            for (const asset of assets(content, id, config.root)) {
                this.addWatchFile(asset.absolute);
            }

            return {
                code: imports(content, id, config.root, '?url&no-inline'),
                map: null,
                moduleSideEffects: 'no-treeshake',
            };
        },
        writeBundle: {
            order: 'post',
            sequential: true,
            handler(output, bundle) {
                const shared = Object.values(bundle).filter((file) => file.type === 'asset' && file.originalFileNames.length > 1);
                const manifestPath = path.resolve(config.root, output.dir ?? config.build.outDir, 'manifest.json');
                if (shared.length === 0 || !fs.existsSync(manifestPath)) {
                    return;
                }

                const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
                let changed = false;
                for (const file of shared) {
                    const sources = file.originalFileNames.map((name) => path.relative(config.root, path.resolve(config.root, name)).split(path.sep).join('/'));
                    const entry = Object.entries(manifest).find(([key, url]) => typeof url === 'string'
                        && sources.some((source) => key === source || key.endsWith(`/${source}`)));
                    if (!entry) {
                        continue;
                    }
                    const source = sources.find((source) => entry[0] === source || entry[0].endsWith(`/${source}`));
                    const prefix = entry[0].slice(0, -source.length);
                    for (const alias of sources) {
                        if (!Object.hasOwn(manifest, prefix + alias)) {
                            manifest[prefix + alias] = entry[1];
                            changed = true;
                        }
                    }
                }
                if (changed) {
                    const temporaryPath = `${manifestPath}.${process.pid}.tmp`;
                    fs.writeFileSync(temporaryPath, `${JSON.stringify(manifest, null, 2)}\n`);
                    fs.renameSync(temporaryPath, manifestPath);
                }
            },
        },
    };
};
