/*
 * This file is part of the AtomicAssetsBundle package.
 * Copyright (c) Matthias Krauser <matthias@krauser.eu>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
const { imports } = require('../AtomicAssets');

module.exports = function(content, map, meta) {
    const callback = this.async();

    callback(null, imports(content, this.resourcePath, this.rootContext), null, meta);
};
