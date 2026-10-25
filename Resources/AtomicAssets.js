const fs = require('node:fs');
const path = require('node:path');

function assets(content, template, root) {
    const source = content.replace(/\{#[\s\S]*?#\}/g, '');
    const pattern = /\batomic_asset\s*\(\s*(?:path\s*:\s*)?(['"])([^'"\\]+)\1\s*\)/g;
    const result = [];
    for (const match of source.matchAll(pattern)) {
        const absolute = path.resolve(path.dirname(template), match[2]);
        const relative = path.relative(path.dirname(template), absolute);
        const key = path.relative(root, absolute).split(path.sep).join('/');
        const realRelative = path.relative(fs.realpathSync(path.dirname(template)), fs.realpathSync(absolute));
        if ([relative, realRelative, key].some((value) => value === '..' || value.startsWith('../') || path.isAbsolute(value))) {
            throw new Error(`Colocated asset path escapes its allowed directory: ${match[2]}`);
        }
        result.push({
            absolute,
            key,
            request: `./${relative.split(path.sep).join('/')}`,
        });
    }
    return result;
}

function imports(content, template, root, assetSuffix = '') {
    const source = content.replace(/\{#[\s\S]*?#\}/g, '');
    const pattern = /\{%\s*importModule\s+(['"])([^'"]+)\1;?\s*%\}/g;
    return [
        ...Array.from(source.matchAll(pattern), (match) => `import ${JSON.stringify(match[2])};`),
        ...assets(content, template, root).map((asset) => `import ${JSON.stringify(asset.request + assetSuffix)};`),
    ].join('\n');
}

module.exports = { assets, imports };
