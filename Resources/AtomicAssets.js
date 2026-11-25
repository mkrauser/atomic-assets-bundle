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
    const modules = [];
    for (const block of source.matchAll(/\{[{%][\s\S]*?[}%]\}/g)) {
        for (const call of block[0].matchAll(/\bscope\s*\(/g)) {
            const expression = block[0].slice(call.index + call[0].length);
            let depth = 0;
            let quote = null;
            for (let i = 0; i < expression.length; i++) {
                const char = expression[i];
                if (quote) {
                    if (char === '\\') i++;
                    else if (char === quote) quote = null;
                } else if (char === "'" || char === '"') quote = char;
                else if ('([{'.includes(char)) depth++;
                else if (')]}'.includes(char)) {
                    if (depth === 0) break;
                    depth--;
                } else if (char === ',' && depth === 0) {
                    const match = expression.slice(i + 1).match(/^\s*(['"])([^'"\\]+)\1\s*\)/);
                    if (match) modules.push(match[2]);
                    break;
                }
            }
        }
    }
    return [
        ...Array.from(source.matchAll(pattern), (match) => `import ${JSON.stringify(match[2])};`),
        ...modules.map((module) => `import ${JSON.stringify(module)};`),
        ...assets(content, template, root).map((asset) => `import ${JSON.stringify(asset.request + assetSuffix)};`),
    ].join('\n');
}

module.exports = { assets, imports };
