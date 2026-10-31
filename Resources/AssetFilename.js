const path = require('node:path');

// Keep source paths in manifest keys, but omit the template root in emitted URLs.
module.exports = function assetFilename(pattern = '[path][name].[contenthash:8][ext]') {
    return (data) => {
        const filename = data.filename.replace(/\\/g, '/');
        const directory = path.posix.dirname(filename);
        const relative = directory === '.' ? '' : `${directory}/`;
        return pattern.replaceAll('[path]', relative.replace(/^templates?\//, ''));
    };
};
